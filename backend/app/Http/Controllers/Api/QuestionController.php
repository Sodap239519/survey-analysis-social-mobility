<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Capital;
use App\Models\Question;
use Illuminate\Http\JsonResponse;

class QuestionController extends Controller
{
    public function index(): JsonResponse
    {
        $capitals = Capital::with(['questions' => function ($q) {
            $q->with('choices')->orderBy('sort_order');
        }])->orderBy('sort_order')->get();

        return response()->json($capitals);
    }

    public function show(Question $question): JsonResponse
    {
        return response()->json($question->load('choices', 'capital'));
    }
}
