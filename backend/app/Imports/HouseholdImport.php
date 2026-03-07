<?php

namespace App\Imports;

use App\Models\Household;
use App\Models\Person;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

/**
 * Import legacy household baseline data from CSV or XLSX.
 *
 * We intentionally do NOT implement WithHeadingRow so that Maatwebsite/Excel
 * does not transform column names (the default slug formatter strips Thai chars).
 * Rows are accessed by 0-based integer index; the first row (header) is skipped.
 *
 * Column indices from:
 *   data/ข้อมูลพื้นฐานครัวเรือนยากจน_30_2568 (7).csv
 *
 *   0  รหัสบ้าน          10 ตำบล (code)       20 หมายเลขบัตร (head)
 *   1  ปีที่สำรวจ         11 ตำบล (name)       21 คำนำหน้า (informant)
 *   2  ครั้งที่สำรวจ       12 อำเภอ (code)      22 ชื่อผู้ให้ข้อมูล
 *   5  บ้านเลขที่          13 อำเภอ (name)      23 สกุลผู้ให้ข้อมูล
 *   6  หมู่ที่             14 จังหวัด (code)    24 หมายเลขบัตร (informant)
 *   7  ชื่อหมู่บ้าน         15 จังหวัด (name)   25 เบอร์โทรศัพท์
 *   8  ซอย               16 รหัสไปรษณีย์       31 ละติจูด
 *   9  ถนน               17 คำนำหน้า (head)   32 ลองจิจูด
 *                        18 ชื่อ (head)
 *                        19 สกุล (head)
 *
 * NOTE: citizen_id may appear in scientific notation in Excel (e.g., 3.3001E+12).
 */
class HouseholdImport implements ToCollection, WithCustomCsvSettings
{
    public int $imported = 0;
    public int $skipped  = 0;

    private int $rowIndex = 0;

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $this->rowIndex++;

            // Skip the header row (first row contains column names)
            if ($this->rowIndex === 1) {
                continue;
            }

            $houseCode = $this->cleanString($row->get(0) ?? '');

            if (empty($houseCode)) {
                $this->skipped++;
                continue;
            }

            $household = Household::firstOrCreate(
                ['house_code' => $houseCode],
                [
                    'survey_year'      => $this->toInt($row->get(1)),
                    'survey_round'     => $this->toInt($row->get(2)),
                    'house_no'         => $this->cleanString($row->get(5)),
                    'village_no'       => $this->cleanString($row->get(6)),
                    'village_name'     => $this->cleanString($row->get(7)),
                    'alley'            => $this->cleanString($row->get(8)),
                    'road'             => $this->cleanString($row->get(9)),
                    'subdistrict_code' => $this->cleanString($row->get(10)),
                    'subdistrict_name' => $this->cleanString($row->get(11)),
                    'district_code'    => $this->cleanString($row->get(12)),
                    'district_name'    => $this->cleanString($row->get(13)),
                    'province_code'    => $this->cleanString($row->get(14)),
                    'province_name'    => $this->cleanString($row->get(15)),
                    'postal_code'      => $this->cleanString($row->get(16)),
                    'latitude'         => $this->toFloat($row->get(31)),
                    'longitude'        => $this->toFloat($row->get(32)),
                    'raw_data'         => $row->toArray(),
                ]
            );

            // Prefer informant (ผู้ให้ข้อมูล) columns [21-24]; fall back to head [17-20]
            $citizenId = $this->parseCitizenId($row->get(24));
            $firstName = $this->cleanString($row->get(22));
            $lastName  = $this->cleanString($row->get(23));
            $title     = $this->cleanString($row->get(21));

            if (empty($firstName) && empty($citizenId)) {
                $citizenId = $this->parseCitizenId($row->get(20));
                $firstName = $this->cleanString($row->get(18));
                $lastName  = $this->cleanString($row->get(19));
                $title     = $this->cleanString($row->get(17));
            }

            if (!empty($firstName) || !empty($citizenId)) {
                // Search by household + is_head to ensure at most one head per household.
                // firstOrCreate intentionally does not update on re-import to preserve
                // any manual corrections made after the initial import.
                // If citizen_id is available, include it as a secondary search key so that
                // re-importing the same person (even across different households) avoids
                // creating duplicates.
                $searchKeys = ['household_id' => $household->id, 'is_head' => true];
                if (!empty($citizenId)) {
                    $searchKeys['citizen_id'] = $citizenId;
                }
                Person::firstOrCreate(
                    $searchKeys,
                    [
                        'title'      => $title,
                        'first_name' => $firstName,
                        'last_name'  => $lastName,
                        'citizen_id' => $citizenId,
                        'phone'      => $this->cleanString($row->get(25)),
                    ]
                );
            }

            $this->imported++;
        }
    }

    /**
     * Handle citizen_id that may arrive as scientific notation (e.g., 3.3001E+12).
     */
    private function parseCitizenId(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $str = (string) $value;

        // If it looks like scientific notation, convert to integer string
        if (preg_match('/^[\d.]+[eE][+\-]\d+$/', $str)) {
            // Cast via float then to int string
            $int = (int) round((float) $str);
            return (string) $int;
        }

        // Strip dashes (e.g., 3-3001-00693-71-9 => 3300100693719)
        $cleaned = str_replace(['-', ' '], '', $str);

        return $cleaned ?: null;
    }

    private function cleanString(mixed $value): ?string
    {
        if ($value === null) return null;
        $s = trim((string) $value);
        return $s === '' ? null : $s;
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') return null;
        $f = filter_var($value, FILTER_VALIDATE_FLOAT);
        return $f !== false ? $f : null;
    }

    private function toInt(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        return (int) $value;
    }

    public function getCsvSettings(): array
    {
        return [
            'input_encoding' => 'UTF-8',
        ];
    }
}
