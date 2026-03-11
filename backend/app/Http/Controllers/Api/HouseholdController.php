<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Services\CompareHouseholdSurveyLogic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HouseholdController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Household::query();

        // When has_responses=1, only return households that have at least one survey response
        if ($request->boolean('has_responses')) {
            $query->whereHas('surveyResponses');
        }

        if ($request->filled('district')) {
            $query->where('district_name', 'like', '%' . $request->district . '%');
        }

        if ($request->filled('search')) {
            $s = $request->search;
            // For autocomplete purposes, only search by house_code when the search
            // string contains only digits (house codes are numeric only).
            // This prevents non-house-code data (e.g. survey years) from appearing
            // in the house_code suggestion list.
            if (ctype_digit($s)) {
                $query->where('house_code', 'like', "%{$s}%");
            } else {
                $query->where(function ($q) use ($s) {
                    $q->where('house_code', 'like', "%{$s}%")
                      ->orWhere('village_name', 'like', "%{$s}%");
                });
            }
        }

        if ($request->filled('survey_year')) {
            $query->where('survey_year', (int) $request->survey_year);
        }

        $households = $query->orderBy('house_code')
            ->paginate($request->integer('per_page', 20));

        return response()->json($households);
    }

    public function show(Household $household): JsonResponse
    {
        return response()->json($household->load(['persons', 'surveyResponses']));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'house_code'       => 'required|string|unique:households,house_code|max:20',
            'village_name'     => 'nullable|string',
            'village_no'       => 'nullable|string',
            'subdistrict_name' => 'nullable|string',
            'district_name'    => 'nullable|string',
            'province_name'    => 'nullable|string',
            'postal_code'      => 'nullable|string',
            'house_no'         => 'nullable|string',
            'latitude'         => 'nullable|numeric',
            'longitude'        => 'nullable|numeric',
        ]);

        $household = Household::create($validated);

        return response()->json($household, 201);
    }

    public function update(Request $request, Household $household): JsonResponse
    {
        $validated = $request->validate([
            'house_code'       => 'sometimes|string|unique:households,house_code,' . $household->id . '|max:20',
            'village_name'     => 'nullable|string',
            'village_no'       => 'nullable|string',
            'subdistrict_name' => 'nullable|string',
            'district_name'    => 'nullable|string',
            'province_name'    => 'nullable|string',
            'postal_code'      => 'nullable|string',
            'house_no'         => 'nullable|string',
            'latitude'         => 'nullable|numeric',
            'longitude'        => 'nullable|numeric',
        ]);

        $household->update($validated);

        return response()->json($household);
    }

    public function destroy(Household $household): JsonResponse
    {
        $household->delete();

        return response()->json(['message' => 'Deleted']);
    }

    /**
     * Compare Before (legacy import) vs After (survey response) capital scores.
     *
     * GET /api/v1/households/{household}/compare
     *
     * Query params:
     *   survey_year  (int, optional) – filter survey responses by year
     *   survey_round (int, optional) – filter survey responses by round
     *
     * Returns per-capital before/after/diff scores (0–100 normalized),
     * summary averages, X index, and poverty level before/after/diff.
     */
    public function compare(Request $request, Household $household): JsonResponse
    {
        $surveyYear  = $request->filled('survey_year')  ? (int) $request->survey_year  : null;
        $surveyRound = $request->filled('survey_round') ? (int) $request->survey_round : null;

        $logic  = new CompareHouseholdSurveyLogic();
        $result = $logic->compare($household, $surveyYear, $surveyRound);

        return response()->json($result);
    }
}
