<?php

namespace App\Services;

use App\Models\Household;
use App\Models\SurveyResponse;

/**
 * CompareHouseholdSurveyLogic
 *
 * Compares Before (legacy CSV import) vs After (new survey response) capital scores
 * for a given household, matched by house_code (11-digit รหัสบ้าน).
 *
 * Before scores are read from two sources (in priority order):
 *  1. A SurveyResponse record with period='before' (score_* columns, 0–100 scale).
 *  2. Fallback: Household.raw_data columns from the legacy CSV import (X scale 1–4),
 *     converted to 0–100 using: normalized = (x – 1.0) / 3.0 * 100.
 *
 * After scores are read from the most recent SurveyResponse with period='after'
 * (score_* columns, 0–100 scale).
 *
 * Capital slug → legacy CSV column mapping (0-based index in raw_data array):
 *   human     => 44  (ทุนมนุษย์)
 *   physical  => 55  (ทุนกายภาพ)
 *   financial => 69  (ทุนการเงิน)   NOTE: column name 'ทุนการเงิน' — pending mapping manual
 *   natural   => 78  (ทุนธรรมชาติ)
 *   social    => 87  (ทุนทางสังคม)
 *
 * After-survey question grouping → capital slug mapping is handled by ScoringService
 * and stored in SurveyResponse.score_* columns.  The field-level question-to-capital
 * assignment for the "After" questionnaire (แบบกำกับติดตาม_After) is:
 *   human     => Q2, Q3, Q3.1, Q3.2, Q4      (max 20 pts each)
 *   physical  => Q5, Q6                        (70 + 30 pts)
 *   financial => Q7, Q8, Q9, Q10, Q11         (20 pts each)
 *   natural   => Q12–Q16 (ตอนที่ 4)            — pending mapping manual (exact questions TBC)
 *   social    => Q17–Q26 (ตอนที่ 5)            — pending mapping manual (exact questions TBC)
 *
 * X index (poverty level 1–4):
 *   X = 1.0 + (avg_normalized / 100) * 3.0
 *   Level 1: 1.00 ≤ X < 1.75  (อยู่ลำบาก)
 *   Level 2: 1.75 ≤ X < 2.50  (อยู่ยาก)
 *   Level 3: 2.50 ≤ X < 3.25  (อยู่พอได้)
 *   Level 4: 3.25 ≤ X ≤ 4.00  (อยู่ดี)
 */
class CompareHouseholdSurveyLogic
{
    /**
     * Capital metadata: slug => [label (Thai), raw_data column index, score field]
     *
     * raw_data_col: 0-based integer index in Household.raw_data (from legacy CSV).
     * score_field:  column name in survey_responses table (period=before/after).
     */
    private const CAPITALS = [
        'human'     => ['label' => 'ทุนมนุษย์',           'raw_data_col' => 44, 'score_field' => 'score_human'],
        'physical'  => ['label' => 'ทุนกายภาพ',           'raw_data_col' => 55, 'score_field' => 'score_physical'],
        'financial' => ['label' => 'ทุนการเงิน',           'raw_data_col' => 69, 'score_field' => 'score_financial'],
        'natural'   => ['label' => 'ทุนธรรมชาติ',          'raw_data_col' => 78, 'score_field' => 'score_natural'],
        'social'    => ['label' => 'ทุนทางสังคม',          'raw_data_col' => 87, 'score_field' => 'score_social'],
    ];

