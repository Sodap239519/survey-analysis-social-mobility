<?php

use App\Models\Question;
use App\Models\Choice;
use Illuminate\Database\Migrations\Migration;

/**
 * Updates questionnaire questions to match the paper form structure:
 *  - Q8  → special_q8  type (household expenses table, no single-select)
 *  - Q9  → update choice text to match paper form (ไม่มี / มี)
 *  - Q10 → special_q10 type (debt details with ไม่มี / มี choices)
 *  - Q13 → special_q13 type (social groups with sub-questions)
 *  - Q14 → add choice_text_required meta for choices 1,2,3,5,6
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Q8: change to special_q8, remove choices, set max_score = 0 ─────────
        $q8 = Question::where('question_key', 'Q8')->first();
        if ($q8) {
            $q8->choices()->delete();
            $q8->update(['type' => 'special_q8', 'max_score' => 0]);
        }

        // ── Q9: update choice text to ไม่มี / มี ────────────────────────────────
        $q9 = Question::where('question_key', 'Q9')->first();
        if ($q9) {
            $choice0 = $q9->choices()->where('choice_key', '0')->first();
            if ($choice0) $choice0->update(['text_th' => 'ไม่มี']);

            $choice1 = $q9->choices()->where('choice_key', '1')->first();
            if ($choice1) $choice1->update(['text_th' => 'มี']);
        }

        // ── Q10: change to special_q10, replace choices with ไม่มี / มี ─────────
        $q10 = Question::where('question_key', 'Q10')->first();
        if ($q10) {
            // Delete choices with keys 1, 2, 3
            $q10->choices()->whereIn('choice_key', ['1', '2', '3'])->delete();

            // Rename choice '0' to 'ไม่มี'
            $choice0 = $q10->choices()->where('choice_key', '0')->first();
            if ($choice0) {
                $choice0->update(['text_th' => 'ไม่มี']);
            }

            // Add new choice '1' with text 'มี' weight 0
            if (!$q10->choices()->where('choice_key', '1')->exists()) {
                Choice::create([
                    'question_id' => $q10->id,
                    'choice_key'  => '1',
                    'text_th'     => 'มี',
                    'weight'      => 0,
                    'is_exclusive'=> false,
                    'sort_order'  => 1,
                ]);
            }

            $q10->update(['type' => 'special_q10', 'max_score' => 0]);
        }

        // ── Q13: change to special_q13 (keep existing choices as-is) ─────────────
        $q13 = Question::where('question_key', 'Q13')->first();
        if ($q13) {
            $q13->update(['type' => 'special_q13']);
        }

        // ── Q14: add choice_text_required meta ────────────────────────────────────
        $q14 = Question::where('question_key', 'Q14')->first();
        if ($q14) {
            $meta = $q14->meta ?? [];
            $meta['choice_text_required'] = ['1', '2', '3', '5', '6'];
            $q14->update(['meta' => $meta]);
        }
    }

    public function down(): void
    {
        // ── Restore Q8 to single_select with original choices ────────────────────
        $q8 = Question::where('question_key', 'Q8')->first();
        if ($q8) {
            $q8->choices()->delete();
            $q8->update(['type' => 'single_select', 'max_score' => 20]);

            $choices = [
                ['1', 'ลดลง',   20, 0],
                ['2', 'เท่าเดิม', 10, 1],
                ['3', 'เพิ่มขึ้น', 5, 2],
            ];
            foreach ($choices as [$key, $text, $weight, $order]) {
                Choice::create([
                    'question_id' => $q8->id,
                    'choice_key'  => $key,
                    'text_th'     => $text,
                    'weight'      => $weight,
                    'is_exclusive'=> false,
                    'sort_order'  => $order,
                ]);
            }
        }

        // ── Restore Q9 choice text ────────────────────────────────────────────────
        $q9 = Question::where('question_key', 'Q9')->first();
        if ($q9) {
            $choice0 = $q9->choices()->where('choice_key', '0')->first();
            if ($choice0) $choice0->update(['text_th' => 'ไม่มีการออม']);

            $choice1 = $q9->choices()->where('choice_key', '1')->first();
            if ($choice1) $choice1->update(['text_th' => 'มีการออม']);
        }

        // ── Restore Q10 to single_select with original choices ────────────────────
        $q10 = Question::where('question_key', 'Q10')->first();
        if ($q10) {
            $q10->choices()->delete();
            $q10->update(['type' => 'single_select', 'max_score' => 20]);

            $choices = [
                ['0', 'ไม่มีหนี้สิน',          20, 0],
                ['1', 'มีหนี้สิน แต่ลดลง',     15, 1],
                ['2', 'เท่าเดิม',              10, 2],
                ['3', 'เพิ่มขึ้น',              5, 3],
            ];
            foreach ($choices as [$key, $text, $weight, $order]) {
                Choice::create([
                    'question_id' => $q10->id,
                    'choice_key'  => $key,
                    'text_th'     => $text,
                    'weight'      => $weight,
                    'is_exclusive'=> false,
                    'sort_order'  => $order,
                ]);
            }
        }

        // ── Restore Q13 to multi_select ───────────────────────────────────────────
        $q13 = Question::where('question_key', 'Q13')->first();
        if ($q13) {
            $q13->update(['type' => 'multi_select']);
        }

        // ── Remove choice_text_required from Q14 meta ─────────────────────────────
        $q14 = Question::where('question_key', 'Q14')->first();
        if ($q14) {
            $meta = $q14->meta ?? [];
            unset($meta['choice_text_required']);
            $q14->update(['meta' => $meta]);
        }
    }
};
