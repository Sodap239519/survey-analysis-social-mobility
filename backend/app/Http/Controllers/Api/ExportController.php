<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExportLog;
use App\Models\Household;
use App\Models\ImportLog;
use App\Models\Person;
use App\Models\SurveyResponse;
use App\Services\CompareHouseholdSurveyLogic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * ExportController
 *
 * Handles CSV/Excel export for all admin tables.
 * When exporting lower-level data (persons, responses), address fields
 * (รหัสบ้าน, บ้านเลขที่, หมู่ที่, หมู่บ้าน, ตำบล, อำเภอ) are automatically included.
 *
 * Routes:
 *   GET /api/v1/export/{table}   – download CSV or Excel
 *   GET /api/v1/export/history   – list past exports (paginated)
 *   DELETE /api/v1/export/history/{id} – delete an export log entry
 *   GET /api/v1/export/comparison – export comparison report
 */
class ExportController extends Controller
{
    // Address fields automatically prepended to non-household exports
    private const ADDRESS_HEADERS = [
        'house_code'       => 'รหัสบ้าน',
        'house_no'         => 'บ้านเลขที่',
        'village_no'       => 'หมู่ที่',
        'village_name'     => 'หมู่บ้าน',
        'subdistrict_name' => 'ตำบล',
        'district_name'    => 'อำเภอ',
    ];

    /**
     * Threshold for trend classification: ดีขึ้น / คงที่ / แย่ลง
     * A change is considered "improved" when the difference exceeds 5% of the before score,
     * and "worse" when it falls more than 5% below. Otherwise it is "stable".
     * (per project comparison rules: ดีขึ้น = new > old + 5%, แย่ลง = new < old - 5%)
     */
    private const TREND_THRESHOLD_PCT = 0.05;

    // ──────────────────────────────────────────────
    // Export History
    // ──────────────────────────────────────────────

    public function history(Request $request): JsonResponse
    {
        try {
            $logs = ExportLog::query()
                ->with('user:id,name')
                ->orderByDesc('created_at')
                ->paginate($request->integer('per_page', 20));

            return response()->json($logs->through(fn ($log) => [
                'id'            => $log->id,
                'table_name'    => $log->table_name,
                'format'        => $log->format,
                'filename'      => $log->filename,
                'records_count' => $log->records_count,
                'filters'       => $log->filters,
                'exported_by'   => $log->user?->name ?? 'ระบบ',
                'exported_at'   => $log->created_at?->toDateTimeString(),
            ]));
        } catch (\Throwable $e) {
            // Return empty pagination result if export_logs table does not exist yet
            return response()->json([
                'data'          => [],
                'total'         => 0,
                'per_page'      => 20,
                'current_page'  => 1,
                'last_page'     => 1,
                'from'          => null,
                'to'            => null,
            ]);
        }
    }

    public function deleteHistory(int $id): JsonResponse
    {
        try {
            $log = ExportLog::findOrFail($id);
            $log->delete();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Not found'], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            // Table does not exist yet – treat as already gone
        }

        return response()->json(['message' => 'Deleted']);
    }

    // ──────────────────────────────────────────────
    // Export Tables
    // ──────────────────────────────────────────────

    /**
     * Export a table as CSV or Excel.
     *
     * @param  string  $table  One of: households, persons, responses, import-logs
     */
    public function export(Request $request, string $table): Response|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $format = strtolower($request->query('format', 'csv'));

