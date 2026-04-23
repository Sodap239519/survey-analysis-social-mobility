<?php

namespace Tests\Unit;

use App\Models\Capital;
use App\Models\Choice;
use App\Models\Question;
use App\Services\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoringServiceTest extends TestCase
{
    use RefreshDatabase;

    private ScoringService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ScoringService();
        $this->seed(\Database\Seeders\QuestionnaireSeeder::class);
    }

    // ─── Poverty Level Mapping ───────────────────────────────────────────────

    public function test_poverty_level_1(): void
    {
        $this->assertEquals(1, $this->service->getPovertyLevel(1.00));
        $this->assertEquals(1, $this->service->getPovertyLevel(1.50));
        $this->assertEquals(1, $this->service->getPovertyLevel(1.749));
    }

    public function test_poverty_level_2(): void
    {
        $this->assertEquals(2, $this->service->getPovertyLevel(1.75));
        $this->assertEquals(2, $this->service->getPovertyLevel(2.00));
        $this->assertEquals(2, $this->service->getPovertyLevel(2.499));
    }

    public function test_poverty_level_3(): void
    {
        $this->assertEquals(3, $this->service->getPovertyLevel(2.50));
        $this->assertEquals(3, $this->service->getPovertyLevel(3.00));
        $this->assertEquals(3, $this->service->getPovertyLevel(3.249));
    }

    public function test_poverty_level_4(): void
    {
        $this->assertEquals(4, $this->service->getPovertyLevel(3.25));
        $this->assertEquals(4, $this->service->getPovertyLevel(4.00));
    }

    // ─── Poverty Level Labels ────────────────────────────────────────────────

    public function test_poverty_level_labels(): void
    {
        $this->assertEquals('อยู่ลำบาก', $this->service->getPovertyLevelLabel(1));
        $this->assertEquals('อยู่ยาก',   $this->service->getPovertyLevelLabel(2));
        $this->assertEquals('อยู่พอได้',  $this->service->getPovertyLevelLabel(3));
        $this->assertEquals('อยู่ดี',     $this->service->getPovertyLevelLabel(4));
    }

    public function test_poverty_level_label_unknown_returns_empty(): void
    {
        $this->assertEquals('', $this->service->getPovertyLevelLabel(0));
        $this->assertEquals('', $this->service->getPovertyLevelLabel(5));
    }

    // ─── Aggregate Score Computation ─────────────────────────────────────────

    public function test_aggregate_score_from_normalized(): void
    {
        // All capitals at 100 => X = 1 + 1 * 3 = 4.0
        $scores = ['human' => 100, 'physical' => 100, 'financial' => 100, 'natural' => 100, 'social' => 100];
        $this->assertEqualsWithDelta(4.0, $this->service->computeAggregateScore($scores), 0.001);

        // All capitals at 0 => X = 1.0
        $scores = ['human' => 0, 'physical' => 0, 'financial' => 0, 'natural' => 0, 'social' => 0];
        $this->assertEqualsWithDelta(1.0, $this->service->computeAggregateScore($scores), 0.001);

        // All capitals at 50 => X = 1 + 0.5 * 3 = 2.5
        $scores = ['human' => 50, 'physical' => 50, 'financial' => 50, 'natural' => 50, 'social' => 50];
        $this->assertEqualsWithDelta(2.5, $this->service->computeAggregateScore($scores), 0.001);
    }

    // ─── Normalize Score ─────────────────────────────────────────────────────

    public function test_normalize_score(): void
    {
        $this->assertEqualsWithDelta(100.0, $this->service->normalizeScore(100, 100), 0.001);
        $this->assertEqualsWithDelta(0.0, $this->service->normalizeScore(0, 100), 0.001);
        $this->assertEqualsWithDelta(50.0, $this->service->normalizeScore(50, 100), 0.001);
        $this->assertEqualsWithDelta(75.0, $this->service->normalizeScore(75, 100), 0.001);
    }

    public function test_normalize_score_zero_max(): void
    {
        $this->assertEquals(0.0, $this->service->normalizeScore(10, 0));
    }

    // ─── Multi-select Question Scoring ───────────────────────────────────────

    public function test_multi_select_score_sum(): void
    {
        $question = Question::where('question_key', 'Q3')->first();
        $this->assertNotNull($question, 'Q3 must exist in seeded data');

        // Get choices for Q3 (exclude exclusive choice 0)
        $choice1 = Choice::where('question_id', $question->id)->where('choice_key', '1')->first();
        $choice2 = Choice::where('question_id', $question->id)->where('choice_key', '2')->first();

        $this->assertNotNull($choice1);
        $this->assertNotNull($choice2);

        $score = $this->service->scoreQuestion($question, [$choice1->id, $choice2->id]);

        // Q3 new rule: any non-exclusive skill(s) selected => full score (20)
        $this->assertEqualsWithDelta(20.0, $score, 0.001);
    }

    public function test_multi_select_exclusive_choice_gives_zero(): void
    {
        $question = Question::where('question_key', 'Q3')->first();
        $this->assertNotNull($question);

        $exclusiveChoice = Choice::where('question_id', $question->id)->where('is_exclusive', true)->first();
        $this->assertNotNull($exclusiveChoice, 'Q3 should have an exclusive choice (0)');

        $otherChoice = Choice::where('question_id', $question->id)->where('choice_key', '1')->first();

        // Selecting exclusive choice together with other choices => 0
        $score = $this->service->scoreQuestion($question, [$exclusiveChoice->id, $otherChoice->id]);
        $this->assertEquals(0.0, $score);
    }

    public function test_q3_exclusive_only_gives_zero(): void
    {
        $question = Question::where('question_key', 'Q3')->first();
        $this->assertNotNull($question);

        $exclusiveChoice = Choice::where('question_id', $question->id)->where('is_exclusive', true)->first();
        $this->assertNotNull($exclusiveChoice, 'Q3 should have an exclusive "ไม่มี" choice');

        // Selecting only the exclusive "ไม่มี" choice => 0
        $score = $this->service->scoreQuestion($question, [$exclusiveChoice->id]);
        $this->assertEquals(0.0, $score);
    }

    public function test_q3_single_non_exclusive_choice_gives_full_score(): void
    {
        $question = Question::where('question_key', 'Q3')->first();
        $this->assertNotNull($question);

        $choice1 = Choice::where('question_id', $question->id)->where('choice_key', '1')->first();
        $this->assertNotNull($choice1);

        // Selecting only one non-exclusive skill => full score (20)
        $score = $this->service->scoreQuestion($question, [$choice1->id]);
        $this->assertEqualsWithDelta(20.0, $score, 0.001);
    }

    public function test_multi_select_max_score_cap(): void
    {
        $question = Question::where('question_key', 'Q3')->first();
        $this->assertNotNull($question);

        // Select all non-exclusive choices
        $nonExclusiveChoices = Choice::where('question_id', $question->id)
            ->where('is_exclusive', false)
            ->pluck('id')
            ->toArray();

        $score = $this->service->scoreQuestion($question, $nonExclusiveChoices);

        // Score should be capped at max_score = 20
        $this->assertLessThanOrEqual($question->max_score, $score);
        $this->assertEquals($question->max_score, $score);
    }

    // ─── Q6 Special Rule ─────────────────────────────────────────────────────

    public function test_q6_no_problem_gives_full_score(): void
    {
        $question = Question::where('question_key', 'Q6')->first();
        $this->assertNotNull($question, 'Q6 must exist');

        $noProblemChoice = Choice::where('question_id', $question->id)
            ->where('is_exclusive', true)
            ->first();
        $this->assertNotNull($noProblemChoice, 'Q6 exclusive choice must exist');

        $score = $this->service->scoreQuestion($question, [$noProblemChoice->id]);

        $this->assertEqualsWithDelta(30.0, $score, 0.001);
    }

    public function test_q6_one_sub_problem_applies_penalty(): void
    {
        $question = Question::where('question_key', 'Q6')->first();
        $this->assertNotNull($question);

        $subProblem = Choice::where('question_id', $question->id)
            ->where('choice_key', '1.1')
            ->first();
        $this->assertNotNull($subProblem, 'Choice 1.1 must exist for Q6');

        $score = $this->service->scoreQuestion($question, [$subProblem->id]);
        // max_score - 5 * 1 = 25
        $this->assertEqualsWithDelta(25.0, $score, 0.001);
    }

    public function test_q6_multiple_sub_problems(): void
    {
        $question = Question::where('question_key', 'Q6')->first();
        $this->assertNotNull($question);

        $subProblems = Choice::where('question_id', $question->id)
            ->whereIn('choice_key', ['1.1', '1.2', '1.3'])
            ->pluck('id')
            ->toArray();

        $this->assertCount(3, $subProblems);

        $score = $this->service->scoreQuestion($question, $subProblems);
        // max_score - 5 * 3 = 15
        $this->assertEqualsWithDelta(15.0, $score, 0.001);
    }

    public function test_q6_many_problems_does_not_go_below_zero(): void
    {
        $question = Question::where('question_key', 'Q6')->first();
        $this->assertNotNull($question);

        // Select all sub-problems (7 of them = 7*5 = 35 penalty on 30 pts => 0)
        $subProblems = Choice::where('question_id', $question->id)
            ->where('is_exclusive', false)
            ->where('choice_key', 'like', '1.%')
            ->pluck('id')
            ->toArray();

        $score = $this->service->scoreQuestion($question, $subProblems);

        $this->assertGreaterThanOrEqual(0.0, $score);
    }

    // ─── Empty Answers ───────────────────────────────────────────────────────

    public function test_empty_selection_gives_zero_score(): void
    {
        $question = Question::where('question_key', 'Q3')->first();
        $score = $this->service->scoreQuestion($question, []);
        $this->assertEquals(0.0, $score);
    }
}
