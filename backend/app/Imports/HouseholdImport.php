<?php

namespace App\Imports;

use App\Models\Household;
use App\Models\Person;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

/**
 * Import legacy household baseline data from CSV or XLSX.
 *
 * Column mapping is based on:
 *   data/ข้อมูลพื้นฐานครัวเรือนยากจน_30_2568 (7).csv
 *
 * NOTE: citizen_id may appear in scientific notation in Excel.
 *       Maatwebsite/Excel reads it as a float string, so we cast to string and
 *       reformat if it looks like scientific notation.
 */
class HouseholdImport implements ToCollection, WithHeadingRow, WithCustomCsvSettings
{
    public int $imported = 0;
    public int $skipped  = 0;

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $houseCode = $this->cleanString($row->get('รหัสบ้าน') ?? $row->get('house_code') ?? '');

            if (empty($houseCode)) {
                $this->skipped++;
                continue;
            }

            $household = Household::firstOrCreate(
                ['house_code' => $houseCode],
                [
                    'village_name'     => $this->cleanString($row->get('ชื่อหมู่บ้าน')),
                    'village_no'       => $this->cleanString($row->get('หมู่ที่')),
                    'subdistrict_code' => $this->cleanString($row->get('ตำบล')),
                    'subdistrict_name' => $this->getSubdistrictName($row),
                    'district_code'    => $this->cleanString($row->get('อำเภอ')),
                    'district_name'    => $this->getDistrictName($row),
                    'province_code'    => $this->cleanString($row->get('จังหวัด')),
                    'province_name'    => $this->getProvinceName($row),
                    'postal_code'      => $this->cleanString($row->get('รหัสไปรษณีย์')),
                    'house_no'         => $this->cleanString($row->get('บ้านเลขที่')),
                    'road'             => $this->cleanString($row->get('ถนน')),
                    'alley'            => $this->cleanString($row->get('ซอย')),
                    'latitude'         => $this->toFloat($row->get('ละติจูด')),
                    'longitude'        => $this->toFloat($row->get('ลองจิจูด')),
                    'survey_year'      => $this->toInt($row->get('ปีที่สำรวจ')),
                    'survey_round'     => $this->toInt($row->get('ครั้งที่สำรวจ')),
                    'raw_data'         => $row->toArray(),
                ]
            );

            // Import head of household
            $citizenId = $this->parseCitizenId($row->get('หมายเลขบัตรประจำตัวประชาชน'));
            $firstName = $this->cleanString($row->get('ชื่อ'));
            $lastName  = $this->cleanString($row->get('สกุล'));

            if (!empty($firstName) || !empty($citizenId)) {
                Person::firstOrCreate(
                    [
                        'household_id' => $household->id,
                        'is_head'      => true,
                    ],
                    [
                        'title'      => $this->cleanString($row->get('คำนำหน้าชื่อ')),
                        'first_name' => $firstName,
                        'last_name'  => $lastName,
                        'citizen_id' => $citizenId,
                        'phone'      => $this->cleanString($row->get('เบอร์โทรศัพท์')),
                    ]
                );
            }

            $this->imported++;
        }
    }

    /**
     * CSV has duplicate column headers for subdistrict/district/province.
     * The second occurrence (Thai name) appears right after the code.
     * We try multiple fallbacks.
     */
    private function getSubdistrictName(Collection $row): ?string
    {
        // Try different column name patterns
        $keys = ['ตำบล_1', 'subdistrict_name'];
        foreach ($keys as $k) {
            $v = $this->cleanString($row->get($k));
            if ($v) return $v;
        }
        return null;
    }

    private function getDistrictName(Collection $row): ?string
    {
        $keys = ['อำเภอ_1', 'district_name'];
        foreach ($keys as $k) {
            $v = $this->cleanString($row->get($k));
            if ($v) return $v;
        }
        return null;
    }

    private function getProvinceName(Collection $row): ?string
    {
        $keys = ['จังหวัด_1', 'province_name'];
        foreach ($keys as $k) {
            $v = $this->cleanString($row->get($k));
            if ($v) return $v;
        }
        return null;
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