        return match ($table) {
            'households'   => $this->exportHouseholds($request, $format),
            'persons'      => $this->exportPersons($request, $format),
            'responses'    => $this->exportResponses($request, $format),
            'import-logs'  => $this->exportImportLogs($request, $format),
            'comparison'   => $this->exportComparison($request, $format),
            default        => response('ไม่รองรับตาราง: ' . $table, 400),
        };
    }

    // ──────────────────────────────────────────────
    // Private: per-table export helpers
    // ──────────────────────────────────────────────

    private function exportHouseholds(Request $request, string $format): \Symfony\Component\HttpFoundation\StreamedResponse|Response
    {
        $query = Household::query();

        if ($request->filled('survey_year')) {
            $query->where('survey_year', (int) $request->survey_year);
        }
        if ($request->filled('district')) {
            $query->where('district_name', 'like', '%' . $request->district . '%');
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('house_code', 'like', "%{$s}%")
                ->orWhere('village_name', 'like', "%{$s}%"));
        }

        $headers = [
            'house_code'       => 'รหัสบ้าน',
            'house_no'         => 'บ้านเลขที่',
            'village_no'       => 'หมู่ที่',
            'village_name'     => 'หมู่บ้าน',
            'subdistrict_name' => 'ตำบล',
            'district_name'    => 'อำเภอ',
            'province_name'    => 'จังหวัด',
            'postal_code'      => 'รหัสไปรษณีย์',
            'latitude'         => 'ละติจูด',
            'longitude'        => 'ลองจิจูด',
            'survey_year'      => 'ปีสำรวจ',
            'survey_round'     => 'รอบสำรวจ',
        ];

        $rows = $query->orderBy('house_code')->get()->map(fn ($h) => [
            'house_code'       => $h->house_code,
            'house_no'         => $h->house_no,
            'village_no'       => $h->village_no,
            'village_name'     => $h->village_name,
            'subdistrict_name' => $h->subdistrict_name,
            'district_name'    => $h->district_name,
            'province_name'    => $h->province_name,
            'postal_code'      => $h->postal_code,
            'latitude'         => $h->latitude,
            'longitude'        => $h->longitude,
            'survey_year'      => $h->survey_year,
            'survey_round'     => $h->survey_round,
        ])->toArray();

        return $this->streamCsv('households', $headers, $rows, $request, $format);
    }

    private function exportPersons(Request $request, string $format): \Symfony\Component\HttpFoundation\StreamedResponse|Response
    {
        $query = Person::query()->with('household');

        if ($request->filled('household_id')) {
            $query->where('household_id', (int) $request->household_id);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('first_name', 'like', "%{$s}%")
                ->orWhere('last_name', 'like', "%{$s}%")
                ->orWhere('citizen_id', 'like', "%{$s}%"));
        }

        // Address headers first, then person-specific
        $headers = array_merge(self::ADDRESS_HEADERS, [
            'title'      => 'คำนำหน้า',
            'first_name' => 'ชื่อ',
            'last_name'  => 'สกุล',
            'citizen_id' => 'เลขบัตรประชาชน',
            'birthdate'  => 'วันเกิด',
            'phone'      => 'เบอร์โทร',
            'is_head'    => 'หัวหน้าครัวเรือน',
        ]);

        $rows = $query->orderBy('id')->get()->map(function ($p) {
            $h = $p->household;
            return [
                'house_code'       => $h?->house_code,
                'house_no'         => $h?->house_no,
                'village_no'       => $h?->village_no,
                'village_name'     => $h?->village_name,
                'subdistrict_name' => $h?->subdistrict_name,
                'district_name'    => $h?->district_name,
                'title'            => $p->title,
                'first_name'       => $p->first_name,
                'last_name'        => $p->last_name,
                'citizen_id'       => $p->citizen_id,
                'birthdate'        => $p->birthdate,
                'phone'            => $p->phone,
                'is_head'          => $p->is_head ? 'ใช่' : 'ไม่',
            ];
        })->toArray();

        return $this->streamCsv('persons', $headers, $rows, $request, $format);
    }

    private function exportResponses(Request $request, string $format): \Symfony\Component\HttpFoundation\StreamedResponse|Response
    {
        $query = SurveyResponse::query()->with('household', 'person');

        if ($request->filled('period')) {
            $query->where('period', $request->period);
        }
        if ($request->filled('survey_year')) {
            $query->where('survey_year', (int) $request->survey_year);
        }
        if ($request->filled('household_id')) {
            $query->where('household_id', (int) $request->household_id);
        }

        // Address headers first, then response-specific
        $headers = array_merge(self::ADDRESS_HEADERS, [
            'period'          => 'ช่วงเวลา',
            'survey_year'     => 'ปีสำรวจ',
            'survey_round'    => 'รอบสำรวจ',
            'surveyed_at'     => 'วันที่สำรวจ',
            'surveyor_name'   => 'ชื่อผู้สำรวจ',
            'model_name'      => 'โมเดล',
            'score_human'     => 'ทุนมนุษย์ (คะแนน)',
            'score_physical'  => 'ทุนกายภาพ (คะแนน)',
            'score_financial' => 'ทุนการเงิน (คะแนน)',
            'score_natural'   => 'ทุนธรรมชาติ (คะแนน)',
            'score_social'    => 'ทุนสังคม (คะแนน)',
            'score_aggregate' => 'คะแนนรวม (X)',
            'poverty_level'   => 'ระดับความยากจน',
        ]);

        $rows = $query->orderBy('id')->get()->map(function ($r) {
            $h = $r->household;
            return [
                'house_code'       => $h?->house_code,
                'house_no'         => $h?->house_no,
                'village_no'       => $h?->village_no,
                'village_name'     => $h?->village_name,
                'subdistrict_name' => $h?->subdistrict_name,
                'district_name'    => $h?->district_name,
                'period'           => $r->period,
                'survey_year'      => $r->survey_year,
                'survey_round'     => $r->survey_round,
                'surveyed_at'      => $r->surveyed_at?->toDateString(),
                'surveyor_name'    => $r->surveyor_name,
                'model_name'       => $r->model_name,
                'score_human'      => $r->score_human,
                'score_physical'   => $r->score_physical,
                'score_financial'  => $r->score_financial,
                'score_natural'    => $r->score_natural,
                'score_social'     => $r->score_social,
                'score_aggregate'  => $r->score_aggregate,
                'poverty_level'    => $r->poverty_level,
            ];
        })->toArray();

        return $this->streamCsv('responses', $headers, $rows, $request, $format);
    }

    private function exportImportLogs(Request $request, string $format): \Symfony\Component\HttpFoundation\StreamedResponse|Response
    {
        $headers = [
            'id'             => 'ลำดับ',
            'filename'       => 'ชื่อไฟล์',
            'imported_count' => 'นำเข้าสำเร็จ',
            'exists_count'   => 'ซ้ำ',
            'skipped_count'  => 'ข้าม',
            'imported_by'    => 'ผู้นำเข้า',
            'created_at'     => 'วันที่นำเข้า',
        ];

        $rows = ImportLog::query()
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($log) => [
                'id'             => $log->id,
                'filename'       => $log->filename,
                'imported_count' => $log->imported_count,
                'exists_count'   => $log->exists_count,
                'skipped_count'  => $log->skipped_count,
                'imported_by'    => $log->user?->name ?? 'ระบบ',
                'created_at'     => $log->created_at?->toDateTimeString(),
            ])->toArray();

        return $this->streamCsv('import-logs', $headers, $rows, $request, $format);
    }

    private function exportComparison(Request $request, string $format): \Symfony\Component\HttpFoundation\StreamedResponse|Response
    {
        $logic = new CompareHouseholdSurveyLogic();

        $query = Household::query();
        if ($request->filled('district')) {
            $query->where('district_name', 'like', '%' . $request->district . '%');
        }
        if ($request->filled('survey_year')) {
            $query->where('survey_year', (int) $request->survey_year);
        }

        $surveyYear  = $request->filled('survey_year')  ? (int) $request->survey_year  : null;
        $surveyRound = $request->filled('survey_round') ? (int) $request->survey_round : null;

        $headers = [
            'house_code'           => 'รหัสบ้าน',
            'house_no'             => 'บ้านเลขที่',
            'village_no'           => 'หมู่ที่',
            'village_name'         => 'หมู่บ้าน',
            'subdistrict_name'     => 'ตำบล',
            'district_name'        => 'อำเภอ',
            'before_source'        => 'แหล่งข้อมูลก่อน',
            'human_before'         => 'ทุนมนุษย์ (ก่อน)',
            'human_after'          => 'ทุนมนุษย์ (หลัง)',
            'human_diff'           => 'ทุนมนุษย์ (เปลี่ยน)',
            'physical_before'      => 'ทุนกายภาพ (ก่อน)',
            'physical_after'       => 'ทุนกายภาพ (หลัง)',
            'physical_diff'        => 'ทุนกายภาพ (เปลี่ยน)',
            'financial_before'     => 'ทุนการเงิน (ก่อน)',
            'financial_after'      => 'ทุนการเงิน (หลัง)',
            'financial_diff'       => 'ทุนการเงิน (เปลี่ยน)',
            'natural_before'       => 'ทุนธรรมชาติ (ก่อน)',
            'natural_after'        => 'ทุนธรรมชาติ (หลัง)',
            'natural_diff'         => 'ทุนธรรมชาติ (เปลี่ยน)',
            'social_before'        => 'ทุนสังคม (ก่อน)',
            'social_after'         => 'ทุนสังคม (หลัง)',
            'social_diff'          => 'ทุนสังคม (เปลี่ยน)',
            'avg_before'           => 'เฉลี่ยก่อน',
            'avg_after'            => 'เฉลี่ยหลัง',
            'avg_diff'             => 'เฉลี่ยเปลี่ยน',
            'x_before'             => 'X ก่อน',
            'x_after'              => 'X หลัง',
            'poverty_level_before' => 'ระดับก่อน',
            'poverty_level_after'  => 'ระดับหลัง',
            'trend'                => 'แนวโน้ม',
        ];

        $rows = [];
        $query->orderBy('house_code')->chunk(100, function ($households) use (&$rows, $logic, $surveyYear, $surveyRound) {
            foreach ($households as $household) {
                $result = $logic->compare($household, $surveyYear, $surveyRound);
                $s = $result['summary'];
                $c = $result['capitals'];

                // Determine trend (ดีขึ้น/คงที่/แย่ลง) using TREND_THRESHOLD_PCT
                $trend = '—';
                if ($s['avg_before'] !== null && $s['avg_after'] !== null) {
                    $threshold = $s['avg_before'] * self::TREND_THRESHOLD_PCT;
                    if ($s['avg_diff'] > $threshold) {
                        $trend = 'ดีขึ้น';
                    } elseif ($s['avg_diff'] < -$threshold) {
                        $trend = 'แย่ลง';
                    } else {
                        $trend = 'คงที่';
                    }
                }

                $rows[] = [
                    'house_code'           => $household->house_code,
                    'house_no'             => $household->house_no,
                    'village_no'           => $household->village_no,
                    'village_name'         => $household->village_name,
                    'subdistrict_name'     => $household->subdistrict_name,
                    'district_name'        => $household->district_name,
                    'before_source'        => $result['before_source'],
                    'human_before'         => $c['human']['before'],
                    'human_after'          => $c['human']['after'],
                    'human_diff'           => $c['human']['diff'],
                    'physical_before'      => $c['physical']['before'],
                    'physical_after'       => $c['physical']['after'],
                    'physical_diff'        => $c['physical']['diff'],
                    'financial_before'     => $c['financial']['before'],
                    'financial_after'      => $c['financial']['after'],
                    'financial_diff'       => $c['financial']['diff'],
                    'natural_before'       => $c['natural']['before'],
                    'natural_after'        => $c['natural']['after'],
                    'natural_diff'         => $c['natural']['diff'],
                    'social_before'        => $c['social']['before'],
                    'social_after'         => $c['social']['after'],
                    'social_diff'          => $c['social']['diff'],
                    'avg_before'           => $s['avg_before'],
                    'avg_after'            => $s['avg_after'],
                    'avg_diff'             => $s['avg_diff'],
                    'x_before'             => $s['x_before'],
                    'x_after'              => $s['x_after'],
                    'poverty_level_before' => $s['poverty_level_before'],
                    'poverty_level_after'  => $s['poverty_level_after'],
                    'trend'                => $trend,
                ];
            }
        });

        return $this->streamCsv('comparison', $headers, $rows, $request, $format);
    }

    // ──────────────────────────────────────────────
    // Streaming CSV output helper
    // ──────────────────────────────────────────────

    /**
     * Stream CSV (or log and prepare Excel).
     * Logs the export to export_logs table.
     */
    private function streamCsv(
        string $tableName,
        array $headers,
        array $rows,
        Request $request,
        string $format
    ): \Symfony\Component\HttpFoundation\StreamedResponse|Response {
        $count    = count($rows);
        $ts       = now()->format('Ymd_His');
        $filename = "{$tableName}_{$ts}.csv";

        // Log the export (non-fatal: ignore if export_logs table does not exist yet)
        try {
            ExportLog::create([
                'user_id'       => $request->user()?->id,
                'table_name'    => $tableName,
                'format'        => $format === 'excel' ? 'excel' : 'csv',
                'filename'      => $filename,
                'records_count' => $count,
                'filters'       => $request->only(['survey_year', 'period', 'district', 'search', 'survey_round']),
            ]);
        } catch (\Throwable $e) {
            // Table may not exist yet – skip logging but do not abort the export
        }

        if ($format === 'excel') {
            // Build UTF-8 CSV with BOM so Excel reads Thai characters correctly
            $filename = "{$tableName}_{$ts}_excel.csv";
            $content  = "\xEF\xBB\xBF"; // UTF-8 BOM for Excel
            $content .= implode(',', array_map(fn ($v) => '"' . str_replace('"', '""', $v) . '"', $headers)) . "\r\n";
            foreach ($rows as $row) {
                $content .= implode(',', array_map(function ($v) {
                    return '"' . str_replace('"', '""', (string) ($v ?? '')) . '"';
                }, $row)) . "\r\n";
            }

            return response($content, 200, [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        // Streamed CSV
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM so Thai characters display correctly in Excel
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, array_values($headers));
            foreach ($rows as $row) {
                fputcsv($handle, array_values(array_map(fn ($v) => $v ?? '', $row)));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
