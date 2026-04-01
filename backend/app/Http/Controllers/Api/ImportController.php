<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\HouseholdImport;
use App\Imports\MultiSheetHouseholdImport;
use App\Models\Household;
use App\Models\ImportLog;
use App\Models\SurveyResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ImportController extends Controller
{
    public function importHouseholds(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv,xls',
        ]);

        $filename  = $request->file('file')->getClientOriginalName();
        $extension = strtolower($request->file('file')->getClientOriginalExtension());
        $fileSizeMb = round($request->file('file')->getSize() / 1024 / 1024, 2);
        $startTime = microtime(true);

        // Store the uploaded file so it can be downloaded later
        $storedPath = $request->file('file')->store('imports', 'local');

        // Use the multi-sheet importer for XLSX files; fall back to the legacy
        // single-sheet CSV importer for .csv files.
        $import = ($extension === 'xlsx' || $extension === 'xls')
            ? new MultiSheetHouseholdImport()
            : new HouseholdImport();

        try {
            Excel::import($import, $request->file('file'));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Import failed', [
                'file'  => $filename,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'ไม่สามารถนำเข้าไฟล์ได้ กรุณาตรวจสอบรูปแบบไฟล์และลองอีกครั้ง',
                'detail'  => $e->getMessage(),
            ], 422);
        }

        $processingTime = round(microtime(true) - $startTime, 2);
        $sheetResults = $this->buildSheetResults($import);

        // Log this import
        ImportLog::create([
            'user_id'         => $request->user()?->id,
            'filename'        => $filename,
            'file_path'       => $storedPath,
            'imported_count'  => $import->imported,
            'exists_count'    => $import->exists,
            'skipped_count'   => $import->skipped,
            'sheet_results'   => $sheetResults,
            'file_size_mb'    => $fileSizeMb,
            'processing_time' => $processingTime,
        ]);

        return response()->json([
            'message'         => 'Import completed',
            'imported'        => $import->imported,
            'exists'          => $import->exists,
            'skipped'         => $import->skipped,
            'rows'            => $import->rows,
            'sheet_results'   => $sheetResults,
            'file_size_mb'    => $fileSizeMb,
            'processing_time' => $processingTime,
        ]);
    }

    private function buildSheetResults($import): array
    {
        if (!($import instanceof MultiSheetHouseholdImport)) {
            return [];
        }

        $sheets = [
            [
                'name'        => 'ข้อมูลพื้นฐาน',
                'type'        => 'household',
                'total_rows'  => $import->basicDataRows,
                'imported'    => $import->householdsImported,
                'exists'      => $import->householdsExists,
                'skipped'     => $import->householdsSkipped,
                'description' => 'ครัวเรือน',
            ],
            [
                'name'        => 'ทุนมนุษย์',
                'type'        => 'person',
                'total_rows'  => $import->humanCapitalRows,
                'imported'    => $import->personsImported,
                'exists'      => $import->personsExists,
                'skipped'     => $import->personsSkipped,
                'description' => 'บุคคล',
            ],
        ];

        // Reference sheets share the same household count as basic data
        $referenceSheets = [
            ['name' => 'ทุนกายภาพ',   'description' => 'ครัวเรือน (อ้างอิง)'],
            ['name' => 'ทุนการเงิน',   'description' => 'ครัวเรือน (อ้างอิง)'],
            ['name' => 'ทุนธรรมชาติ',  'description' => 'ครัวเรือน (อ้างอิง)'],
            ['name' => 'ทุนทางสังคม',  'description' => 'ครัวเรือน (อ้างอิง)'],
        ];

        foreach ($referenceSheets as $ref) {
            $sheets[] = [
                'name'        => $ref['name'],
                'type'        => 'reference',
                'total_rows'  => $import->basicDataRows,
                'imported'    => $import->basicDataRows,
                'exists'      => 0,
                'skipped'     => 0,
                'description' => $ref['description'],
            ];
        }

        return $sheets;
    }

    public function show(int $id): JsonResponse
    {
        $log = ImportLog::with('user:id,name')->findOrFail($id);

        return response()->json([
            'id'             => $log->id,
            'filename'       => $log->filename,
            'imported_count' => $log->imported_count,
            'exists_count'   => $log->exists_count,
            'skipped_count'  => $log->skipped_count,
            'imported_by'    => $log->user?->name ?? 'ระบบ',
            'imported_at'    => $log->created_at?->toDateTimeString(),
        ]);
    }

    public function history(): JsonResponse
    {
        $logs = ImportLog::query()
            ->with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(fn ($log) => [
                'id'              => $log->id,
                'filename'        => $log->filename,
                'has_file'        => $log->file_path && Storage::disk('local')->exists($log->file_path),
                'imported_count'  => $log->imported_count,
                'exists_count'    => $log->exists_count,
                'skipped_count'   => $log->skipped_count,
                'imported_by'     => $log->user?->name ?? 'ระบบ',
                'imported_at'     => $log->created_at?->toDateTimeString(),
                'sheet_results'   => $log->sheet_results ?? [],
                'file_size_mb'    => $log->file_size_mb,
                'processing_time' => $log->processing_time,
            ]);

        return response()->json($logs);
    }

    public function download(int $id)
    {
        $log = ImportLog::findOrFail($id);

        if (!$log->file_path || !Storage::disk('local')->exists($log->file_path)) {
            return response()->json(['message' => 'ไม่พบไฟล์'], 404);
        }

        return Storage::disk('local')->download($log->file_path, $log->filename);
    }

    public function stats(): JsonResponse
    {
        // Geographic totals from ALL imported households
        $row = Household::query()
            ->selectRaw('
                COUNT(DISTINCT district_name)    AS district_count,
                COUNT(DISTINCT subdistrict_name) AS subdistrict_count,
                COUNT(DISTINCT village_name)     AS village_count,
                COUNT(DISTINCT house_code)       AS household_count
            ')->first();

        $byDistrict = Household::query()
            ->selectRaw('
                district_name,
                district_code,
                COUNT(DISTINCT subdistrict_name) AS subdistrict_count,
                COUNT(DISTINCT village_name)     AS village_count,
                COUNT(DISTINCT house_code)       AS household_count
            ')
            ->groupBy('district_name', 'district_code')
            ->orderBy('district_name')
            ->get()
            ->toArray();

        // Capital averages from survey_responses (period=after by default)
        $capRow = SurveyResponse::query()
            ->selectRaw('
                ROUND(AVG(score_human),     1) AS avg_human,
                ROUND(AVG(score_physical),  1) AS avg_physical,
                ROUND(AVG(score_financial), 1) AS avg_financial,
                ROUND(AVG(score_natural),   1) AS avg_natural,
                ROUND(AVG(score_social),    1) AS avg_social
            ')->first();

        $capitalAverages = [
            ['slug' => 'human',     'nameTh' => 'ทุนมนุษย์',    'avg' => $capRow ? (float) $capRow->avg_human     : 0],
            ['slug' => 'physical',  'nameTh' => 'ทุนกายภาพ',    'avg' => $capRow ? (float) $capRow->avg_physical  : 0],
            ['slug' => 'financial', 'nameTh' => 'ทุนการเงิน',   'avg' => $capRow ? (float) $capRow->avg_financial : 0],
            ['slug' => 'natural',   'nameTh' => 'ทุนธรรมชาติ',  'avg' => $capRow ? (float) $capRow->avg_natural   : 0],
            ['slug' => 'social',    'nameTh' => 'ทุนสังคม',     'avg' => $capRow ? (float) $capRow->avg_social    : 0],
        ];

        // Poverty level distribution from survey_responses
        $povertyRows = SurveyResponse::query()
            ->whereNotNull('poverty_level')
            ->selectRaw('poverty_level, COUNT(*) AS cnt')
            ->groupBy('poverty_level')
            ->pluck('cnt', 'poverty_level')
            ->toArray();

        $povertyLevels = [
            1 => (int) ($povertyRows[1] ?? 0),
            2 => (int) ($povertyRows[2] ?? 0),
            3 => (int) ($povertyRows[3] ?? 0),
            4 => (int) ($povertyRows[4] ?? 0),
        ];

        return response()->json([
            'total_districts'    => $row ? (int) $row->district_count : 0,
            'total_subdistricts' => $row ? (int) $row->subdistrict_count : 0,
            'total_villages'     => $row ? (int) $row->village_count : 0,
            'total_households'   => $row ? (int) $row->household_count : 0,
            'capital_averages'   => $capitalAverages,
            'poverty_levels'     => $povertyLevels,
            'by_district'        => $byDistrict,
        ]);
    }
}
