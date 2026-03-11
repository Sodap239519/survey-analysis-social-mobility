<?php

namespace Database\Seeders;

use App\Models\Capital;
use App\Models\Choice;
use App\Models\Question;
use Illuminate\Database\Seeder;

/**
 * Seeds the questionnaire structure from docs/แบบกำกับติดตาม_After.md
 *
 * Capital max scores are derived from question totals:
 *  Human:    Q2(20) + Q3(20) + Q3.1(20) + Q3.2(20) + Q4(20) = 100
 *  Physical: Q5(70) + Q6(30) = 100
 *  Financial: Q7(20) + Q8(20) + Q9(20) + Q10(20) + Q11(20) = 100
 *  Natural:  Q12.1(40) + Q12.2(60) = 100
 *  Social:   Q13(60) + Q14(40) = 100
 *
 * NOTE: Choice weights are placeholder values.
 *       TODO: Confirm actual weights with domain experts / stakeholders.
 */
class QuestionnaireSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedHuman();
        $this->seedPhysical();
        $this->seedFinancial();
        $this->seedNatural();
        $this->seedSocial();
    }

    private function seedHuman(): void
    {
        $capital = Capital::firstOrCreate(
            ['slug' => 'human'],
            ['name_th' => 'ทุนมนุษย์', 'name_en' => 'Human Capital', 'max_score' => 100, 'sort_order' => 1]
        );

        // Q2: สถานภาพการทำงาน (20 pts) - single select
        $q2 = $this->createQuestion($capital, 'Q2', 'สถานภาพการทำงานปัจจุบัน', 'single_select', 20, true, 1);
        $this->createChoices($q2, [
            ['0', 'ไม่ทำงาน', 0, true],
            ['1', 'ว่างงาน (เคยทำแต่ตอนนี้ไม่ได้ทำ)', 5, false],
            ['2', 'ทำงาน', 20, false],
        ]);

        // Q2.0: สาเหตุที่ไม่ทำงาน (conditional on Q2=0, not scored)
        $q20 = $this->createQuestion(
            $capital, 'Q2.0', 'สาเหตุที่ไม่ทำงาน (เลือกได้มากกว่า 1)', 'multi_select', 0, false, 10,
            ['conditional_on' => 'Q2', 'conditional_value' => '0', 'required_when_visible' => true]
        );
        $this->createChoices($q20, [
            ['1', 'ชรา',              0, false],
            ['2', 'พิการ',            0, false],
            ['3', 'เจ็บป่วย',         0, false],
            ['4', 'เด็กเล็ก/นักเรียน', 0, false],
            ['5', 'อื่นๆ',            0, false],
        ]);

        // Q3: ทักษะอาชีพ (20 pts) - multi select
        $q3 = $this->createQuestion($capital, 'Q3', 'ทักษะอาชีพที่สามารถสร้างรายได้ปัจจุบัน', 'multi_select', 20, true, 2);
        $this->createChoices($q3, [
            ['0',  'ไม่มี', 0, true],
            ['1',  'พืชเกษตร', 2, false],
            ['2',  'ประมง', 2, false],
            ['3',  'ปศุสัตว์', 2, false],
            ['4',  'ช่าง (ก่อสร้าง ไฟฟ้า ประปา ซ่อม เสริมสวย)', 2, false],
            ['5',  'ศิลปะ งานฝีมือ หัตถกรรม', 2, false],
            ['6',  'อาหาร ขนม แปรรูป ถนอมอาหาร', 2, false],
            ['7',  'สมุนไพร หัตถเวช', 2, false],
            ['8',  'เทคโนโลยีสารสนเทศ', 2, false],
            ['9',  'พิธีกรรมทางศาสนา', 2, false],
            ['10', 'บริหารธุรกิจ/การตลาด', 2, false],
            ['11', 'อื่นๆ', 2, false],
        ]);

        // Q3.1: การเปลี่ยนแปลงด้านทักษะ หลังเข้าร่วมโครงการ (20 pts)
        $q31 = $this->createQuestion($capital, 'Q3.1', 'การเปลี่ยนแปลงด้านทักษะและความสามารถ หลังเข้าร่วมโครงการ', 'multi_select', 20, true, 3);
        $this->createChoices($q31, [
            ['0', 'ยังไม่เห็นการเปลี่ยนแปลงด้านทักษะหรือความสามารถอย่างชัดเจน', 0, true],
            ['1', 'สามารถจัดการรายรับ–รายจ่ายของครัวเรือนได้เป็นระบบมากขึ้น', 3, false],
            ['2', 'สามารถวางแผนการใช้จ่ายและควบคุมค่าใช้จ่ายที่ไม่จำเป็นได้ดีขึ้น', 3, false],
            ['3', 'เริ่มทำหรือทำบัญชีครัวเรือนได้อย่างสม่ำเสมอ', 3, false],
            ['4', 'มีการวางแผนการออมและออมเงินอย่างต่อเนื่องมากขึ้น', 3, false],
            ['5', 'มีความรู้และความสามารถในการจัดการหนี้ดีขึ้น', 3, false],
            ['6', 'มีความรอบคอบในการตัดสินใจด้านการกู้ยืม', 3, false],
            ['7', 'สามารถวางแผนทางการเงินเพื่อรองรับความเสี่ยงได้ดีขึ้น', 2, false],
            ['8', 'อื่น ๆ', 2, false],
        ]);

        // Q3.2: การเข้าร่วมกิจกรรมด้านการเงินจากโครงการ (20 pts)
        $q32 = $this->createQuestion($capital, 'Q3.2', 'การเข้าร่วมกิจกรรมด้านการเงินจากโครงการ', 'multi_select', 20, true, 4);
        $this->createChoices($q32, [
            ['0', 'ไม่เคยเข้าร่วมกิจกรรมด้านการเงินจากโครงการ', 0, true],
            ['1', 'อบรม/ให้ความรู้การบริหารจัดการการเงินบุคคลหรือครัวเรือน', 4, false],
            ['2', 'อบรม/ฝึกปฏิบัติการทำบัญชีครัวเรือน', 4, false],
            ['3', 'อบรม/ให้คำปรึกษาการแก้ไขปัญหาหนี้สิน', 4, false],
            ['4', 'กิจกรรมส่งเสริมการออมและการสร้างวินัยทางการเงิน', 4, false],
            ['5', 'การให้คำปรึกษาด้านการเงินเป็นรายบุคคลหรือครัวเรือน', 4, false],
            ['6', 'กิจกรรมอื่น ๆ ด้านการเงิน', 4, false],
        ]);

        // Q4: รายได้เฉลี่ยปัจจุบัน (20 pts) - numeric
        $q4 = $this->createQuestion($capital, 'Q4', 'รายได้เฉลี่ยปัจจุบัน (บาท/เดือน)', 'numeric', 20, false, 5);
        // Numeric scoring will be handled by value_numeric ranges (TODO: confirm thresholds)
        $this->createChoices($q4, [
            ['1', 'น้อยกว่า 1,000 บาท/เดือน', 4, false],
            ['2', '1,000–3,000 บาท/เดือน', 8, false],
            ['3', '3,001–5,000 บาท/เดือน', 12, false],
            ['4', '5,001–10,000 บาท/เดือน', 16, false],
            ['5', 'มากกว่า 10,000 บาท/เดือน', 20, false],
        ]);
    }

    private function seedPhysical(): void
    {
        $capital = Capital::firstOrCreate(
            ['slug' => 'physical'],
            ['name_th' => 'ทุนกายภาพ', 'name_en' => 'Physical Capital', 'max_score' => 100, 'sort_order' => 2]
        );

        // Q5: ช่องทางจำหน่ายสินค้า (70 pts) - multi select
        $q5 = $this->createQuestion($capital, 'Q5', 'ช่องทางจำหน่ายสินค้า/ผลผลิตปัจจุบัน', 'multi_select', 70, true, 1);
        $this->createChoices($q5, [
            ['0', 'ไม่ได้จำหน่ายสินค้า/ผลผลิต', 0, true],
            ['1', 'ตลาดชุมชน', 10, false],
            ['2', 'ตลาดออนไลน์', 10, false],
            ['3', 'ตลาดในอำเภอ', 10, false],
            ['4', 'ตลาดห้างสรรพสินค้า', 10, false],
            ['5', 'ตลาด OTOP', 10, false],
            ['6', 'ตลาดต่างประเทศ', 10, false],
            ['7', 'อื่นๆ', 10, false],
        ]);

        // Q6: ปัญหาเกี่ยวกับพื้นที่ทำกิน (30 pts) - special_q6
        // "ดี=มาก" policy: no problems = full score; sub-problems = penalty
        $q6 = $this->createQuestion(
            $capital, 'Q6', 'ปัญหาเกี่ยวกับพื้นที่ทำกินปัจจุบัน',
            'special_q6', 30, true, 2,
            ['penalty_per_problem' => 5]  // TODO: confirm with stakeholders
        );
        $this->createChoices($q6, [
            ['0',   'ไม่มีปัญหา', 30, true],   // exclusive => full score
            ['1',   'มีปัญหา', 0, false],        // parent choice (selecting triggers sub-choices)
            ['1.1', 'น้ำเข้าไม่ถึง/ไม่เพียงพอ', 0, false],
            ['1.2', 'ดินไม่อุดมสมบูรณ์', 0, false],
            ['1.3', 'ไม่มีเอกสารสิทธิ์', 0, false],
            ['1.4', 'ที่ดินติดจำนอง', 0, false],
            ['1.5', 'อยู่ในพื้นที่เสี่ยงภัย', 0, false],
            ['1.6', 'เข้าถึงยาก', 0, false],
            ['1.7', 'อื่นๆ', 0, false],
        ]);
    }

    private function seedFinancial(): void
    {
        $capital = Capital::firstOrCreate(
            ['slug' => 'financial'],
            ['name_th' => 'ทุนการเงิน', 'name_en' => 'Financial Capital', 'max_score' => 100, 'sort_order' => 3]
        );

        // Q7: การนำความรู้ด้านการบริหารจัดการทางการเงินไปใช้ (20 pts)
        $q7 = $this->createQuestion($capital, 'Q7', 'การนำความรู้ด้านการบริหารจัดการทางการเงินไปใช้ในชีวิตประจำวัน', 'multi_select', 20, true, 1);
        $this->createChoices($q7, [
            ['0', 'ยังไม่ได้นำไปใช้', 0, true],
            ['1', 'ทำบัญชีครัวเรือนหรือบันทึกรายรับ–รายจ่ายสม่ำเสมอ', 3, false],
            ['2', 'วางแผนการใช้จ่ายและควบคุมค่าใช้จ่ายในครัวเรือน', 3, false],
            ['3', 'ลดรายจ่ายที่ไม่จำเป็น', 3, false],
            ['4', 'วางแผนและเริ่มการออมอย่างสม่ำเสมอ', 3, false],
            ['5', 'ปรับวิธีจัดการหนี้', 3, false],
            ['6', 'ปรับการตัดสินใจด้านการกู้ยืม/การใช้สินเชื่อ', 3, false],
            ['7', 'อื่น ๆ', 2, false],
        ]);

        // Q8: รายจ่ายครัวเรือนปัจจุบัน (20 pts) - simplified to comparison scale
        $q8 = $this->createQuestion($capital, 'Q8', 'รายจ่ายครัวเรือนปัจจุบัน (เปรียบเทียบกับก่อนเข้าโครงการ)', 'single_select', 20, false, 2);
        $this->createChoices($q8, [
            ['1', 'ลดลง', 20, false],
            ['2', 'เท่าเดิม', 10, false],
            ['3', 'เพิ่มขึ้น', 5, false],
        ]);

        // Q9: การออม (20 pts)
        $q9 = $this->createQuestion($capital, 'Q9', 'การออมปัจจุบัน', 'single_select', 20, false, 3);
        $this->createChoices($q9, [
            ['0', 'ไม่มีการออม', 0, false],
            ['1', 'มีการออม', 20, false],
        ]);

        // Q10: หนี้สิน (20 pts)
        $q10 = $this->createQuestion($capital, 'Q10', 'หนี้สินปัจจุบัน', 'single_select', 20, false, 4);
        $this->createChoices($q10, [
            ['0', 'ไม่มีหนี้สิน', 20, false],
            ['1', 'มีหนี้สิน แต่ลดลงจากก่อนเข้าโครงการ', 15, false],
            ['2', 'มีหนี้สิน เท่าเดิม', 10, false],
            ['3', 'มีหนี้สิน เพิ่มขึ้น', 5, false],
        ]);

        // Q11: ทรัพย์สินเพื่อการประกอบอาชีพ (20 pts)
        $q11 = $this->createQuestion($capital, 'Q11', 'ทรัพย์สินเพื่อการประกอบอาชีพปัจจุบัน', 'multi_select', 20, true, 5);
        $this->createChoices($q11, [
            ['1',    'ไม่มี', 0, true],
            ['2.1',  'เครื่องจักรกล', 3, false],
            ['2.2',  'รถมอเตอร์ไซค์ (รับจ้าง/ส่งของ)', 3, false],
            ['2.3',  'รถแท็กซี่', 3, false],
            ['2.4',  'รถยนต์ (รับจ้าง ค้าขาย)', 3, false],
            ['2.5',  'เรือประมง', 3, false],
            ['2.6',  'แผงขายของ', 3, false],
            ['2.7',  'รถโชเล่ย์', 3, false],
            ['2.8',  'ยุ้งฉาง', 3, false],
            ['2.9',  'หุ้น/กองทุน', 3, false],
            ['2.10', 'แชร์', 2, false],
            ['2.11', 'สัตว์เลี้ยง (มีมูลค่า)', 2, false],
            ['2.12', 'อื่นๆ', 2, false],
        ]);
    }

    private function seedNatural(): void
    {
        $capital = Capital::firstOrCreate(
            ['slug' => 'natural'],
            ['name_th' => 'ทุนทรัพยากรธรรมชาติ', 'name_en' => 'Natural Capital', 'max_score' => 100, 'sort_order' => 4]
        );

        // Q12.1: ครัวเรือนประสบภัยพิบัติหรือไม่ (40 pts) - special_q12 (parent + sub disaster types)
        $q121 = $this->createQuestion($capital, 'Q12.1', 'ครัวเรือนของท่านประสบภัยพิบัติหรือไม่ หลังจากเข้าร่วมโครงการ', 'special_q12', 40, false, 1);
        $this->createChoices($q121, [
            ['0',         'ไม่ประสบ',   40, true],   // exclusive => full score
            ['1',         'ประสบ',      20, false],  // parent choice
            ['1.อุทกภัย', 'อุทกภัย',    0,  false],  // sub-type
            ['1.วาตภัย',  'วาตภัย',     0,  false],
            ['1.ภัยแล้ง', 'ภัยแล้ง',    0,  false],
            ['1.อัคคีภัย','อัคคีภัย',   0,  false],
            ['1.โรคระบาด','โรคระบาด',   0,  false],
            ['1.อื่นๆ',   'อื่นๆ',      0,  false],
        ]);

        // Q12.2: การรับมือกับภัยพิบัติ (60 pts)
        $q122 = $this->createQuestion($capital, 'Q12.2', 'การรับมือกับภัยพิบัติ', 'single_select', 60, false, 2);
        $this->createChoices($q122, [
            ['0', 'ไม่ได้รับความช่วยเหลือ/ช่วยเหลือตัวเอง', 15, false],
            ['1', 'ได้รับการช่วยเหลือและชดเชยเยียวยาจากภาครัฐ/เอกชน', 30, false],
            ['2', 'ชุมชนมีระบบบริหารจัดการรองรับภัยพิบัติ', 45, false],
            ['3', 'ชุมชนมีระบบรองรับภัยพิบัติและฟื้นตัวเข้าสู่ภาวะปกติได้เร็ว', 60, false],
        ]);
    }

    private function seedSocial(): void
    {
        $capital = Capital::firstOrCreate(
            ['slug' => 'social'],
            ['name_th' => 'ทุนทางสังคม', 'name_en' => 'Social Capital', 'max_score' => 100, 'sort_order' => 5]
        );

        // Q13: การเป็นสมาชิกกลุ่มกิจกรรม (60 pts)
        $q13 = $this->createQuestion($capital, 'Q13', 'การเป็นสมาชิกกลุ่มกิจกรรมปัจจุบัน', 'multi_select', 60, false, 1);
        $this->createChoices($q13, [
            ['1', 'กลุ่มอาชีพ/การผลิต/แปรรูป', 10, false],
            ['2', 'กลุ่มการเงิน (กองทุนหมู่บ้าน กลุ่มออมทรัพย์)', 10, false],
            ['3', 'กลุ่มสวัสดิการสังคม (ฌาปนกิจ กองทุนสวัสดิการ)', 10, false],
            ['4', 'กลุ่มด้านสังคม (เยาวชน ผู้สูงอายุ สตรี/แม่บ้าน ศาสนา)', 10, false],
            ['5', 'กลุ่มทรัพยากรธรรมชาติ (อนุรักษ์ ผู้ใช้น้ำ)', 10, false],
            ['6', 'กลุ่มอื่นๆ', 10, false],
        ]);

        // Q14: ภาคีเครือข่ายที่เข้าร่วมสนับสนุนครัวเรือน (40 pts)
        $q14 = $this->createQuestion($capital, 'Q14', 'ภาคีเครือข่ายที่เข้าร่วมสนับสนุนครัวเรือน', 'multi_select', 40, false, 2);
        $this->createChoices($q14, [
            ['1', 'หน่วยงานภาครัฐ', 8, false],
            ['2', 'สถาบันการศึกษา', 8, false],
            ['3', 'หน่วยงานภาคเอกชน', 8, false],
            ['4', 'ผู้นำชุมชน/อสม./อพม.', 8, false],
            ['5', 'องค์กรภาคประชาสังคม', 8, false],
            ['6', 'อื่นๆ', 8, false],
        ]);
    }

    private function createQuestion(
        Capital $capital,
        string $key,
        string $textTh,
        string $type,
        int $maxScore,
        bool $hasExclusive,
        int $sortOrder,
        ?array $meta = null
    ): Question {
        return Question::firstOrCreate(
            ['question_key' => $key],
            [
                'capital_id'          => $capital->id,
                'text_th'             => $textTh,
                'type'                => $type,
                'max_score'           => $maxScore,
                'has_exclusive_option' => $hasExclusive,
                'meta'                => $meta,
                'sort_order'          => $sortOrder,
            ]
        );
    }

    private function createChoices(Question $question, array $choices): void
    {
        foreach ($choices as $i => [$key, $text, $weight, $exclusive]) {
            Choice::firstOrCreate(
                ['question_id' => $question->id, 'choice_key' => $key],
                [
                    'text_th'      => $text,
                    'weight'       => $weight,
                    'is_exclusive' => $exclusive,
                    'sort_order'   => $i,
                ]
            );
        }
    }
}
