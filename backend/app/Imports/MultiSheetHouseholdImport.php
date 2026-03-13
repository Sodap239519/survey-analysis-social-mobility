<?php

namespace App\Imports;

use App\Models\Household;
use App\Models\Person;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Multi-sheet XLSX importer for the 6-sheet household baseline data file.
 *
 * Supported sheets (matched by exact Thai name):
 *   ข้อมูลพื้นฐาน  – household basic data + baseline capital scores
 *   ทุนมนุษย์       – person/respondent data
 *   ทุนกายภาพ      – (read for validation, persons already handled above)
 *   ทุนการเงิน      – (read for validation)
 *   ทุนธรรมชาติ    – (read for validation)
 *   ทุนทางสังคม    – (read for validation)
 *
 * Column mapping is done by header name, NOT by index, so it is resilient to
 * column insertions/re-ordering in the source file.
 *
 * Normalisation:
 *   - house_no cells that Excel has coerced into dates (e.g. "25-ก.พ.") are
 *     converted back to "dd/m" format (e.g. "25/2").
 *   - citizen_id values in scientific notation (e.g. "3.3E+12") are expanded
 *     to their full integer string.
 *
 * Validation:
 *   - ตำบล / อำเภอ / จังหวัด must not be pure decimal numbers; rows that fail
 *     this check are marked as status='skipped' with a reason string.
 *
 * Public properties for ImportController response:
 *   $imported  – count of newly created Household records
 *   $exists    – count of households that already existed (skipped re-create)
 *   $skipped   – count of rows with validation errors
 *   $rows      – per-row summary array for the API response
 */
class MultiSheetHouseholdImport implements WithMultipleSheets
{
    public int   $imported = 0;
    public int   $exists   = 0;
    public int   $skipped  = 0;
    public array $rows     = [];

    // Per-sheet tracking
    public int $basicDataRows       = 0;
    public int $humanCapitalRows    = 0;
    public int $householdsImported  = 0;
    public int $householdsExists    = 0;
    public int $householdsSkipped   = 0;
    public int $personsImported     = 0;
    public int $personsExists       = 0;
    public int $personsSkipped      = 0;

    public function sheets(): array
    {
        return [
            'ข้อมูลพื้นฐาน' => new BasicDataSheetImport($this),
            'ทุนมนุษย์'      => new HumanCapitalSheetImport($this),
            // Other sheets are imported but only address/location columns are used.
            // Currently they only add persons already captured from ทุนมนุษย์.
        ];
    }
}

