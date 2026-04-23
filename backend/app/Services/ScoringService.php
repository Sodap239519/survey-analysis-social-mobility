<?php

namespace App\Services;

use App\Models\Answer;
use App\Models\Capital;
use App\Models\Choice;
use App\Models\Question;
use App\Models\SurveyResponse;
use Illuminate\Support\Collection;

/**
 * ScoringService
 *
 * Handles computation of capital scores, aggregate score, and poverty level.
 *
 * Poverty Level Mapping (X = aggregate score in [1.0, 4.0]):
 *  Level 1: 1.00 <= X < 1.75  (อยู่ลำบาก)
 *  Level 2: 1.75 <= X < 2.50  (อยู่ยาก)
 *  Level 3: 2.50 <= X < 3.25  (อยู่พอได้)
 *  Level 4: 3.25 <= X <= 4.00 (อยู่ดี)
 *
 * Multi-select scoring rule (default):
 *  score_question = min(max_score, sum(weights_of_selected_choices))
 *  If exclusive option selected => 0
 *
 * Question 3 (Human capital skills question, 20 pts):
 *  If "ไม่มี" (is_exclusive) selected => 0
 *  If >= 1 non-exclusive skill selected => 20 (full score)
 *
 * Question 6 (Physical capital, 30 pts) - "ดี=มาก" policy:
 *  If "ไม่มีปัญหา" (choice_key=0) => full 30
 *  If "มีปัญหา" with sub-problems => penalty:
 *    score = max(0, 30 - penalty_per_problem * count(selected_subproblems))
 *    Default penalty_per_problem = 5 (configurable via question meta)
 */
class ScoringService
{
    /**
     * Compute score for a single question based on selected choices.
     *
     * @param Question $question
     * @param array    $selectedChoiceIds  Array of Choice IDs selected
     * @return float
     */
    public function scoreQuestion(Question $question, array $selectedChoiceIds): float
    {
        if (empty($selectedChoiceIds)) {
            return 0.0;
        }

        // Special rule for Q3 (Human capital skills question)
        // Any exclusive "ไม่มี" selected => 0; any non-exclusive skill selected => full score
        if ($question->question_key === 'Q3') {
            $choices = $question->choices()->whereIn('id', $selectedChoiceIds)->get();
            if ($choices->where('is_exclusive', true)->isNotEmpty()) {
                return 0.0;
            }
            // Return full score only when at least one valid non-exclusive choice is confirmed
            if ($choices->where('is_exclusive', false)->isEmpty()) {
                return 0.0;
            }
            return (float) $question->max_score;
        }

        // Special rule for Q6 (Physical capital land-problem question)
        if ($question->type === 'special_q6') {
            return $this->scoreQ6($question, $selectedChoiceIds);
        }

        // Special rule for Q12.1 (Natural capital disaster question)
        if ($question->type === 'special_q12') {
            return $this->scoreQ12($question, $selectedChoiceIds);
        }

        $choices = $question->choices()->whereIn('id', $selectedChoiceIds)->get();

        // If any exclusive choice is selected, score is 0
        if ($choices->where('is_exclusive', true)->isNotEmpty()) {
            return 0.0;
        }

        $total = $choices->sum('weight');

        return min((float) $question->max_score, $total);
    }

    /**
     * Special scoring for Question 6: ปัญหาเกี่ยวกับพื้นที่ทำกิน
     *
     * Choice key "0" means "ไม่มีปัญหา" => full score
     * Sub-problem choices (1.1, 1.2, ...) each apply a penalty.
     * Penalty per problem defaults to 5 (stored in question.meta.penalty_per_problem).
     */
    public function scoreQ6(Question $question, array $selectedChoiceIds): float
    {
        $maxScore = (float) $question->max_score;
        $choices = $question->choices()->whereIn('id', $selectedChoiceIds)->get();

        // If "ไม่มีปัญหา" (is_exclusive / choice_key=0) selected => full score
        if ($choices->where('is_exclusive', true)->isNotEmpty()) {
            return $maxScore;
        }

        $meta = $question->meta ?? [];
        $penaltyPerProblem = (float) ($meta['penalty_per_problem'] ?? 5);

        // Filter sub-problem choices (not the parent "1) มีปัญหา" choice, only sub-items)
        $subProblemChoices = $choices->filter(function (Choice $c) {
            // Sub-problems have choice_key like 1.1, 1.2, etc.
            return str_contains((string) $c->choice_key, '.');
        });

        $count = $subProblemChoices->count();
        $score = max(0.0, $maxScore - $penaltyPerProblem * $count);

        return $score;
    }

