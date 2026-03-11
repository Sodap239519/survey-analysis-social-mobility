<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\DetailedAnswer;
use App\Models\Question;
use App\Models\SurveyResponse;
use App\Services\ScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SurveyResponseController extends Controller
{
    public function __construct(private readonly ScoringService $scoring) {}

    public function index(Request $request): JsonResponse
    {
        $query = SurveyResponse::with('household', 'person');

        if ($request->filled('household_id')) {
            $query->where('household_id', $request->household_id);
        }

        if ($request->filled('period')) {
            $query->where('period', $request->period);
        }

        if ($request->filled('survey_year')) {
            $query->where('survey_year', (int) $request->survey_year);
        }

        return response()->json($query->paginate($request->integer('per_page', 20)));
    }

    public function show(SurveyResponse $surveyResponse): JsonResponse
    {
        return response()->json($surveyResponse->load(['household', 'person', 'answers.question', 'detailedAnswers']));
    }

    /**
     * Create a new survey response with answers.
     *
     * Expected body:
     * {
     *   "household_id": 1,
     *   "person_id": 1,          // optional
     *   "period": "after",
     *   "survey_year": 2568,
     *   "survey_round": 1,
     *   "surveyed_at": "2025-09-23",
     *   "surveyor_name": "นางสาวโยษิตา",
     *   "answers": {
     *     "1": { "selected_choice_ids": [3, 5] },    // question_id => answer
     *     "2": { "value_text": "some text" },
     *     ...
     *   }
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'household_id'  => 'required|exists:households,id',
            'person_id'     => 'nullable|exists:persons,id',
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

        $response = SurveyResponse::create([
            'household_id'  => $validated['household_id'],
            'person_id'     => $validated['person_id'] ?? null,
            'period'        => $validated['period'],
            'survey_year'   => $validated['survey_year'] ?? null,
            'survey_round'  => $validated['survey_round'] ?? null,
            'surveyed_at'   => $validated['surveyed_at'] ?? null,
            'surveyor_name' => $validated['surveyor_name'] ?? null,
            'model_name'    => $validated['model_name'] ?? null,
        ]);

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

    public function update(Request $request, SurveyResponse $surveyResponse): JsonResponse
    {
        $validated = $request->validate([
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

        $surveyResponse->update(collect($validated)->except(['answers', 'detailed_answers'])->toArray());

        foreach ($validated['answers'] ?? [] as $questionId => $answerData) {
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
