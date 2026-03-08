<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Household;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HouseholdController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Household::query();

        if ($request->filled('district')) {
            $query->where('district_name', 'like', '%' . $request->district . '%');
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('house_code', 'like', "%{$s}%")
                  ->orWhere('village_name', 'like', "%{$s}%");
            });
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
}
