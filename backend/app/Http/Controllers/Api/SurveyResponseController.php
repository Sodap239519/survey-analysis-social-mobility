<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\DetailedAnswer;
use App\Models\Household;
use App\Models\Person;
use App\Models\Question;
use App\Models\SurveyResponse;
use App\Services\CompareHouseholdSurveyLogic;
use App\Services\ScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SurveyResponseController extends Controller
{
    public function __construct(
        private readonly ScoringService $scoring,
        private readonly CompareHouseholdSurveyLogic $compare,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = SurveyResponse::with(['household', 'person'])
            ->latest('surveyed_at')
            ->latest('id');

        if ($request->filled('household_id')) {
            $query->where('household_id', $request->household_id);
        }

        if ($request->filled('period')) {
            $query->where('period', $request->period);
        }

        if ($request->filled('survey_year')) {
            $query->where('survey_year', (int) $request->survey_year);
        }

        if ($request->filled('search')) {
            $search = mb_substr(trim($request->string('search')), 0, 100);
            $query->where(function ($q) use ($search) {
                $q->whereHas('household', fn ($hq) => $hq->where('house_code', 'like', "%{$search}%"))
                  ->orWhereHas('person', fn ($pq) => $pq->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('model_name')) {
            $query->where('model_name', $request->string('model_name')->toString());
        }

        $paginated = $query->paginate($request->integer('per_page', 20));

        // Append comparison data (household is already eager-loaded; no N+1)
        $paginated->getCollection()->transform(function (SurveyResponse $response) {
            $this->appendComparison($response);
            return $response;
        });

        return response()->json($paginated);
    }

    /**
     * Compute and attach baseline-comparison metadata to a SurveyResponse instance.
     *
     * Both before and after scores are expressed in X scale (1.0–4.0) so they can be
     * directly compared with the baseline imported from the XLSX file.
     *
     * Uses household.baseline_score_* (X scale 1-4) as "before" directly.
     * Converts survey score_* (0-100 normalized) to X scale via convertToXScale().
     * Falls back to Household.raw_data legacy columns when baseline_score_* are not set.
     */
    private function appendComparison(SurveyResponse $response): void
    {
        if (! $response->household) {
            return;
        }

        // Get baseline (before) scores in X scale (1.0–4.0) directly
        $beforeScores    = $this->compare->baselineScoresXScale($response->household);
        // Get survey (after) scores in 0-100 normalized scale, then convert to X scale
        $afterScoresNorm = $this->compare->scoresFromResponse($response);

        $comparison = [];
        $diffs      = [];

        foreach (['human', 'physical', 'financial', 'natural', 'social'] as $capital) {
            $before    = $beforeScores[$capital] ?? null;
            $afterNorm = $afterScoresNorm[$capital] ?? null;
            // Convert normalized (0-100) survey score to X scale (1-4)
            $after     = $afterNorm !== null ? $this->compare->convertToXScale($afterNorm) : null;
            $diff      = ($before !== null && $after !== null) ? round($after - $before, 4) : null;
            $percentage = ($before !== null && $before > 0 && $diff !== null)
                ? round(($diff / $before) * 100, 1)
                : null;
            $diffs[] = $diff;

            $trend = null;
            if ($diff !== null) {
                if ($diff > CompareHouseholdSurveyLogic::COMPARISON_THRESHOLD_X) {
                    $trend = 'ดีขึ้น';
                } elseif ($diff < -CompareHouseholdSurveyLogic::COMPARISON_THRESHOLD_X) {
                    $trend = 'แย่ลง';
                } else {
                    $trend = 'คงที่';
                }
            }

            $comparison[$capital] = [
                'before'     => $before,
                'after'      => $after,
                'diff'       => $diff,
                'percentage' => $percentage,
                'trend'      => $trend,
            ];
        }

        $validDiffs = array_filter($diffs, fn ($d) => $d !== null);
        $avgDiff    = count($validDiffs) > 0 ? array_sum($validDiffs) / count($validDiffs) : null;

        $overallStatus = null;
        if ($avgDiff !== null) {
            if ($avgDiff > CompareHouseholdSurveyLogic::COMPARISON_THRESHOLD_X) {
                $overallStatus = 'ดีขึ้น';
            } elseif ($avgDiff < -CompareHouseholdSurveyLogic::COMPARISON_THRESHOLD_X) {
                $overallStatus = 'แย่ลง';
            } else {
                $overallStatus = 'คงที่';
            }
        }

        $response->comparison     = $comparison;
        $response->overall_status = $overallStatus;
    }

    public function show(SurveyResponse $response): JsonResponse
    {
        $response->load(['household', 'person', 'answers.question.choices', 'detailedAnswers']);
        $this->appendComparison($response);

        return response()->json($response);
    }

    public function edit(SurveyResponse $surveyResponse): JsonResponse
    {
        // Load relationships ครบสำหรับการแก้ไข
        $surveyResponse->load([
            'household', 
            'person', 
            'answers.question.capital',
            'answers.question.choices',
            'detailedAnswers'
        ]);
        
        // ไม่ต้อง appendComparison ใน edit เพราะแค่ต้องการข้อมูลเดิม
        
        return response()->json($surveyResponse);
    }

    /**
     * Create a new survey response with answers.
     *
     * Supports two household resolution modes:
     *  (a) Provide "household_id" directly (must exist in households table), OR
     *  (b) Provide "house_code" + optional "household_data" fields – the household
     *      will be looked up by house_code and created automatically if not found.
     *
     * Supports two person resolution modes:
     *  (a) Provide "person_id" directly (must exist in persons table), OR
     *  (b) Provide "person_data" object with at least "citizen_id" or name fields –
     *      the person will be looked up by citizen_id and created if not found.
     *
     * Expected body:
     * {
     *   "house_code": "30010017415",         // preferred: auto-creates household
     *   "household_data": { ... },           // optional extra household fields
     *   "person_data": {                     // optional: auto-creates person
     *     "citizen_id": "1303005244708",
     *     "title": "นาย", "first_name": "...", "last_name": "...", ...
     *   },
     *   "period": "after",
     *   "survey_year": 2568,
     *   "answers": { "1": { "selected_choice_ids": [3, 5] }, ... }
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'household_id'  => 'nullable|integer',
            'house_code'    => 'nullable|string|max:20',
            'household_data'                     => 'nullable|array',
            'household_data.house_no'            => 'nullable|string',
            'household_data.village_no'          => 'nullable|string',
            'household_data.village_name'        => 'nullable|string',
            'household_data.subdistrict_name'    => 'nullable|string',
            'household_data.district_name'       => 'nullable|string',
            'household_data.province_name'       => 'nullable|string',
            'household_data.postal_code'         => 'nullable|string',
            'household_data.latitude'            => 'nullable|numeric',
            'household_data.longitude'           => 'nullable|numeric',
            'person_id'     => 'nullable|integer',
            'person_data'                        => 'nullable|array',
            'person_data.citizen_id'             => 'nullable|string|max:20',
            'person_data.title'                  => 'nullable|string|max:20',
            'person_data.first_name'             => 'nullable|string|max:100',
            'person_data.last_name'              => 'nullable|string|max:100',
            'person_data.birthdate'              => 'nullable|date',
            'person_data.phone'                  => 'nullable|string|max:20',
            'period'        => 'required|in:before,after',
            'survey_year'   => 'nullable|integer',
            'survey_round'  => 'nullable|integer',
            'surveyed_at'   => 'nullable|date',
            'surveyor_name' => 'nullable|string',
            'model_name'    => 'nullable|string|max:255',
            'answers'       => 'nullable|array',
            'answers.*.selected_choice_ids' => 'nullable|array',
            'answers.*.value_text'          => 'nullable|string',
            'answers.*.value_numeric'       => 'nullable|numeric',
            'detailed_answers'              => 'nullable|array',
            'detailed_answers.*.question_code' => 'required_with:detailed_answers.*|string|max:50',
            'detailed_answers.*.answer_value'  => 'nullable|string',
            'detailed_answers.*.sub_answers'   => 'nullable|array',
        ]);

        // ── Resolve household ─────────────────────────────────────────────────
        $householdId = $validated['household_id'] ?? null;
        $houseCode   = $validated['house_code']   ?? null;

        if (! $householdId && $houseCode) {
            $hhData    = $validated['household_data'] ?? [];
            $household = Household::firstOrCreate(
                ['house_code' => $houseCode],
                $hhData
            );
            $householdId = $household->id;
        }

        if (! $householdId) {
            return response()->json(['message' => 'household_id หรือ house_code จำเป็นต้องระบุ'], 422);
        }

        // ── Resolve person ────────────────────────────────────────────────────
        $personId   = $validated['person_id'] ?? null;
        $personData = $validated['person_data'] ?? [];

        if (! $personId && ! empty($personData)) {
            $citizenId = $personData['citizen_id'] ?? null;

            if ($citizenId) {
                $person   = Person::firstOrCreate(
                    ['citizen_id' => $citizenId],
                    array_merge(['household_id' => $householdId], $personData)
                );
                // Update birthdate/phone if provided and not yet set on existing person
                $updateFields = array_filter([
                    'birthdate' => $personData['birthdate'] ?? null,
                    'phone'     => $personData['phone']     ?? null,
                ], fn ($v) => $v !== null && $v !== '');
                if (! $person->wasRecentlyCreated && ! empty($updateFields)) {
                    $person->update($updateFields);
                }
                $personId = $person->id;
            } elseif (! empty($personData['first_name']) || ! empty($personData['last_name'])) {
                // No citizen_id: attempt to match by household + name to avoid duplicates.
                $person = Person::firstOrCreate(
                    [
                        'household_id' => $householdId,
                        'first_name'   => $personData['first_name'] ?? null,
                        'last_name'    => $personData['last_name']  ?? null,
                    ],
                    $personData
                );
                // Update birthdate/phone if provided and not yet set on existing person
                $updateFields = array_filter([
                    'birthdate' => $personData['birthdate'] ?? null,
                    'phone'     => $personData['phone']     ?? null,
                ], fn ($v) => $v !== null && $v !== '');
                if (! $person->wasRecentlyCreated && ! empty($updateFields)) {
                    $person->update($updateFields);
                }
                $personId = $person->id;
            }
        }

        $response = SurveyResponse::create([
            'household_id'  => $householdId,
            'person_id'     => $personId,
            'period'        => $validated['period'],
            'survey_year'   => $validated['survey_year'] ?? null,
            'survey_round'  => $validated['survey_round'] ?? null,
            'surveyed_at'   => $validated['surveyed_at'] ?? null,
            'surveyor_name' => $validated['surveyor_name'] ?? null,
            'model_name'    => $validated['model_name'] ?? null,
        ]);

        if (isset($validated['answers']) && array_is_list($validated['answers'])) {
            $normalized = [];
            foreach ($validated['answers'] as $a) {
                if (isset($a['question_id'])) {
                    $normalized[$a['question_id']] = $a;
                }
            }
            $validated['answers'] = $normalized;
        }

        // Create answer records
        foreach ($validated['answers'] ?? [] as $questionId => $answerData) {
            $question = Question::find($questionId);
            if (!$question) continue;

            Answer::create([
                'survey_response_id' => $response->id,
                'question_id'        => $questionId,
                'selected_choice_ids' => $answerData['selected_choice_ids'] ?? null,
                'value_text'         => $answerData['value_text'] ?? null,
                'value_numeric'      => $answerData['value_numeric'] ?? null,
            ]);
        }

        // Store detailed answers (for complex question data)
        foreach ($validated['detailed_answers'] ?? [] as $detailData) {
            DetailedAnswer::create([
                'survey_response_id' => $response->id,
                'question_code'      => $detailData['question_code'],
                'answer_value'       => $detailData['answer_value'] ?? null,
                'sub_answers'        => $detailData['sub_answers'] ?? null,
            ]);
        }

        // Compute scores
        $response = $this->scoring->computeAndSave($response);

        return response()->json($response->load(['answers.question']), 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        // หา SurveyResponse manually
        $surveyResponse = SurveyResponse::findOrFail($id);
        
        \Log::info('Manual Survey Response:', [
            'id' => $surveyResponse->id,
            'exists' => $surveyResponse->exists
        ]);

        $validated = $request->validate([
            'household_data'                     => 'nullable|array',
            'household_data.house_no'            => 'nullable|string',
            'household_data.village_no'          => 'nullable|string',
            'household_data.village_name'        => 'nullable|string',
            'household_data.subdistrict_name'    => 'nullable|string',
            'household_data.district_name'       => 'nullable|string',
            'household_data.province_name'       => 'nullable|string',
            'household_data.postal_code'         => 'nullable|string',
            'person_data'                        => 'nullable|array',
            'person_data.citizen_id'             => 'nullable|string|max:20',
            'person_data.title'                  => 'nullable|string|max:20',
            'person_data.first_name'             => 'nullable|string|max:100',
            'person_data.last_name'              => 'nullable|string|max:100',
            'person_data.birthdate'              => 'nullable|date',
            'person_data.phone'                  => 'nullable|string|max:20',
            'period'        => 'sometimes|in:before,after',
            'survey_year'   => 'nullable|integer',
            'survey_round'  => 'nullable|integer',
            'surveyed_at'   => 'nullable|date',
            'surveyor_name' => 'nullable|string',
            'model_name'    => 'nullable|string|max:255',
            'answers'       => 'nullable|array',
            'answers.*.selected_choice_ids' => 'nullable|array',
            'answers.*.value_text'          => 'nullable|string',
            'answers.*.value_numeric'       => 'nullable|numeric',
            'detailed_answers'              => 'nullable|array',
            'detailed_answers.*.question_code' => 'required_with:detailed_answers.*|string|max:50',
            'detailed_answers.*.answer_value'  => 'nullable|string',
            'detailed_answers.*.sub_answers'   => 'nullable|array',
        ]);

        // Debug request answers
    \Log::info('Request Answers:', $request->get('answers', []));
    \Log::info('Validated Answers:', $validated['answers'] ?? []);


        // ── Update household data if provided ─────────────────────────────────
        $householdData = $validated['household_data'] ?? [];
        if (! empty($householdData) && $surveyResponse->household) {
            $surveyResponse->household->update(array_filter($householdData, fn ($v) => $v !== null));
        }

        // ── Update person data if provided ────────────────────────────────────
        $personData = $validated['person_data'] ?? [];
        if (! empty($personData) && $surveyResponse->person) {
            $surveyResponse->person->update(array_filter($personData, fn ($v) => $v !== null));
        }

        $surveyResponse->update(collect($validated)->except(['household_data', 'person_data', 'answers', 'detailed_answers'])->toArray());

        foreach ($validated['answers'] ?? [] as $questionId => $answerData) {
            $questionId = (int) $questionId;
            
            Answer::updateOrCreate(
                [
                    'survey_response_id' => $surveyResponse->id,
                    'question_id'        => $questionId,
                ],
                [
                    'selected_choice_ids' => $answerData['selected_choice_ids'] ?? null,
                    'value_text'         => $answerData['value_text'] ?? null,
                    'value_numeric'      => $answerData['value_numeric'] ?? null,
                ]
            );
        }
        // Update detailed answers (upsert by question_code)
        foreach ($validated['detailed_answers'] ?? [] as $detailData) {
            DetailedAnswer::updateOrCreate(
                [
                    'survey_response_id' => $surveyResponse->id,
                    'question_code'      => $detailData['question_code'],
                ],
                [
                    'answer_value' => $detailData['answer_value'] ?? null,
                    'sub_answers'  => $detailData['sub_answers'] ?? null,
                ]
            );
        }

        $surveyResponse = $this->scoring->computeAndSave($surveyResponse);

        return response()->json($surveyResponse->load(['answers.question', 'detailedAnswers']));
    }

    public function destroy(SurveyResponse $surveyResponse): JsonResponse
    {
        $surveyResponse->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
