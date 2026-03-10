<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\HouseholdController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\PersonController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\SurveyResponseController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::prefix('v1')->group(function () {
    // Dashboard - public read-only
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/years', [DashboardController::class, 'years']);

    // Questions / choices - public read-only (for form generation)
    Route::get('/questions', [QuestionController::class, 'index']);
    Route::get('/questions/{question}', [QuestionController::class, 'show']);

    // Auth
    Route::post('/auth/login', [AuthController::class, 'login']);
});

// Protected admin routes
Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Households
    Route::apiResource('households', HouseholdController::class);
    Route::get('/households/{household}/compare', [HouseholdController::class, 'compare'])
        ->name('households.compare');

    // Persons
    Route::apiResource('persons', PersonController::class);

    // Survey responses
    Route::apiResource('responses', SurveyResponseController::class);

    // Import
    Route::post('/import/households', [ImportController::class, 'importHouseholds']);
    Route::get('/import/stats', [ImportController::class, 'stats']);
    Route::get('/import/history', [ImportController::class, 'history']);
    Route::get('/import/history/{id}', [ImportController::class, 'show']);
});
