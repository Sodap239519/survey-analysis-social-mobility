<?php

namespace Tests\Unit;

use App\Models\Household;
use App\Models\SurveyResponse;
use App\Services\CompareHouseholdSurveyLogic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for CompareHouseholdSurveyLogic.
 *
 * Covers:
 *  - povertyLevel() thresholds
 *  - buildSummary() averages, X index, poverty levels
 *  - scoresFromRawData() X→0-100 normalization and null handling
 *  - scoresFromResponse() field mapping
 *  - getBeforeScores() source priority (survey_response > legacy_import)
 *  - getAfterScores() found/not-found
 *  - compare() full round-trip
 */
class CompareHouseholdSurveyLogicTest extends TestCase
{
    use RefreshDatabase;

    private CompareHouseholdSurveyLogic $logic;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logic = new CompareHouseholdSurveyLogic();
    }

    // ─── povertyLevel ────────────────────────────────────────────────────────

    public function test_poverty_level_boundaries(): void
    {
        $this->assertEquals(1, $this->logic->povertyLevel(1.00));
        $this->assertEquals(1, $this->logic->povertyLevel(1.749));
        $this->assertEquals(2, $this->logic->povertyLevel(1.75));
        $this->assertEquals(2, $this->logic->povertyLevel(2.499));
        $this->assertEquals(3, $this->logic->povertyLevel(2.50));
        $this->assertEquals(3, $this->logic->povertyLevel(3.249));
        $this->assertEquals(4, $this->logic->povertyLevel(3.25));
        $this->assertEquals(4, $this->logic->povertyLevel(4.00));
    }

    // ─── buildSummary ─────────────────────────────────────────────────────────

    public function test_build_summary_all_capitals(): void
    {
        $capitals = [
            'human'     => ['label' => 'ทุนมนุษย์',    'before' => 50.0,  'after' => 70.0,  'diff' => 20.0],
            'physical'  => ['label' => 'ทุนกายภาพ',    'before' => 40.0,  'after' => 60.0,  'diff' => 20.0],
            'financial' => ['label' => 'ทุนการเงิน',    'before' => 30.0,  'after' => 50.0,  'diff' => 20.0],
            'natural'   => ['label' => 'ทุนธรรมชาติ',   'before' => 60.0,  'after' => 80.0,  'diff' => 20.0],
            'social'    => ['label' => 'ทุนทางสังคม',   'before' => 70.0,  'after' => 90.0,  'diff' => 20.0],
        ];

        $summary = $this->logic->buildSummary($capitals);

        // avg_before = (50+40+30+60+70)/5 = 50
        $this->assertEqualsWithDelta(50.0, $summary['avg_before'], 0.01);
        // avg_after  = (70+60+50+80+90)/5 = 70
        $this->assertEqualsWithDelta(70.0, $summary['avg_after'], 0.01);
        // avg_diff   = 20
        $this->assertEqualsWithDelta(20.0, $summary['avg_diff'], 0.01);

        // X = 1 + (avg/100)*3
        // x_before = 1 + (50/100)*3 = 2.5
        $this->assertEqualsWithDelta(2.5, $summary['x_before'], 0.01);
        // x_after  = 1 + (70/100)*3 = 3.1
        $this->assertEqualsWithDelta(3.1, $summary['x_after'], 0.01);

        $this->assertEquals(3, $summary['poverty_level_before']); // 2.5 => level 3
        $this->assertEquals(3, $summary['poverty_level_after']);  // 3.1 => level 3
        $this->assertEquals(0, $summary['poverty_level_diff']);
    }

    public function test_build_summary_with_null_values(): void
    {
        $capitals = [
            'human'     => ['label' => 'ทุนมนุษย์',  'before' => 50.0, 'after' => null, 'diff' => null],
            'physical'  => ['label' => 'ทุนกายภาพ',  'before' => 50.0, 'after' => null, 'diff' => null],
            'financial' => ['label' => 'ทุนการเงิน',  'before' => 50.0, 'after' => null, 'diff' => null],
            'natural'   => ['label' => 'ทุนธรรมชาติ', 'before' => 50.0, 'after' => null, 'diff' => null],
            'social'    => ['label' => 'ทุนทางสังคม', 'before' => 50.0, 'after' => null, 'diff' => null],
        ];

        $summary = $this->logic->buildSummary($capitals);

        $this->assertEquals(50.0, $summary['avg_before']);
        $this->assertNull($summary['avg_after']);
        $this->assertNull($summary['avg_diff']);
        $this->assertNull($summary['x_after']);
        $this->assertNull($summary['poverty_level_after']);
        $this->assertNull($summary['poverty_level_diff']);
    }

    public function test_build_summary_all_zeros(): void
    {
        $capitals = [];
        foreach (['human', 'physical', 'financial', 'natural', 'social'] as $slug) {
            $capitals[$slug] = ['label' => $slug, 'before' => 0.0, 'after' => 0.0, 'diff' => 0.0];
        }

        $summary = $this->logic->buildSummary($capitals);

        $this->assertEqualsWithDelta(0.0, $summary['avg_before'], 0.001);
        $this->assertEqualsWithDelta(1.0, $summary['x_before'], 0.001); // X_min = 1.0
        $this->assertEquals(1, $summary['poverty_level_before']);
    }

    public function test_build_summary_all_100(): void
    {
        $capitals = [];
        foreach (['human', 'physical', 'financial', 'natural', 'social'] as $slug) {
            $capitals[$slug] = ['label' => $slug, 'before' => 100.0, 'after' => 100.0, 'diff' => 0.0];
        }

        $summary = $this->logic->buildSummary($capitals);

        $this->assertEqualsWithDelta(100.0, $summary['avg_before'], 0.001);
        $this->assertEqualsWithDelta(4.0, $summary['x_before'], 0.001); // X_max = 4.0
        $this->assertEquals(4, $summary['poverty_level_before']);
    }

    // ─── scoresFromRawData ────────────────────────────────────────────────────

    public function test_scores_from_raw_data_normalizes_x_to_0_100(): void
    {
        // X=1.0 => 0, X=4.0 => 100, X=2.5 => 50
        $household = new Household();
        $raw = array_fill(0, 100, null);
        $raw[44] = '2.5';   // human    => (2.5-1)/3*100 = 50
        $raw[55] = '1.0';   // physical => 0
        $raw[69] = '4.0';   // financial=> 100
        $raw[78] = '1.75';  // natural  => (0.75/3)*100 = 25
        $raw[87] = '3.25';  // social   => (2.25/3)*100 = 75
        $household->raw_data = $raw;

        $scores = $this->logic->scoresFromRawData($household);

        $this->assertEqualsWithDelta(50.0,  $scores['human'],    0.01);
        $this->assertEqualsWithDelta(0.0,   $scores['physical'], 0.01);
        $this->assertEqualsWithDelta(100.0, $scores['financial'],0.01);
        $this->assertEqualsWithDelta(25.0,  $scores['natural'],  0.01);
        $this->assertEqualsWithDelta(75.0,  $scores['social'],   0.01);
    }

    public function test_scores_from_raw_data_clamps_out_of_range(): void
    {
        $household = new Household();
        $raw = array_fill(0, 100, null);
        $raw[44] = '5.0';  // above 4 → clamped to 4 → 100
        $raw[55] = '0.0';  // below 1 → clamped to 1 → 0
        $raw[69] = '2.5';
        $raw[78] = '2.5';
        $raw[87] = '2.5';
        $household->raw_data = $raw;

        $scores = $this->logic->scoresFromRawData($household);

        $this->assertEqualsWithDelta(100.0, $scores['human'],    0.01);
        $this->assertEqualsWithDelta(0.0,   $scores['physical'], 0.01);
    }

    public function test_scores_from_raw_data_returns_null_for_empty_value(): void
    {
        $household = new Household();
        $household->raw_data = []; // no data at all

        $scores = $this->logic->scoresFromRawData($household);

        foreach ($scores as $v) {
            $this->assertNull($v);
        }
    }

    public function test_scores_from_raw_data_null_raw_data(): void
    {
        $household = new Household();
        $household->raw_data = null;

        $scores = $this->logic->scoresFromRawData($household);

        $this->assertCount(5, $scores);
        foreach ($scores as $v) {
            $this->assertNull($v);
        }
    }

    // ─── scoresFromResponse ───────────────────────────────────────────────────

    public function test_scores_from_response_maps_fields(): void
    {
        $response = new SurveyResponse([
            'score_human'     => 45.0,
            'score_physical'  => 60.0,
            'score_financial' => 70.0,
            'score_natural'   => 55.0,
            'score_social'    => 80.0,
        ]);

        $scores = $this->logic->scoresFromResponse($response);

        $this->assertEqualsWithDelta(45.0, $scores['human'],    0.001);
        $this->assertEqualsWithDelta(60.0, $scores['physical'], 0.001);
        $this->assertEqualsWithDelta(70.0, $scores['financial'],0.001);
        $this->assertEqualsWithDelta(55.0, $scores['natural'],  0.001);
        $this->assertEqualsWithDelta(80.0, $scores['social'],   0.001);
    }

    public function test_scores_from_response_null_fields_returned_as_null(): void
    {
        $response = new SurveyResponse([
            'score_human' => null,
        ]);

        $scores = $this->logic->scoresFromResponse($response);

        $this->assertNull($scores['human']);
    }

    // ─── getBeforeScores priority ─────────────────────────────────────────────

    public function test_get_before_scores_prefers_survey_response_over_raw_data(): void
    {
        $household = Household::create([
            'house_code' => '12345678901',
            'raw_data'   => array_fill(0, 100, '2.5'),  // would give 50.0 for all
        ]);

        // Create a period='before' SurveyResponse with different scores
        SurveyResponse::create([
            'household_id'    => $household->id,
            'period'          => 'before',
            'score_human'     => 80.0,
            'score_physical'  => 80.0,
            'score_financial' => 80.0,
            'score_natural'   => 80.0,
            'score_social'    => 80.0,
        ]);

        $result = $this->logic->getBeforeScores($household);

        $this->assertEquals('survey_response', $result['source']);
        $this->assertEqualsWithDelta(80.0, $result['scores']['human'], 0.001);
    }

    public function test_get_before_scores_falls_back_to_legacy_import(): void
    {
        $raw = array_fill(0, 100, null);
        $raw[44] = '2.5';   // human => 50
        $raw[55] = '2.5';
        $raw[69] = '2.5';
        $raw[78] = '2.5';
        $raw[87] = '2.5';

        $household = Household::create([
            'house_code' => '12345678902',
            'raw_data'   => $raw,
        ]);

        // No SurveyResponse with period='before' exists

        $result = $this->logic->getBeforeScores($household);

        $this->assertEquals('legacy_import', $result['source']);
        $this->assertEqualsWithDelta(50.0, $result['scores']['human'], 0.01);
    }

    // ─── getAfterScores ───────────────────────────────────────────────────────

    public function test_get_after_scores_returns_not_found_when_no_response(): void
    {
        $household = Household::create(['house_code' => '12345678903']);

        $result = $this->logic->getAfterScores($household);

        $this->assertFalse($result['found']);
        foreach ($result['scores'] as $v) {
            $this->assertNull($v);
        }
    }

    public function test_get_after_scores_returns_latest_response(): void
    {
        $household = Household::create(['house_code' => '12345678904']);

        SurveyResponse::create([
            'household_id'    => $household->id,
            'period'          => 'after',
            'surveyed_at'     => '2025-01-01',
            'score_human'     => 55.0,
            'score_physical'  => 55.0,
            'score_financial' => 55.0,
            'score_natural'   => 55.0,
            'score_social'    => 55.0,
        ]);

        SurveyResponse::create([
            'household_id'    => $household->id,
            'period'          => 'after',
            'surveyed_at'     => '2025-06-01',
            'score_human'     => 75.0,
            'score_physical'  => 75.0,
            'score_financial' => 75.0,
            'score_natural'   => 75.0,
            'score_social'    => 75.0,
        ]);

        $result = $this->logic->getAfterScores($household);

        $this->assertTrue($result['found']);
        $this->assertEqualsWithDelta(75.0, $result['scores']['human'], 0.001);
    }

    // ─── compare (full round-trip) ────────────────────────────────────────────

    public function test_compare_returns_diff_per_capital(): void
    {
        $raw = array_fill(0, 100, null);
        // X=2.5 => (2.5-1)/3*100 = 50.0
        foreach ([44, 55, 69, 78, 87] as $col) {
            $raw[$col] = '2.5';
        }

        $household = Household::create([
            'house_code' => '12345678905',
            'raw_data'   => $raw,
        ]);

        SurveyResponse::create([
            'household_id'    => $household->id,
            'period'          => 'after',
            'score_human'     => 70.0,
            'score_physical'  => 60.0,
            'score_financial' => 80.0,
            'score_natural'   => 65.0,
            'score_social'    => 75.0,
        ]);

        $result = $this->logic->compare($household);

        $this->assertEquals($household->id, $result['household_id']);
        $this->assertEquals('12345678905', $result['house_code']);
        $this->assertEquals('legacy_import', $result['before_source']);
        $this->assertTrue($result['after_found']);

        // All before = 50, after varies
        $this->assertEqualsWithDelta(50.0, $result['capitals']['human']['before'], 0.01);
        $this->assertEqualsWithDelta(70.0, $result['capitals']['human']['after'],  0.01);
        $this->assertEqualsWithDelta(20.0, $result['capitals']['human']['diff'],   0.01);

        $this->assertEqualsWithDelta(30.0, $result['capitals']['financial']['diff'], 0.01);

        // Summary: avg_before=50, avg_after=(70+60+80+65+75)/5=70
        $this->assertEqualsWithDelta(50.0, $result['summary']['avg_before'], 0.01);
        $this->assertEqualsWithDelta(70.0, $result['summary']['avg_after'],  0.01);
        $this->assertEqualsWithDelta(20.0, $result['summary']['avg_diff'],   0.01);

        // x_before = 1+(50/100)*3 = 2.5; x_after = 1+(70/100)*3 = 3.1
        $this->assertEqualsWithDelta(2.5, $result['summary']['x_before'], 0.01);
        $this->assertEqualsWithDelta(3.1, $result['summary']['x_after'],  0.01);
    }

    public function test_compare_with_no_after_response(): void
    {
        $raw = array_fill(0, 100, null);
        foreach ([44, 55, 69, 78, 87] as $col) {
            $raw[$col] = '2.5';
        }

        $household = Household::create([
            'house_code' => '12345678906',
            'raw_data'   => $raw,
        ]);

        $result = $this->logic->compare($household);

        $this->assertFalse($result['after_found']);

        foreach ($result['capitals'] as $capital) {
            $this->assertNotNull($capital['before']);
            $this->assertNull($capital['after']);
            $this->assertNull($capital['diff']);
        }

        $this->assertNull($result['summary']['avg_after']);
        $this->assertNull($result['summary']['avg_diff']);
        $this->assertNull($result['summary']['x_after']);
        $this->assertNull($result['summary']['poverty_level_after']);
    }

    public function test_compare_with_survey_response_as_before_source(): void
    {
        $household = Household::create(['house_code' => '12345678907']);

        SurveyResponse::create([
            'household_id'    => $household->id,
            'period'          => 'before',
            'score_human'     => 30.0,
            'score_physical'  => 30.0,
            'score_financial' => 30.0,
            'score_natural'   => 30.0,
            'score_social'    => 30.0,
        ]);

        SurveyResponse::create([
            'household_id'    => $household->id,
            'period'          => 'after',
            'score_human'     => 60.0,
            'score_physical'  => 60.0,
            'score_financial' => 60.0,
            'score_natural'   => 60.0,
            'score_social'    => 60.0,
        ]);

        $result = $this->logic->compare($household);

        $this->assertEquals('survey_response', $result['before_source']);
        $this->assertEqualsWithDelta(30.0, $result['summary']['avg_before'], 0.01);
        $this->assertEqualsWithDelta(60.0, $result['summary']['avg_after'],  0.01);
        $this->assertEqualsWithDelta(30.0, $result['summary']['avg_diff'],   0.01);
    }

    public function test_capital_labels_are_thai(): void
    {
        $household = Household::create(['house_code' => '12345678908']);

        $result = $this->logic->compare($household);

        $this->assertEquals('ทุนมนุษย์',    $result['capitals']['human']['label']);
        $this->assertEquals('ทุนกายภาพ',    $result['capitals']['physical']['label']);
        $this->assertEquals('ทุนการเงิน',    $result['capitals']['financial']['label']);
        $this->assertEquals('ทุนธรรมชาติ',   $result['capitals']['natural']['label']);
        $this->assertEquals('ทุนทางสังคม',   $result['capitals']['social']['label']);
    }
}
