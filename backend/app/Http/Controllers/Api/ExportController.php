<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\DetailedAnswer;
use App\Models\ExportLog;
use App\Models\Household;
use App\Models\ImportLog;
use App\Models\Person;
use App\Models\Question;
use App\Models\SurveyResponse;
use App\Services\CompareHouseholdSurveyLogic;
use App\Services\ScoringService;
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
            'respondent_name'        => 'ชื่อผู้ตอบ',
            'period'                 => 'ช่วงเวลา',
            'survey_year'            => 'ปีสำรวจ',
            'survey_round'           => 'รอบสำรวจ',
            'surveyed_at'            => 'วันที่สำรวจ',
            'surveyor_name'          => 'ชื่อผู้สำรวจ',
            'model_name'             => 'โมเดล',
            
            // 🆕 ทุนมนุษย์
            'baseline_human'         => 'ทุนมนุษย์ - BASELINE (X)',
            'score_human'            => 'ทุนมนุษย์ - SURVEY (X)',
            'human_diff'             => 'ทุนมนุษย์ - ผลต่าง',
            'human_percent'          => 'ทุนมนุษย์ - เปอร์เซ็นต์',
            'human_trend'            => 'ทุนมนุษย์ - แนวโน้ม',
            
            // 🆕 ทุนกายภาพ
            'baseline_physical'      => 'ทุนกายภาพ - BASELINE (X)',
            'score_physical'         => 'ทุนกายภาพ - SURVEY (X)',
            'physical_diff'          => 'ทุนกายภาพ - ผลต่าง',
            'physical_percent'       => 'ทุนกายภาพ - เปอร์เซ็นต์',
            'physical_trend'         => 'ทุนกายภาพ - แนวโน้ม',
            
            // 🆕 ทุนการเงิน
            'baseline_financial'     => 'ทุนการเงิน - BASELINE (X)',
            'score_financial'        => 'ทุนการเงิน - SURVEY (X)',
            'financial_diff'         => 'ทุนการเงิน - ผลต่าง',
            'financial_percent'      => 'ทุนการเงิน - เปอร์เซ็นต์',
            'financial_trend'        => 'ทุนการเงิน - แนวโน้ม',
            
            // 🆕 ทุนธรรมชาติ
            'baseline_natural'       => 'ทุนธรรมชาติ - BASELINE (X)',
            'score_natural'          => 'ทุนธรรมชาติ - SURVEY (X)',
            'natural_diff'           => 'ทุนธร��มชาติ - ผลต่าง',
            'natural_percent'        => 'ทุนธรรมชาติ - เปอร์เซ็นต์',
            'natural_trend'          => 'ทุนธรรมชาติ - แนวโน้ม',
            
            // 🆕 ทุนสังคม
            'baseline_social'        => 'ทุนสังคม - BASELINE (X)',
            'score_social'           => 'ทุนสังคม - SURVEY (X)',
            'social_diff'            => 'ทุนสังคม - ผลต่าง',
            'social_percent'         => 'ทุนสังคม - เปอร์เซ็��ต์',
            'social_trend'           => 'ทุนสังคม - แนวโน้ม',
            
            'score_aggregate'        => 'คะแนนรวม (X)',
            'avg_score'              => 'คะแนนเฉลี่ย',
            'poverty_level'          => 'ระดับ (1-4)',
            'poverty_label'          => 'ระดับความเป็นอยู่',

            // 🆕 ข้อมูลการเงินส่วนบุคคล
            'income_baseline'        => 'รายได้เดิม',
            'income_survey'          => 'รายได้จากการสำรวจ',
            'expense_total'          => 'รายจ่าย',
            'debt_total'             => 'หนี้สิน',
            'savings_total'          => 'เงินออม',
        ]);

        $scoring = new ScoringService();

        // ── Bulk-load financial data to avoid N+1 ──────────────────────────────

        // Fetch all responses first so we can collect IDs
        $responses = $query->orderBy('id')->get();
        $responseIds = $responses->pluck('id');

        // Baseline income: from persons.baseline_income_monthly (already eager-loaded via 'person')
        // (accessed per response below)

        // Survey income: from answers.value_numeric for question_key Q4/04
        $surveyIncomeByResponseId = [];
        $q4Ids = Question::whereIn('question_key', ['Q4', '04'])->pluck('id');
        if ($q4Ids->isNotEmpty() && $responseIds->isNotEmpty()) {
            Answer::whereIn('survey_response_id', $responseIds)
                ->whereIn('question_id', $q4Ids)
                ->whereNotNull('value_numeric')
                ->get(['survey_response_id', 'value_numeric'])
                ->each(function ($a) use (&$surveyIncomeByResponseId) {
                    $surveyIncomeByResponseId[$a->survey_response_id] = (float) $a->value_numeric;
                });
        }

        // Expenses: sum of Q8 value_text JSON amounts > 0 per response
        $expenseByResponseId = [];
        $q8Id = Question::where('question_key', 'Q8')->value('id');
        if ($q8Id && $responseIds->isNotEmpty()) {
            Answer::whereIn('survey_response_id', $responseIds)
                ->where('question_id', $q8Id)
                ->whereNotNull('value_text')
                ->get(['survey_response_id', 'value_text'])
                ->each(function ($a) use (&$expenseByResponseId) {
                    $decoded = json_decode($a->value_text, true);
                    if (!is_array($decoded)) {
                        return;
                    }
                    $sum = 0.0;
                    foreach ($decoded as $value) {
                        if (is_numeric($value) && (float) $value > 0) {
                            $sum += (float) $value;
                        }
                    }
                    if ($sum > 0) {
                        $expenseByResponseId[$a->survey_response_id] = $sum;
                    }
                });
        }

        // Debt: sum of Q10_debt sub_answers amounts > 0 per response
        $debtByResponseId = [];
        if ($responseIds->isNotEmpty()) {
            DetailedAnswer::whereIn('survey_response_id', $responseIds)
                ->where('question_code', 'Q10_debt')
                ->whereNotNull('sub_answers')
                ->get(['survey_response_id', 'sub_answers'])
                ->each(function ($da) use (&$debtByResponseId) {
                    $subAnswers = $da->sub_answers;
                    if (!is_array($subAnswers)) {
                        return;
                    }
                    $sum = 0.0;
                    foreach ($subAnswers as $info) {
                        if (is_array($info) && isset($info['amount']) && is_numeric($info['amount']) && (float) $info['amount'] > 0) {
                            $sum += (float) $info['amount'];
                        } elseif (is_numeric($info) && (float) $info > 0) {
                            $sum += (float) $info;
                        }
                    }
                    if ($sum > 0) {
                        $debtByResponseId[$da->survey_response_id] = ($debtByResponseId[$da->survey_response_id] ?? 0.0) + $sum;
                    }
                });
        }

        // Savings: sum of Q9_savings sub_answers amounts > 0 per response
        // Q9_savings sub_answers structure: key -> numeric amount (unlike Q10_debt which uses key -> {amount: ...})
        // Added defensive array-with-amount handling for forward-compatibility
        $savingsByResponseId = [];
        if ($responseIds->isNotEmpty()) {
            DetailedAnswer::whereIn('survey_response_id', $responseIds)
                ->where('question_code', 'Q9_savings')
                ->whereNotNull('sub_answers')
                ->get(['survey_response_id', 'sub_answers'])
                ->each(function ($da) use (&$savingsByResponseId) {
                    $subAnswers = $da->sub_answers;
                    if (!is_array($subAnswers)) {
                        return;
                    }
                    $sum = 0.0;
                    foreach ($subAnswers as $value) {
                        if (is_array($value) && isset($value['amount']) && is_numeric($value['amount']) && (float) $value['amount'] > 0) {
                            $sum += (float) $value['amount'];
                        } elseif (is_numeric($value) && (float) $value > 0) {
                            $sum += (float) $value;
                        }
                    }
                    if ($sum > 0) {
                        $savingsByResponseId[$da->survey_response_id] = ($savingsByResponseId[$da->survey_response_id] ?? 0.0) + $sum;
                    }
                });
        }

        // ── Build rows ─────────────────────────────────────────────────────────

        $rows = $responses->map(function ($r) use ($scoring, $surveyIncomeByResponseId, $expenseByResponseId, $debtByResponseId, $savingsByResponseId) {
            $h = $r->household;
            $p = $r->person;
            
            // 🆕 คำนวณผลต่างและเปอร์เซ็นต์
            $humanData = $this->calculateCapitalData($r->score_human, $h?->baseline_score_human);
            $physicalData = $this->calculateCapitalData($r->score_physical, $h?->baseline_score_physical);
            $financialData = $this->calculateCapitalData($r->score_financial, $h?->baseline_score_financial);
            $naturalData = $this->calculateCapitalData($r->score_natural, $h?->baseline_score_natural);
            $socialData = $this->calculateCapitalData($r->score_social, $h?->baseline_score_social);
            
            $avgScore = ($r->score_human + $r->score_physical + $r->score_financial + $r->score_natural + $r->score_social) / 5;

            // 🆕 ข้อมูลการเงินส่วนบุคคล
            $incomeBaseline = $p?->baseline_income_monthly !== null ? (float) $p->baseline_income_monthly : '';
            $incomeSurvey   = isset($surveyIncomeByResponseId[$r->id]) ? $surveyIncomeByResponseId[$r->id] : '';
            $expenseTotal   = isset($expenseByResponseId[$r->id])      ? (int) round($expenseByResponseId[$r->id])  : '';
            $debtTotal      = isset($debtByResponseId[$r->id])         ? (int) round($debtByResponseId[$r->id])     : '';
            $savingsTotal   = isset($savingsByResponseId[$r->id])      ? (int) round($savingsByResponseId[$r->id])  : '';
            
            return [
                'house_code'         => $h?->house_code,
                'house_no'           => $h?->house_no,
                'village_no'         => $h?->village_no,
                'village_name'       => $h?->village_name,
                'subdistrict_name'   => $h?->subdistrict_name,
                'district_name'      => $h?->district_name,
                'respondent_name'    => trim(($p?->title ?? '') . ' ' . ($p?->first_name ?? '') . ' ' . ($p?->last_name ?? '')),
                'period'             => $r->period,
                'survey_year'        => $r->survey_year,
                'survey_round'       => $r->survey_round,
                'surveyed_at'        => $r->surveyed_at?->toDateString(),
                'surveyor_name'      => $r->surveyor_name,
                'model_name'         => $r->model_name,

                // 🆕 ทุนมนุษย์
                'baseline_human'     => $humanData['baseline'],
                'score_human'        => $humanData['survey'],
                'human_diff'         => $humanData['diff'],
                'human_percent'      => $humanData['percent'],
                'human_trend'        => $humanData['trend'],

                // 🆕 ทุนกายภาพ
                'baseline_physical'  => $physicalData['baseline'],
                'score_physical'     => $physicalData['survey'],
                'physical_diff'      => $physicalData['diff'],
                'physical_percent'   => $physicalData['percent'],
                'physical_trend'     => $physicalData['trend'],

                // 🆕 ทุนการเงิน
                'baseline_financial' => $financialData['baseline'],
                'score_financial'    => $financialData['survey'],
                'financial_diff'     => $financialData['diff'],
                'financial_percent'  => $financialData['percent'],
                'financial_trend'    => $financialData['trend'],

                // 🆕 ทุนธรรมชาติ
                'baseline_natural'   => $naturalData['baseline'],
                'score_natural'      => $naturalData['survey'],
                'natural_diff'       => $naturalData['diff'],
                'natural_percent'    => $naturalData['percent'],
                'natural_trend'      => $naturalData['trend'],

                // 🆕 ทุนสังคม
                'baseline_social'    => $socialData['baseline'],
                'score_social'       => $socialData['survey'],
                'social_diff'        => $socialData['diff'],
                'social_percent'     => $socialData['percent'],
                'social_trend'       => $socialData['trend'],
                
                'score_aggregate'    => $r->score_aggregate,
                'avg_score'          => round($avgScore, 2),
                'poverty_level'      => $r->poverty_level,
                'poverty_label'      => $r->poverty_level ? $scoring->getPovertyLevelLabel((int) $r->poverty_level) : '',

                // 🆕 ข้อมูลการเงินส่วนบุคคล
                'income_baseline'    => $incomeBaseline,
                'income_survey'      => $incomeSurvey,
                'expense_total'      => $expenseTotal,
                'debt_total'         => $debtTotal,
                'savings_total'      => $savingsTotal,
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

    /**
     * คำนวณข้อมูลทุน: ผลต่าง, เปอร์เซ็นต์, แนวโน้ม (รูปแบบเหมือน frontend)
     * แปลงคะแนน normalized (0-100) เป็น X scale (1.0-4.0)
     */
    private function calculateCapitalData(?float $currentScore, ?float $baselineScore): array
    {
        // 🆕 กรณีไม่มี Survey เลย
        if ($currentScore === null) {
            return [
                'baseline' => $baselineScore !== null ? number_format($baselineScore, 2) : '—',
                'survey' => '—',
                'diff' => '—',
                'percent' => '—',
                'trend' => '—'
            ];
        }
        
        // 🆕 แปลงค่า currentScore จาก normalized (0-100) เป็น X scale (1.0-4.0)
        $currentScoreX = 1.0 + ($currentScore / 100.0) * 3.0;
        $surveyFormatted = number_format($currentScoreX, 2);
        
        // 🆕 กรณีมีแค่ Survey ไม่มี Baseline
        if ($baselineScore === null) {
            return [
                'baseline' => '—',
                'survey' => $surveyFormatted,
                'diff' => '—',
                'percent' => '—',
                'trend' => 'ไม่มีการเปรียบเทียบ เนื่องจากไม่มี Baseline'
            ];
        }
        
        // 🆕 กรณีมีทั้ง Baseline และ Survey
        $diff = $currentScoreX - $baselineScore;
        $percent = $baselineScore > 0 ? ($diff / $baselineScore) * 100 : 0;
        
        // Format baseline และ survey เป็น 2 ทศนิยม
        $baselineFormatted = number_format($baselineScore, 2);
        
        // Format ผลต่าง
        $diffFormatted = $diff > 0 
            ? '+' . number_format($diff, 2) 
            : number_format($diff, 2);
        
        // Format เปอร์เซ็นต์
        $percentFormatted = $percent > 0 
            ? '(+' . number_format($percent, 1) . '%)' 
            : '(' . number_format($percent, 1) . '%)';
        
        // กำหนดแนวโน้ม
        if (abs($diff) < self::TREND_THRESHOLD_PCT) {
            $trend = 'คงที่';
        } elseif ($diff > 0) {
            $trend = 'ดีขึ้น';
        } else {
            $trend = 'แย่ลง';
        }
        
        return [
            'baseline' => $baselineFormatted,
            'survey' => $surveyFormatted,
            'diff' => $diffFormatted,
            'percent' => $percentFormatted,
            'trend' => $trend
        ];
    }
    
}
