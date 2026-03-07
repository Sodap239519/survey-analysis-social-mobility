<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends Model
{
    protected $fillable = [
        'survey_response_id', 'question_id',
        'selected_choice_ids', 'value_text', 'value_numeric', 'score',
    ];

    protected $casts = [
        'selected_choice_ids' => 'array',
        'value_numeric' => 'float',
        'score' => 'float',
    ];

    public function surveyResponse(): BelongsTo
    {
        return $this->belongsTo(SurveyResponse::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
