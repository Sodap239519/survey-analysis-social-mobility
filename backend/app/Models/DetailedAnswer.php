<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailedAnswer extends Model
{
    protected $fillable = [
        'survey_response_id',
        'question_code',
        'answer_value',
        'sub_answers',
    ];

    protected $casts = [
        'sub_answers' => 'array',
    ];

    public function surveyResponse(): BelongsTo
    {
        return $this->belongsTo(SurveyResponse::class);
    }
}
