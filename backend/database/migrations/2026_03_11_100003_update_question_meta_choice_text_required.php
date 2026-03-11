<?php

use App\Models\Question;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds choice_text_required metadata to questions whose specific choices require
 * an inline free-text field (the "ระบุ..." field on the paper form).
 *
 * choice_text_required is an array of choice_key strings.  When a choice whose key
 * is in this array is selected, the frontend must show a required text input.
 *
 * Questions updated:
 *  Q2  (สถานภาพการทำงาน, single_select)
 *      choice_key '1' = ว่างงาน → "สาเหตุ..." required text
 *
 *  Q2.1 (อาชีพปัจจุบัน, multi_select, conditional on Q2=2)
 *      choice_key '9' = ธุรกิจส่วนตัว/งานบริการ → "โปรดระบุ..." required text
 *      choice_key '10' = อื่นๆ → "ระบุ..." required text
 */
return new class extends Migration
{
    public function up(): void
    {
        // Q2: add choice_text_required for ว่างงาน (key '1')
        $q2 = Question::where('question_key', 'Q2')->first();
        if ($q2) {
            $meta = (array) ($q2->meta ?? []);
            $meta['choice_text_required'] = ['1'];
            $q2->update(['meta' => $meta]);
        }

        // Q2.1: keep existing conditional meta, add choice_text_required for keys '9' and '10'
        $q21 = Question::where('question_key', 'Q2.1')->first();
        if ($q21) {
            $meta = (array) ($q21->meta ?? []);
            $meta['choice_text_required'] = ['9', '10'];
            $q21->update(['meta' => $meta]);
        }
    }

    public function down(): void
    {
        foreach (['Q2', 'Q2.1'] as $key) {
            $q = Question::where('question_key', $key)->first();
            if (!$q) continue;
            $meta = (array) ($q->meta ?? []);
            unset($meta['choice_text_required']);
            $q->update(['meta' => $meta]);
        }
    }
};
