<?php

use App\Models\Choice;
use App\Models\Question;
use Illuminate\Database\Migrations\Migration;

/**
 * Updates Q4.1 (แหล่งรายได้ที่เพิ่มขึ้น) to use a table-style UI.
 *
 * Changes:
 *  - type: multi_select → special_q41 (triggers table template in the frontend)
 *  - meta: adds income_table=true and structured source/range definitions
 *  - Choices: re-keyed from sequential (1-18) to "{source}_{range}" format
 *    Source: 1=การแปรรูปผลผลิต, 2=การผลิตสินค้า/ผลิตภัณฑ์, 3=ค้าขาย/จำหน่ายสินค้า,
 *            4=รับจ้าง (ทั่วไป/เกษตร), 5=เกษตรกรรม (พืช/สัตว์/ประมง), 6=อื่นๆ
 *    Range: 1=0-1,000 บาท, 2=1,000-3,000 บาท, 3=>3,000 บาท
 *
 * UI behaviour (table per source):
 *  - Each row = one income source; per row only ONE range radio can be selected.
 *  - The "other" text field appears when source 6 (อื่นๆ) row is activated.
 */
return new class extends Migration
{
    // Income sources in order
    private array $SOURCES = [
        1 => 'การแปรรูปผลผลิต',
        2 => 'การผลิตสินค้า/ผลิตภัณฑ์',
        3 => 'ค้าขาย/จำหน่ายสินค้า',
        4 => 'รับจ้าง (ทั่วไป/เกษตร)',
        5 => 'เกษตรกรรม (พืช/สัตว์/ประมง)',
        6 => 'อื่นๆ',
    ];

    // Amount ranges in order
    private array $RANGES = [
        1 => '0-1,000 บาท',
        2 => '1,000-3,000 บาท',
        3 => 'มากกว่า 3,000 บาท',
    ];

    public function up(): void
    {
        $q41 = Question::where('question_key', 'Q4.1')->first();
        if (!$q41) return;

        // Change type and update meta
        $q41->update([
            'type' => 'special_q41',
            'meta' => [
                'income_table' => true,
                'sources' => $this->SOURCES,
                'ranges'  => $this->RANGES,
            ],
        ]);

        // Remove old sequential-key choices and replace with source_range keys
        $q41->choices()->delete();

        $order = 0;
        foreach ($this->SOURCES as $sourceId => $sourceName) {
            foreach ($this->RANGES as $rangeId => $rangeName) {
                Choice::create([
                    'question_id'  => $q41->id,
                    'choice_key'   => "{$sourceId}_{$rangeId}",
                    'text_th'      => "{$sourceName} — {$rangeName}",
                    'weight'       => 0,
                    'is_exclusive' => false,
                    'sort_order'   => $order++,
                ]);
            }
        }
    }

    public function down(): void
    {
        $q41 = Question::where('question_key', 'Q4.1')->first();
        if (!$q41) return;

        // Revert to multi_select with original sequential choices
        $q41->update(['type' => 'multi_select', 'meta' => ['sub_questions' => true]]);
        $q41->choices()->delete();

        $original = [
            ['1',  'การแปรรูปผลผลิต (0-1,000 บาท)',        0],
            ['2',  'การแปรรูปผลผลิต (1,000-3,000 บาท)',     0],
            ['3',  'การแปรรูปผลผลิต (มากกว่า 3,000 บาท)',   0],
            ['4',  'การผลิตสินค้า/ผลิตภัณฑ์ (0-1,000 บาท)', 0],
            ['5',  'การผลิตสินค้า/ผลิตภัณฑ์ (1,000-3,000 บาท)', 0],
            ['6',  'การผลิตสินค้า/ผลิตภัณฑ์ (มากกว่า 3,000 บาท)', 0],
            ['7',  'ค้าขาย/จำหน่ายสินค้า (0-1,000 บาท)',   0],
            ['8',  'ค้าขาย/จำหน่ายสินค้า (1,000-3,000 บาท)', 0],
            ['9',  'ค้าขาย/จำหน่ายสินค้า (มากกว่า 3,000 บาท)', 0],
            ['10', 'รับจ้าง (0-1,000 บาท)',                 0],
            ['11', 'รับจ้าง (1,000-3,000 บาท)',             0],
            ['12', 'รับจ้าง (มากกว่า 3,000 บาท)',           0],
            ['13', 'เกษตรกรรม (0-1,000 บาท)',              0],
            ['14', 'เกษตรกรรม (1,000-3,000 บาท)',          0],
            ['15', 'เกษตรกรรม (มากกว่า 3,000 บาท)',        0],
            ['16', 'อื่นๆ (0-1,000 บาท)',                  0],
            ['17', 'อื่นๆ (1,000-3,000 บาท)',              0],
            ['18', 'อื่นๆ (มากกว่า 3,000 บาท)',            0],
        ];
        foreach ($original as $i => [$key, $text, $weight]) {
            Choice::create([
                'question_id'  => $q41->id,
                'choice_key'   => $key,
                'text_th'      => $text,
                'weight'       => $weight,
                'is_exclusive' => false,
                'sort_order'   => $i,
            ]);
        }
    }
};
