<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\Person;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Person::with('household');

        // When has_responses=1, only return persons linked to at least one survey response
        if ($request->boolean('has_responses')) {
            $query->whereHas('surveyResponses');
        }

        if ($request->filled('household_id')) {
            $query->where('household_id', $request->household_id);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('first_name', 'like', "%{$s}%")
                  ->orWhere('last_name', 'like', "%{$s}%")
                  ->orWhere('citizen_id', 'like', "%{$s}%");
            });
        }

        if ($request->filled('citizen_id')) {
            $query->where('citizen_id', $request->citizen_id);
        }

        if ($request->filled('house_code')) {
            $query->whereHas('household', fn($q) => $q->where('house_code', $request->house_code));
            $query->orderByDesc('is_head');
        }

        return response()->json($query->paginate($request->integer('per_page', 20)));
    }

    public function show(Person $person): JsonResponse
    {
        return response()->json($person->load('household'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'household_id' => 'required|exists:households,id',
            'title'        => 'nullable|string',
            'first_name'   => 'nullable|string',
            'last_name'    => 'nullable|string',
            'citizen_id'   => 'nullable|string',
            'birthdate'    => 'nullable|date',
            'phone'        => 'nullable|string',
            'is_head'      => 'nullable|boolean',
        ]);

        $person = Person::create($validated);

        return response()->json($person, 201);
    }

    public function update(Request $request, Person $person): JsonResponse
    {
        $validated = $request->validate([
            'title'      => 'nullable|string',
            'first_name' => 'nullable|string',
            'last_name'  => 'nullable|string',
            'citizen_id' => 'nullable|string',
            'birthdate'  => 'nullable|date',
            'phone'      => 'nullable|string',
            'is_head'    => 'nullable|boolean',
        ]);

        $person->update($validated);

        return response()->json($person);
    }

    public function destroy(Person $person): JsonResponse
    {
        $person->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