    /**
     * Run Before–After comparison for one household.
     *
     * @param  Household   $household
     * @param  int|null    $surveyYear   Filter survey_year on SurveyResponse (optional)
     * @param  int|null    $surveyRound  Filter survey_round on SurveyResponse (optional)
     * @return array{
     *   household_id: int,
     *   house_code: string,
     *   before_source: string,
     *   after_found: bool,
     *   capitals: array<string, array{label: string, before: float|null, after: float|null, diff: float|null}>,
     *   summary: array{avg_before: float|null, avg_after: float|null, avg_diff: float|null,
     *                  x_before: float|null,   x_after: float|null,   x_diff: float|null,
     *                  poverty_level_before: int|null, poverty_level_after: int|null, poverty_level_diff: int|null}
     * }
     */
    public function compare(Household $household, ?int $surveyYear = null, ?int $surveyRound = null): array
    {
        $beforeScores = $this->getBeforeScores($household, $surveyYear, $surveyRound);
        $afterScores  = $this->getAfterScores($household, $surveyYear, $surveyRound);

        $capitals = [];
        foreach (array_keys(self::CAPITALS) as $slug) {
            $label  = self::CAPITALS[$slug]['label'];
            $before = $beforeScores['scores'][$slug] ?? null;
            $after  = $afterScores['scores'][$slug] ?? null;
            $diff   = ($before !== null && $after !== null) ? round($after - $before, 4) : null;

            $capitals[$slug] = [
                'label'  => $label,
                'before' => $before,
                'after'  => $after,
                'diff'   => $diff,
            ];
        }

        $summary = $this->buildSummary($capitals);

        return [
            'household_id'  => $household->id,
            'house_code'    => $household->house_code,
            'before_source' => $beforeScores['source'],
            'after_found'   => $afterScores['found'],
            'capitals'      => $capitals,
            'summary'       => $summary,
        ];
    }

    /**
     * Retrieve Before capital scores (0–100 normalized).
     *
     * Priority:
     *  1. SurveyResponse with period='before' (already stored as 0–100).
     *  2. Household.raw_data legacy CSV columns (X scale 1–4 → converted to 0–100).
     *
     * @return array{source: string, scores: array<string, float|null>}
     */
    public function getBeforeScores(Household $household, ?int $surveyYear = null, ?int $surveyRound = null): array
    {
        // 1. Try SurveyResponse period='before'
        $query = SurveyResponse::where('household_id', $household->id)
            ->where('period', 'before');

        if ($surveyYear !== null) {
            $query->where('survey_year', $surveyYear);
        }
        if ($surveyRound !== null) {
            $query->where('survey_round', $surveyRound);
        }

        $response = $query->latest('surveyed_at')->first();

        if ($response) {
            return [
                'source' => 'survey_response',
                'scores' => $this->scoresFromResponse($response),
            ];
        }

        // 2. Fallback: legacy raw_data from household import
        return [
            'source' => 'legacy_import',
            'scores' => $this->scoresFromRawData($household),
        ];
    }

    /**
     * Retrieve After capital scores (0–100 normalized) from the most recent
     * SurveyResponse with period='after'.
     *
     * @return array{found: bool, scores: array<string, float|null>}
     */
    public function getAfterScores(Household $household, ?int $surveyYear = null, ?int $surveyRound = null): array
    {
        $query = SurveyResponse::where('household_id', $household->id)
            ->where('period', 'after');

        if ($surveyYear !== null) {
            $query->where('survey_year', $surveyYear);
        }
        if ($surveyRound !== null) {
            $query->where('survey_round', $surveyRound);
        }

        $response = $query->latest('surveyed_at')->first();

        if (!$response) {
            return ['found' => false, 'scores' => array_fill_keys(array_keys(self::CAPITALS), null)];
        }

        return [
            'found'  => true,
            'scores' => $this->scoresFromResponse($response),
        ];
    }

    /**
     * Extract per-capital scores (0–100) from a SurveyResponse record.
     *
     * @return array<string, float|null>
     */
    public function scoresFromResponse(SurveyResponse $response): array
    {
        $scores = [];
        foreach (self::CAPITALS as $slug => $meta) {
            $value = $response->{$meta['score_field']};
            $scores[$slug] = $value !== null ? round((float) $value, 4) : null;
        }

        return $scores;
    }