    /**
     * Special scoring for Q12.1: ครัวเรือนประสบภัยพิบัติหรือไม่
     *
     * Choice key "0" means "ไม่ประสบ" (is_exclusive) => full 40 pts
     * Choice key "1" means "ประสบ" (parent) => 20 pts
     * Choice keys "1.อุทกภัย" etc. are sub-type informational choices => 0 pts each
     *
     * Score = weight of the "0" or "1" parent choice selected.
     */
    public function scoreQ12(Question $question, array $selectedChoiceIds): float
    {
        $maxScore = (float) $question->max_score;
        $choices  = $question->choices()->whereIn('id', $selectedChoiceIds)->get();

        // "ไม่ประสบ" (is_exclusive) selected => full score
        if ($choices->where('is_exclusive', true)->isNotEmpty()) {
            return $maxScore;
        }

        // Look for parent "ประสบ" choice (choice_key = "1")
        $parentChoice = $choices->first(fn ($c) => (string) $c->choice_key === '1');
        if ($parentChoice) {
            return (float) $parentChoice->weight;
        }

        return 0.0;
    }

    /**
     * Normalize a raw capital score to 0-100 range.
     *
     * @param float $rawScore   Actual earned score
     * @param float $maxScore   Maximum possible score for this capital
     * @return float            Normalized score 0-100
     */
    public function normalizeScore(float $rawScore, float $maxScore): float
    {
        if ($maxScore <= 0) {
            return 0.0;
        }

        return round(($rawScore / $maxScore) * 100, 4);
    }

    /**
     * Compute aggregate score X in [1.0, 4.0] from five normalized capital scores.
     *
     * Formula: X = 1.0 + (average_normalized / 100) * 3.0
     *
     * @param array $normalizedScores  ['human'=>75, 'physical'=>60, ...]
     * @return float
     */
    public function computeAggregateScore(array $normalizedScores): float
    {
        $capitals = ['human', 'physical', 'financial', 'natural', 'social'];
        $values = [];

        foreach ($capitals as $cap) {
            if (isset($normalizedScores[$cap])) {
                $values[] = (float) $normalizedScores[$cap];
            }
        }

        if (empty($values)) {
            return 1.0;
        }

        $avg = array_sum($values) / count($values);

        return round(1.0 + ($avg / 100.0) * 3.0, 4);
    }

    /**
     * Map aggregate score X to poverty level (1-4).
     *
     * Level 1: 1.00 <= X < 1.75  (อยู่ลำบาก)
     * Level 2: 1.75 <= X < 2.50  (อยู่ยาก)
     * Level 3: 2.50 <= X < 3.25  (อยู่พอได้)
     * Level 4: 3.25 <= X <= 4.00 (อยู่ดี)
     */
    public function getPovertyLevel(float $aggregateScore): int
    {
        if ($aggregateScore < 1.75) {
            return 1;
        }
        if ($aggregateScore < 2.50) {
            return 2;
        }
        if ($aggregateScore < 3.25) {
            return 3;
        }

        return 4;
    }

    /**
     * Return the Thai label for a poverty level (1–4).
     *
     * 1 → อยู่ลำบาก
     * 2 → อยู่ยาก
     * 3 → อยู่พอได้
     * 4 → อยู่ดี
     */
    public function getPovertyLevelLabel(int $level): string
    {
        return match ($level) {
            1 => 'อยู่ลำบาก',
            2 => 'อยู่ยาก',
            3 => 'อยู่พอได้',
            4 => 'อยู่ดี',
            default => '',
        };
    }

    /**
     * Compute and persist scores for a SurveyResponse.
     * Loads all answers with choices, computes per-question scores,
     * aggregates per capital, normalizes, then computes aggregate X + poverty level.
     */
    public function computeAndSave(SurveyResponse $response): SurveyResponse
    {
        $capitals = Capital::with(['questions.choices'])->orderBy('sort_order')->get();

        $capitalScores = [];
        $capitalMaxes = [];

        foreach ($capitals as $capital) {
            $rawScore = 0.0;
            $maxScore = 0.0;

            foreach ($capital->questions as $question) {
                $answer = $response->answers()->where('question_id', $question->id)->first();

                if ($answer) {
                    $selected = $answer->selected_choice_ids ?? [];
                    $qScore = $this->scoreQuestion($question, $selected);
                    $answer->update(['score' => $qScore]);
                    $rawScore += $qScore;
                }

                $maxScore += $question->max_score;
            }

            $capitalScores[$capital->slug] = $this->normalizeScore($rawScore, $maxScore);
            $capitalMaxes[$capital->slug] = $maxScore;
        }

        $aggregateX = $this->computeAggregateScore($capitalScores);
        $povertyLevel = $this->getPovertyLevel($aggregateX);

        $response->update([
            'score_human'     => $capitalScores['human']     ?? null,
            'score_physical'  => $capitalScores['physical']  ?? null,
            'score_financial' => $capitalScores['financial'] ?? null,
            'score_natural'   => $capitalScores['natural']   ?? null,
            'score_social'    => $capitalScores['social']    ?? null,
            'score_aggregate' => $aggregateX,
            'poverty_level'   => $povertyLevel,
        ]);

        return $response->fresh();
    }
}