// ──────────────────────────────────────────────────────────────────────────────
// Sheet: ข้อมูลพื้นฐาน
// ──────────────────────────────────────────────────────────────────────────────
class BasicDataSheetImport implements ToCollection
{
    public function __construct(private MultiSheetHouseholdImport $parent) {}

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) return;

        // First row = headers
        $headerRow = $rows->first()->toArray();
        $headerMap = $this->buildHeaderMap($headerRow);

        foreach ($rows->skip(1) as $row) {
            $data = $row->toArray();
            $this->parent->basicDataRows++;

            $houseCode = $this->cleanString($this->col($data, $headerMap, 'รหัสบ้าน'));

            if (empty($houseCode)) {
                $this->parent->skipped++;
                $this->parent->householdsSkipped++;
                $this->parent->rows[] = [
                    'house_code'       => null,
                    'village_name'     => null,
                    'subdistrict_name' => null,
                    'district_name'    => null,
                    'province_name'    => null,
                    'survey_year'      => null,
                    'status'           => 'skipped',
                    'reason'           => 'รหัสบ้านว่าง',
                ];
                continue;
            }

            // Validate location fields
            $subdistrictName = $this->cleanString($this->col($data, $headerMap, 'ตำบล'));
            $districtName    = $this->cleanString($this->col($data, $headerMap, 'อำเภอ'));
            $provinceName    = $this->cleanString($this->col($data, $headerMap, 'จังหวัด'));

            if ($this->isNumericOnly($subdistrictName) || $this->isNumericOnly($districtName) || $this->isNumericOnly($provinceName)) {
                $this->parent->skipped++;
                $this->parent->householdsSkipped++;
                $this->parent->rows[] = [
                    'house_code'       => $houseCode,
                    'village_name'     => null,
                    'subdistrict_name' => $subdistrictName,
                    'district_name'    => $districtName,
                    'province_name'    => $provinceName,
                    'survey_year'      => null,
                    'status'           => 'skipped',
                    'reason'           => 'ตำบล/อำเภอ/จังหวัดเป็นตัวเลข (column mapping ผิดพลาด)',
                ];
                continue;
            }

            // Parse baseline scores (X scale 1.0–4.0)
            $baselineHuman    = $this->toFloat($this->col($data, $headerMap, 'ทุนมนุษย์'));
            $baselinePhysical = $this->toFloat($this->col($data, $headerMap, 'ทุนกายภาพ'));
            $baselineFinancial= $this->toFloat($this->col($data, $headerMap, 'ทุนการเงิน'));
            $baselineNatural  = $this->toFloat($this->col($data, $headerMap, 'ทุนธรรมชาติ'));
            $baselineSocial   = $this->toFloat($this->col($data, $headerMap, 'ทุนทางสังคม'));

            $houseNo  = $this->normalizeHouseNo($this->col($data, $headerMap, 'บ้านเลขที่'));

            $household = Household::firstOrCreate(
                ['house_code' => $houseCode],
                [
                    'survey_year'             => $this->toInt($this->col($data, $headerMap, 'ปีที่สำรวจ')),
                    'house_no'                => $houseNo,
                    'village_no'              => $this->cleanString($this->col($data, $headerMap, 'หมู่ที่')),
                    'village_name'            => $this->cleanString($this->col($data, $headerMap, 'ชื่อหมู่บ้าน')),
                    'alley'                   => $this->cleanString($this->col($data, $headerMap, 'ซอย')),
                    'road'                    => $this->cleanString($this->col($data, $headerMap, 'ถนน')),
                    'subdistrict_name'        => $subdistrictName,
                    'district_name'           => $districtName,
                    'province_name'           => $provinceName,
                    'postal_code'             => $this->cleanString($this->col($data, $headerMap, 'รหัสไปรษณีย์')),
                    'latitude'                => $this->toFloat($this->col($data, $headerMap, 'ละติจูด')),
                    'longitude'               => $this->toFloat($this->col($data, $headerMap, 'ลองจิจูด')),
                    'baseline_score_human'    => $baselineHuman,
                    'baseline_score_physical' => $baselinePhysical,
                    'baseline_score_financial'=> $baselineFinancial,
                    'baseline_score_natural'  => $baselineNatural,
                    'baseline_score_social'   => $baselineSocial,
                    'raw_data'                => $data,
                ]
            );

            // For existing households, update baseline scores and raw_data if any score is missing
            if (! $household->wasRecentlyCreated
                && ($household->baseline_score_human    === null
                    || $household->baseline_score_physical  === null
                    || $household->baseline_score_financial === null
                    || $household->baseline_score_natural   === null
                    || $household->baseline_score_social    === null)
            ) {
                $household->update([
                    'baseline_score_human'    => $baselineHuman,
                    'baseline_score_physical' => $baselinePhysical,
                    'baseline_score_financial'=> $baselineFinancial,
                    'baseline_score_natural'  => $baselineNatural,
                    'baseline_score_social'   => $baselineSocial,
                    'raw_data'                => $household->raw_data ?? $data,
                ]);
            }

            $status = $household->wasRecentlyCreated ? 'created' : 'exists';
            if ($status === 'created') {
                $this->parent->imported++;
                $this->parent->householdsImported++;
            } else {
                $this->parent->exists++;
                $this->parent->householdsExists++;
            }

            $this->parent->rows[] = [
                'house_code'       => $household->house_code,
                'village_name'     => $household->village_name,
                'subdistrict_name' => $household->subdistrict_name,
                'district_name'    => $household->district_name,
                'province_name'    => $household->province_name,
                'survey_year'      => $household->survey_year,
                'status'           => $status,
            ];
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Build a map: header_text => column_index (0-based) from the raw header row.
     * Extra whitespace is trimmed.
     */
    private function buildHeaderMap(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $idx => $header) {
            $key = trim((string) ($header ?? ''));
            if ($key !== '') {
                $map[$key] = $idx;
            }
        }
        return $map;
    }

    /**
     * Safely retrieve a value from $data by header name.
     * Returns null if header not found or cell is empty.
     */
    private function col(array $data, array $headerMap, string $header): mixed
    {
        if (!isset($headerMap[$header])) {
            return null;
        }
        return $data[$headerMap[$header]] ?? null;
    }

    /**
     * Convert a house_no value that Excel may have parsed as a date.
     *
     * Excel stores dates as serial numbers; when read by openpyxl/PhpSpreadsheet
     * they come back as formatted strings like "25-ก.พ." (for เลขที่ 25 Feb).
     * We convert "dd-<Thai_month_abbr>" to "dd/m".
     */
    /**
     * Pre-compiled regex patterns for Thai month abbreviations (day-month date format).
     * Built once as a class constant to avoid repeated preg_quote calls on every row.
     * Key: compiled regex pattern, Value: month number (1–12).
     *
     * @var array<string,int>|null  (populated lazily via getMonthPatterns())
     */
    private ?array $monthPatterns = null;

    /** @return array<string, int> regex => month number */
    private function getMonthPatterns(): array
    {
        if ($this->monthPatterns !== null) {
            return $this->monthPatterns;
        }
        $thaiMonths = [
            'ม.ค.'  => 1,  'ก.พ.'  => 2,  'มี.ค.' => 3,  'เม.ย.' => 4,
            'พ.ค.'  => 5,  'มิ.ย.' => 6,  'ก.ค.'  => 7,  'ส.ค.'  => 8,
            'ก.ย.'  => 9,  'ต.ค.'  => 10, 'พ.ย.'  => 11, 'ธ.ค.'  => 12,
        ];
        $this->monthPatterns = [];
        foreach ($thaiMonths as $abbr => $month) {
            $this->monthPatterns['/^(\d{1,2})\s*-\s*' . preg_quote($abbr, '/') . '\s*$/u'] = $month;
        }
        return $this->monthPatterns;
    }

    private function normalizeHouseNo(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;

        $str = trim((string) $value);

        foreach ($this->getMonthPatterns() as $pattern => $month) {
            if (preg_match($pattern, $str, $m)) {
                return $m[1] . '/' . $month;
            }
        }

        return $str;
    }

    /**
     * Return true if $value is a string that looks like a pure decimal number.
     * Used to detect columns that have been mis-mapped (e.g. ตำบล = 2.348).
     */
    private function isNumericOnly(?string $value): bool
    {
        if ($value === null || $value === '') return false;
        return is_numeric($value);
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
}