    /**
     * Extract per-capital scores from Household baseline_score_* fields (new XLSX import).
     *
     * Baseline scores from the XLSX file use the X scale [1.0, 4.0].
     * Conversion to 0–100: normalized = (x – 1.0) / 3.0 * 100
     *
     * Falls back to the legacy raw_data array if baseline_score_* fields are not set.
     *
     * @return array<string, float|null>
     */
    public function scoresFromRawData(Household $household): array
    {
        $scores = [];

        // First try the dedicated baseline_score_* columns (set by MultiSheetHouseholdImport)
        $hasBaseline = $household->baseline_score_human !== null
            || $household->baseline_score_physical !== null
            || $household->baseline_score_financial !== null
            || $household->baseline_score_natural !== null
            || $household->baseline_score_social !== null;

        if ($hasBaseline) {
            $fieldMap = [
                'human'     => 'baseline_score_human',
                'physical'  => 'baseline_score_physical',
                'financial' => 'baseline_score_financial',
                'natural'   => 'baseline_score_natural',
                'social'    => 'baseline_score_social',
            ];

            foreach ($fieldMap as $slug => $field) {
                $value = $household->{$field};
                if ($value === null) {
                    $scores[$slug] = null;
                    continue;
                }
                $x = (float) $value;
                $x = max(1.0, min(4.0, $x));
                $scores[$slug] = round(($x - 1.0) / 3.0 * 100, 4);
            }

            return $scores;
        }

        // Legacy fallback: raw_data array (index-based, from original CSV import)
        $raw = $household->raw_data;

        if (empty($raw) || !is_array($raw)) {
            return array_fill_keys(array_keys(self::CAPITALS), null);
        }

        foreach (self::CAPITALS as $slug => $meta) {
            $col   = $meta['raw_data_col'];
            $value = $raw[$col] ?? null;

            if ($value === null || $value === '') {
                $scores[$slug] = null;
                continue;
            }

            $x = (float) $value;
            $x = max(1.0, min(4.0, $x));
            $scores[$slug] = round(($x - 1.0) / 3.0 * 100, 4);
        }

        return $scores;
    }

    /**
     * Build summary statistics from the per-capital comparison array.
     *
     * @param  array<string, array{before: float|null, after: float|null, diff: float|null}> $capitals
     * @return array{avg_before: float|null, avg_after: float|null, avg_diff: float|null,
     *               x_before: float|null,   x_after: float|null,   x_diff: float|null,
     *               poverty_level_before: int|null, poverty_level_after: int|null, poverty_level_diff: int|null}
     */
    public function buildSummary(array $capitals): array
    {
        $beforeValues = array_filter(array_column($capitals, 'before'), fn ($v) => $v !== null);
        $afterValues  = array_filter(array_column($capitals, 'after'),  fn ($v) => $v !== null);

        $avgBefore = count($beforeValues) > 0 ? round(array_sum($beforeValues) / count($beforeValues), 4) : null;
        $avgAfter  = count($afterValues) > 0  ? round(array_sum($afterValues)  / count($afterValues), 4)  : null;
        $avgDiff   = ($avgBefore !== null && $avgAfter !== null) ? round($avgAfter - $avgBefore, 4) : null;

        $xBefore = $avgBefore !== null ? round(1.0 + ($avgBefore / 100.0) * 3.0, 4) : null;
        $xAfter  = $avgAfter  !== null ? round(1.0 + ($avgAfter  / 100.0) * 3.0, 4) : null;
        $xDiff   = ($xBefore !== null && $xAfter !== null) ? round($xAfter - $xBefore, 4) : null;

        $levelBefore = $xBefore !== null ? $this->povertyLevel($xBefore) : null;
        $levelAfter  = $xAfter  !== null ? $this->povertyLevel($xAfter)  : null;
        $levelDiff   = ($levelBefore !== null && $levelAfter !== null) ? ($levelAfter - $levelBefore) : null;

        return [
            'avg_before'           => $avgBefore,
            'avg_after'            => $avgAfter,
            'avg_diff'             => $avgDiff,
            'x_before'             => $xBefore,
            'x_after'              => $xAfter,
            'x_diff'               => $xDiff,
            'poverty_level_before' => $levelBefore,
            'poverty_level_after'  => $levelAfter,
            'poverty_level_diff'   => $levelDiff,
        ];
    }

    /**
     * Map aggregate X score (1.0–4.0) to poverty level (1–4).
     */
    public function povertyLevel(float $x): int
    {
        if ($x < 1.75) return 1;
        if ($x < 2.50) return 2;
        if ($x < 3.25) return 3;

        return 4;
    }
}
