<?php

use App\Models\Capital;
use App\Models\Choice;
use App\Models\Question;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds the missing questions required by the 18-question paper survey:
 *  - Q2.1: ประเภทอาชีพ (conditional on Q2 = "ทำงาน")
 *  - Q4.1: แหล่งรายได้ที่เพิ่มขึ้น (income sources after project, ข้อ 6)
 *  - Q10.1: รายจ่ายครัวเรือน 11 รายการ (detailed household expenses, ข้อ 10)
 *  - Q10.2: การดำเนินการเกี่ยวกับหนี้ (debt management actions, ข้อ 13)
 *  - Q15: ความพึงพอใจต่อโครงการ 5 ด้าน (satisfaction, ข้อ 18)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Ensure capital records exist before adding questions that depend on them.
        // (Capitals are normally seeded via QuestionnaireSeeder, but migrations run
        //  before seeders, so we create them here with firstOrCreate as well.)
        $this->ensureCapitals();

        // Q2.1: อาชีพที่ประกอบปัจจุบัน (multi_select, conditional on Q2=2)
        $human = Capital::where('slug', 'human')->first();
        if ($human && ! Question::where('question_key', 'Q2.1')->exists()) {
            $q21 = Question::create([
                'capital_id'           => $human->id,
                'question_key'         => 'Q2.1',
                'text_th'              => 'อาชีพที่ประกอบปัจจุบัน (ตอบได้มากกว่า 1)',
                'type'                 => 'multi_select',
                'max_score'            => 0,
                'has_exclusive_option' => false,
                'meta'                 => ['conditional_on' => 'Q2', 'conditional_value' => '2'],
                'sort_order'           => 15,
            ]);

            $choices21 = [
                ['1',  'พืชเกษตร', 0],
                ['2',  'ประมง', 0],
                ['3',  'ปศุสัตว์', 0],
                ['4',  'รับจ้างภาคการเกษตร', 0],
                ['5',  'รับจ้างทั่วไปนอกภาคการเกษตร (รายวัน)', 0],
                ['6',  'ลูกจ้าง บ.เอกชน โรงงาน โรงแรม ห้างร้าน', 0],
                ['7',  'ลูกจ้างหน่วยงานภาครัฐ/รัฐวิสาหกิจ', 0],
                ['8',  'รับราชการ/พนักงานรัฐ', 0],
                ['9',  'ธุรกิจส่วนตัว/งานบริการ', 0],
                ['10', 'อื่นๆ', 0],
            ];
            foreach ($choices21 as $i => [$key, $text, $weight]) {
                Choice::create([
                    'question_id' => $q21->id,
                    'choice_key'  => $key,
                    'text_th'     => $text,
                    'weight'      => $weight,
                    'is_exclusive' => false,
                    'sort_order'  => $i,
                ]);
            }
        }

        // Q4.1: แหล่งรายได้ที่เพิ่มขึ้นจากการเข้าร่วมโครงการ (ข้อ 6 ในแบบฟอร์ม)
        if ($human && ! Question::where('question_key', 'Q4.1')->exists()) {
            $q41 = Question::create([
                'capital_id'           => $human->id,
                'question_key'         => 'Q4.1',
                'text_th'              => 'แหล่งรายได้ที่เพิ่มขึ้นจากการเข้าร่วมโครงการ',
                'type'                 => 'multi_select',
                'max_score'            => 0,
                'has_exclusive_option' => false,
                'meta'                 => ['sub_questions' => true],
                'sort_order'           => 16,
            ]);

            $choices41 = [
                ['1', 'การแปรรูปผลผลิต (0-1,000 บาท)', 0],
                ['2', 'การแปรรูปผลผลิต (1,000-3,000 บาท)', 0],
                ['3', 'การแปรรูปผลผลิต (มากกว่า 3,000 บาท)', 0],
                ['4', 'การผลิตสินค้า/ผลิตภัณฑ์ (0-1,000 บาท)', 0],
                ['5', 'การผลิตสินค้า/ผลิตภัณฑ์ (1,000-3,000 บาท)', 0],
                ['6', 'การผลิตสินค้า/ผลิตภัณฑ์ (มากกว่า 3,000 บาท)', 0],
                ['7', 'ค้าขาย/จำหน่ายสินค้า (0-1,000 บาท)', 0],
                ['8', 'ค้าขาย/จำหน่ายสินค้า (1,000-3,000 บาท)', 0],
                ['9', 'ค้าขาย/จำหน่ายสินค้า (มากกว่า 3,000 บาท)', 0],
                ['10', 'รับจ้าง (0-1,000 บาท)', 0],
                ['11', 'รับจ้าง (1,000-3,000 บาท)', 0],
                ['12', 'รับจ้าง (มากกว่า 3,000 บาท)', 0],
                ['13', 'เกษตรกรรม (0-1,000 บาท)', 0],
                ['14', 'เกษตรกรรม (1,000-3,000 บาท)', 0],
                ['15', 'เกษตรกรรม (มากกว่า 3,000 บาท)', 0],
                ['16', 'อื่นๆ (0-1,000 บาท)', 0],
                ['17', 'อื่นๆ (1,000-3,000 บาท)', 0],
                ['18', 'อื่นๆ (มากกว่า 3,000 บาท)', 0],
            ];
            foreach ($choices41 as $i => [$key, $text, $weight]) {
                Choice::create([
                    'question_id' => $q41->id,
                    'choice_key'  => $key,
                    'text_th'     => $text,
                    'weight'      => $weight,
                    'is_exclusive' => false,
                    'sort_order'  => $i,
                ]);
            }
        }

        // Q10.1: การดำเนินการเกี่ยวกับหนี้ (ข้อ 13 ในแบบฟอร์ม)
        $financial = Capital::where('slug', 'financial')->first();
        if ($financial && ! Question::where('question_key', 'Q10.1')->exists()) {
            $q101 = Question::create([
                'capital_id'           => $financial->id,
                'question_key'         => 'Q10.1',
                'text_th'              => 'ในช่วงหลังเข้าร่วมโครงการ ครัวเรือนมีการดำเนินการอย่างใดอย่างหนึ่งต่อไปนี้หรือไม่',
                'type'                 => 'multi_select',
                'max_score'            => 0,
                'has_exclusive_option' => false,
                'meta'                 => null,
                'sort_order'           => 55,
            ]);

            $choices101 = [
                ['1', 'เจรจาปรับโครงสร้างหนี้', 0],
                ['2', 'วางแผนการชำระหนี้เป็นระบบมากขึ้น', 0],
                ['3', 'รวมหนี้/ย้ายหนี้', 0],
                ['4', 'หลีกเลี่ยงการก่อหนี้ใหม่ที่ไม่จำเป็น', 0],
                ['5', 'ยังไม่มีการเปลี่ยนแปลง', 0],
                ['6', 'อื่น ๆ', 0],
            ];
            foreach ($choices101 as $i => [$key, $text, $weight]) {
                Choice::create([
                    'question_id' => $q101->id,
                    'choice_key'  => $key,
                    'text_th'     => $text,
                    'weight'      => $weight,
                    'is_exclusive' => false,
                    'sort_order'  => $i,
                ]);
            }
        }

        // Q15: ความพึงพอใจต่อโครงการโดยรวม 5 ด้าน (ข้อ 18 ในแบบฟอร์ม)
        $social = Capital::where('slug', 'social')->first();
        if ($social && ! Question::where('question_key', 'Q15')->exists()) {
            $q15 = Question::create([
                'capital_id'           => $social->id,
                'question_key'         => 'Q15',
                'text_th'              => 'ระดับความพึงพอใจต่อโครงการโดยรวม',
                'type'                 => 'multi_select',
                'max_score'            => 0,
                'has_exclusive_option' => false,
                'meta'                 => ['aspects' => true],
                'sort_order'           => 30,
            ]);

            $choices15 = [
                ['1_5', 'กระบวนการ/กิจกรรมของโครงการ: มากที่สุด', 0],
                ['1_4', 'กระบวนการ/กิจกรรมของโครงการ: มาก', 0],
                ['1_3', 'กระบวนการ/กิจกรรมของโครงการ: ปานกลาง', 0],
                ['1_2', 'กระบวนการ/กิจกรรมของโครงการ: น้อย', 0],
                ['1_1', 'กระบวนการ/กิจกรรมของโครงการ: น้อยที่สุด', 0],
                ['2_5', 'องค์ความรู้/ทักษะที่ได้รับ: มากที่สุด', 0],
                ['2_4', 'องค์ความรู้/ทักษะที่ได้รับ: มาก', 0],
                ['2_3', 'องค์ความรู้/ทักษะที่ได้รับ: ปานกลาง', 0],
                ['2_2', 'องค์ความรู้/ทักษะที่ได้รับ: น้อย', 0],
                ['2_1', 'องค์ความรู้/ทักษะที่ได้รับ: น้อยที่สุด', 0],
                ['3_5', 'การนำไปใช้ประโยชน์ได้จริง: มากที่สุด', 0],
                ['3_4', 'การนำไปใช้ประโยชน์ได้จริง: มาก', 0],
                ['3_3', 'การนำไปใช้ประโยชน์ได้จริง: ปานกลาง', 0],
                ['3_2', 'การนำไปใช้ประโยชน์ได้จริง: น้อย', 0],
                ['3_1', 'การนำไปใช้ประโยชน์ได้จริง: น้อยที่สุด', 0],
                ['4_5', 'การติดตามและสนับสนุนจากทีมงาน: มากที่สุด', 0],
                ['4_4', 'การติดตามและสนับสนุนจากทีมงาน: มาก', 0],
                ['4_3', 'การติดตามและสนับสนุนจากทีมงาน: ปานกลาง', 0],
                ['4_2', 'การติดตามและสนับสนุนจากทีมงาน: น้อย', 0],
                ['4_1', 'การติดตามและสนับสนุนจากทีมงาน: น้อยที่สุด', 0],
                ['5_5', 'การเปลี่ยนแปลงคุณภาพชีวิตโดยรวม: มากที่สุด', 0],
                ['5_4', 'การเปลี่ยนแปลงคุณภาพชีวิตโดยรวม: มาก', 0],
                ['5_3', 'การเปลี่ยนแปลงคุณภาพชีวิตโดยรวม: ปานกลาง', 0],
                ['5_2', 'การเปลี่ยนแปลงคุณภาพชีวิตโดยรวม: น้อย', 0],
                ['5_1', 'การเปลี่ยนแปลงคุณภาพชีวิตโดยรวม: น้อยที่สุด', 0],
            ];
            foreach ($choices15 as $i => [$key, $text, $weight]) {
                Choice::create([
                    'question_id' => $q15->id,
                    'choice_key'  => $key,
                    'text_th'     => $text,
                    'weight'      => $weight,
                    'is_exclusive' => false,
                    'sort_order'  => $i,
                ]);
            }
        }
    }

    public function down(): void
    {
        Question::whereIn('question_key', ['Q2.1', 'Q4.1', 'Q10.1', 'Q15'])->delete();
    }

    /**
     * Ensure all five Capital records exist.
     * This mirrors QuestionnaireSeeder so the migration is self-contained
     * and works regardless of whether the seeder has already run.
     */
    private function ensureCapitals(): void
    {
        $capitals = [
            ['slug' => 'human',    'name_th' => 'ทุนมนุษย์',              'name_en' => 'Human Capital',    'max_score' => 100, 'sort_order' => 1],
            ['slug' => 'physical', 'name_th' => 'ทุนกายภาพ',              'name_en' => 'Physical Capital', 'max_score' => 100, 'sort_order' => 2],
            ['slug' => 'financial','name_th' => 'ทุนการเงิน',              'name_en' => 'Financial Capital','max_score' => 100, 'sort_order' => 3],
            ['slug' => 'natural',  'name_th' => 'ทุนทรัพยากรธรรมชาติ',   'name_en' => 'Natural Capital',  'max_score' => 100, 'sort_order' => 4],
            ['slug' => 'social',   'name_th' => 'ทุนทางสังคม',            'name_en' => 'Social Capital',   'max_score' => 100, 'sort_order' => 5],
        ];
        foreach ($capitals as $data) {
            Capital::firstOrCreate(['slug' => $data['slug']], $data);
        }
    }
};
