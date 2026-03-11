<?php

use App\Models\Question;
use App\Models\Choice;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds missing questionnaire items required by the paper form:
 *  - Q2.0: สาเหตุที่ไม่ทำงาน (conditional sub-question when Q2 = "ไม่ทำงาน")
 *  - Q12.1 disaster type sub-choices (อุทกภัย / วาตภัย / ภัยแล้ง / อัคคีภัย / โรคระบาด / อื่นๆ)
 *    displayed as sub-checkboxes when "ประสบ" is selected.
 *
 * The migration also updates Q12.1 to use the special_q12 type so that
 * the UI can render it like Q6 (parent + sub-choices pattern).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Q2.0: สาเหตุที่ไม่ทำงาน ──────────────────────────────────────────
        // Shown only when Q2 = "0" (ไม่ทำงาน). Not scored (max_score = 0).
        $q2 = Question::where('question_key', 'Q2')->first();
        if ($q2 && ! Question::where('question_key', 'Q2.0')->exists()) {
            $q20 = Question::create([
                'capital_id'           => $q2->capital_id,
                'question_key'         => 'Q2.0',
                'text_th'              => 'สาเหตุที่ไม่ทำงาน (เลือกได้มากกว่า 1)',
                'type'                 => 'multi_select',
                'max_score'            => 0,
                'has_exclusive_option' => false,
                'meta'                 => ['conditional_on' => 'Q2', 'conditional_value' => '0', 'required_when_visible' => true],
                'sort_order'           => 10, // between Q2 (sort=1) and Q2.1 (sort=15)
            ]);

            $reasons = [
                ['1', 'ชรา',              0, false],
                ['2', 'พิการ',            0, false],
                ['3', 'เจ็บป่วย',         0, false],
                ['4', 'เด็กเล็ก/นักเรียน', 0, false],
                ['5', 'อื่นๆ',            0, false],
            ];
            foreach ($reasons as $i => [$key, $text, $weight, $exclusive]) {
                Choice::create([
                    'question_id'  => $q20->id,
                    'choice_key'   => $key,
                    'text_th'      => $text,
                    'weight'       => $weight,
                    'is_exclusive' => $exclusive,
                    'sort_order'   => $i,
                ]);
            }
        }

        // ── Q12.1 update: change to special_q12 type and restructure choices ─
        // Restructure choices to: 0=ไม่ประสบ (exclusive/full-score), 1=ประสบ, 1.x=disaster sub-types
        $q121 = Question::where('question_key', 'Q12.1')->first();
        if ($q121) {
            // Change type to special_q12 for special rendering/scoring
            $q121->update(['type' => 'special_q12']);

            // Remove old choices and replace with parent+sub pattern
            $q121->choices()->delete();

            $choices = [
                ['0',         'ไม่ประสบ',   40, true,  0],
                ['1',         'ประสบ',      20, false, 1],
                ['1.อุทกภัย', 'อุทกภัย',    0,  false, 2],
                ['1.วาตภัย',  'วาตภัย',     0,  false, 3],
                ['1.ภัยแล้ง', 'ภัยแล้ง',    0,  false, 4],
                ['1.อัคคีภัย','อัคคีภัย',   0,  false, 5],
                ['1.โรคระบาด','โรคระบาด',   0,  false, 6],
                ['1.อื่นๆ',   'อื่นๆ',      0,  false, 7],
            ];
            foreach ($choices as [$key, $text, $weight, $exclusive, $order]) {
                Choice::create([
                    'question_id'  => $q121->id,
                    'choice_key'   => $key,
                    'text_th'      => $text,
                    'weight'       => $weight,
                    'is_exclusive' => $exclusive,
                    'sort_order'   => $order,
                ]);
            }
        }
    }

    public function down(): void
    {
        Question::where('question_key', 'Q2.0')->delete();

        // Restore Q12.1 to single_select with original choices
        $q121 = Question::where('question_key', 'Q12.1')->first();
        if ($q121) {
            $q121->update(['type' => 'single_select']);
            $q121->choices()->delete();

            $original = [
                ['0', 'ไม่ประสบ',        40, false, 0],
                ['1', 'ประสบ - อุทกภัย', 20, false, 1],
                ['2', 'ประสบ - วาตภัย',  20, false, 2],
                ['3', 'ประสบ - ภัยแล้ง', 20, false, 3],
                ['4', 'ประสบ - อัคคีภัย',20, false, 4],
                ['5', 'ประสบ - โรคระบาด',20, false, 5],
                ['6', 'ประสบ - อื่นๆ',   20, false, 6],
            ];
            foreach ($original as [$key, $text, $weight, $exclusive, $order]) {
                Choice::create([
                    'question_id'  => $q121->id,
                    'choice_key'   => $key,
                    'text_th'      => $text,
                    'weight'       => $weight,
                    'is_exclusive' => $exclusive,
                    'sort_order'   => $order,
                ]);
            }
        }
    }
};
