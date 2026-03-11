<?php

namespace App\Services;

use App\Models\Household;
use App\Models\SurveyResponse;

/**
 * CompareHouseholdSurveyLogic
 *
 * Compares Before (legacy XLSX/CSV import) vs After (new survey responses) capital
 * scores for a given household, matched by house_code (11-digit รหัสบ้าน).
 *
 * ─── Before scores ──────────────────────────────────────────────────────────
 * Priority order:
 *  1. Household.baseline_score_* columns (set by MultiSheetHouseholdImport from
 *     the "ข้อมูลพื้นฐาน" sheet baseline columns; X scale 1–4).
 *  2. SurveyResponse with period='before' (score_* columns, 0–100 scale).
 *  3. Fallback: Household.raw_data array (legacy CSV index-based, X scale 1–4).
 *
 * ─── After scores ───────────────────────────────────────────────────────────
 * All SurveyResponse records with period='after' for the household are collected.
 * Each represents one respondent (person) in the household.
 * Scores are AVERAGED across all respondents to produce household-level scores.
 * This reflects requirement B3: "คำนวณระดับรายคน แล้วรวม/เฉลี่ยเป็นรายครัวเรือน".
 *
 * ─── Comparison criteria (ดีขึ้น / คงที่ / แย่ลง) ──────────────────────────
 * Applied per capital (diff = after_score − before_score, both on 0–100 scale):
 *   ดีขึ้น  (improved):  diff >  +2.0 points
 *   คงที่   (unchanged): |diff| ≤ 2.0 points
 *   แย่ลง   (decreased): diff < -2.0 points
 *
 * The ±2-point threshold (2% of 100-point scale) avoids flagging minor rounding
 * differences as real changes.  This constant is COMPARISON_THRESHOLD below.
 *
 * ─── Capital slug → legacy CSV column mapping (raw_data fallback) ───────────
 *   human     => 44  (ทุนมนุษย์)
 *   physical  => 55  (ทุนกายภาพ)
 *   financial => 69  (ทุนการเงิน)
 *   natural   => 78  (ทุนธรรมชาติ)
 *   social    => 87  (ทุนทางสังคม)
 *
 * ─── X index (poverty level 1–4) ────────────────────────────────────────────
 *   X = 1.0 + (avg_normalized / 100) * 3.0
 *   Level 1: 1.00 ≤ X < 1.75  (อยู่ลำบาก)
 *   Level 2: 1.75 ≤ X < 2.50  (อยู่ยาก)
 *   Level 3: 2.50 ≤ X < 3.25  (อยู่พอได้)
 *   Level 4: 3.25 ≤ X ≤ 4.00  (อยู่ดี)
 */
class CompareHouseholdSurveyLogic
{
    /**
     * Threshold (in 0–100 scale points) for ดีขึ้น / คงที่ / แย่ลง comparison.
     * diff > THRESHOLD  => ดีขึ้น
     * |diff| <= THRESHOLD => คงที่
     * diff < -THRESHOLD => แย่ลง
     */
    public const COMPARISON_THRESHOLD = 2.0;

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
     * After scores are averaged across all respondents (persons) with the same
     * house_code who have a period='after' SurveyResponse record.
     *
     * Per-capital change label (see COMPARISON_THRESHOLD):
     *   diff >  THRESHOLD  => 'ดีขึ้น'
     *   |diff| ≤ THRESHOLD => 'คงที่'
     *   diff < -THRESHOLD  => 'แย่ลง'
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

            $trend = null;
            if ($diff !== null) {
                if ($diff > self::COMPARISON_THRESHOLD) {
                    $trend = 'ดีขึ้น';
                } elseif ($diff < -self::COMPARISON_THRESHOLD) {
                    $trend = 'แย่ลง';
                } else {
                    $trend = 'คงที่';
                }
            }

            $capitals[$slug] = [
                'label'  => $label,
                'before' => $before,
                'after'  => $after,
                'diff'   => $diff,
                'trend'  => $trend,
            ];
        }

        $summary = $this->buildSummary($capitals);

        return [
            'household_id'      => $household->id,
            'house_code'        => $household->house_code,
            'before_source'     => $beforeScores['source'],
            'after_found'       => $afterScores['found'],
            'respondent_count'  => $afterScores['respondent_count'] ?? 0,
            'capitals'          => $capitals,
            'summary'           => $summary,
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
     * Retrieve After capital scores (0–100 normalized) by averaging ALL
     * SurveyResponse records with period='after' for the household.
     *
     * Averaging reflects requirement B3: "คำนวณระดับรายคน แล้วรวม/เฉลี่ยเป็นรายครัวเรือน
     * โดยเอาคนที่ house_code เดียวกัน".
     *
     * @return array{found: bool, scores: array<string, float|null>, respondent_count: int}
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

        $responses = $query->get();

        if ($responses->isEmpty()) {
            return [
                'found'            => false,
                'scores'           => array_fill_keys(array_keys(self::CAPITALS), null),
                'respondent_count' => 0,
            ];
        }

        // Average per-capital scores across all respondents in this household
        $totals  = array_fill_keys(array_keys(self::CAPITALS), 0.0);
        $counts  = array_fill_keys(array_keys(self::CAPITALS), 0);

        foreach ($responses as $response) {
            foreach (self::CAPITALS as $slug => $meta) {
                $value = $response->{$meta['score_field']};
                if ($value !== null) {
                    $totals[$slug] += (float) $value;
                    $counts[$slug]++;
                }
            }
        }

        $scores = [];
        foreach (array_keys(self::CAPITALS) as $slug) {
            $scores[$slug] = $counts[$slug] > 0
                ? round($totals[$slug] / $counts[$slug], 4)
                : null;
        }

        return [
            'found'            => true,
            'scores'           => $scores,
            'respondent_count' => $responses->count(),
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

        // Overall household trend based on avg_diff and COMPARISON_THRESHOLD
        $overallTrend = null;
        if ($avgDiff !== null) {
            if ($avgDiff > self::COMPARISON_THRESHOLD) {
                $overallTrend = 'ดีขึ้น';
            } elseif ($avgDiff < -self::COMPARISON_THRESHOLD) {
                $overallTrend = 'แย่ลง';
            } else {
                $overallTrend = 'คงที่';
            }
        }

        return [
            'avg_before'           => $avgBefore,
            'avg_after'            => $avgAfter,
            'avg_diff'             => $avgDiff,
            'overall_trend'        => $overallTrend,
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