// ──────────────────────────────────────────────────────────────────────────────
// Sheet: ทุนมนุษย์  – person data
// ──────────────────────────────────────────────────────────────────────────────
class HumanCapitalSheetImport implements ToCollection
{
    public function __construct(private MultiSheetHouseholdImport $parent) {}

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) return;

        $headerRow = $rows->first()->toArray();
        $headerMap = $this->buildHeaderMap($headerRow);

        foreach ($rows->skip(1) as $row) {
            $data = $row->toArray();
            $this->parent->humanCapitalRows++;

            $houseCode = $this->cleanString($this->col($data, $headerMap, 'รหัสบ้าน'));
            if (empty($houseCode)) {
                $this->parent->personsSkipped++;
                continue;
            }

            $household = Household::where('house_code', $houseCode)->first();
            if (!$household) {
                $this->parent->personsSkipped++;
                continue;
            }

            $citizenId = $this->parseCitizenId($this->col($data, $headerMap, 'หมายเลขประจำตัวประชาชน'));
            $firstName = $this->cleanString($this->col($data, $headerMap, 'ชื่อ'));
            $lastName  = $this->cleanString($this->col($data, $headerMap, 'สกุล'));
            $title     = $this->cleanString($this->col($data, $headerMap, 'คำนำหน้าชื่อ'));

            if (empty($firstName) && empty($citizenId)) {
                $this->parent->personsSkipped++;
                continue;
            }

            $order = $this->toInt($this->col($data, $headerMap, 'ลำดับในบ้าน')) ?? 1;
            $isHead = ($order === 1);

            $searchKeys = ['household_id' => $household->id];
            if (!empty($citizenId)) {
                $searchKeys['citizen_id'] = $citizenId;
            } else {
                $searchKeys['first_name'] = $firstName;
                $searchKeys['last_name']  = $lastName;
                $searchKeys['is_head']    = $isHead;
            }

            $birthdate = $this->parseBirthdate($this->col($data, $headerMap, 'วัน/เดือน/ปีเกิด'));

            $person = Person::firstOrCreate(
                $searchKeys,
                [
                    'title'      => $title,
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                    'citizen_id' => $citizenId,
                    'birthdate'  => $birthdate,
                    'is_head'    => $isHead,
                ]
            );

            if ($person->wasRecentlyCreated) {
                $this->parent->personsImported++;
            } else {
                $this->parent->personsExists++;
            }
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function buildHeaderMap(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $idx => $header) {
            $key = trim((string) ($header ?? ''));
            if ($key !== '') {
                $map[$key] = $idx;
            }
        }
        return $map;
    }

    private function col(array $data, array $headerMap, string $header): mixed
    {
        if (!isset($headerMap[$header])) {
            return null;
        }
        return $data[$headerMap[$header]] ?? null;
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
            $int = (string) (int) round((float) $str);
            return $int;
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

    private function toInt(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        return (int) $value;
    }

    private function parseBirthdate(mixed $value): ?string
    {
        if ($value === null || $value === '') return null;
        
        $str = trim((string) $value);
        
        // ถ้าเป็น dd/mm/yyyy format
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $str, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $year = $matches[3];
            return "{$year}-{$month}-{$day}";
        }
        
        // ลองแปลง date format อื่นๆ
        try {
            $date = new \DateTime($str);
            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}
