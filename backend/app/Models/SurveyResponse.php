<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyResponse extends Model
{
    /** Thai labels for poverty levels 1–4 */
    public const POVERTY_LEVEL_LABELS = [
        1 => 'อยู่ลำบาก',
        2 => 'อยู่ยาก',
        3 => 'อยู่พอได้',
        4 => 'อยู่ดี',
    ];

    protected $fillable = [
        'household_id', 'person_id', 'period', 'survey_year', 'survey_round',
        'surveyed_at', 'surveyor_name', 'model_name',
        'score_human', 'score_physical', 'score_financial',
        'score_natural', 'score_social', 'score_aggregate',
        'poverty_level', 'raw_data',
    ];

    protected $casts = [
        'surveyed_at' => 'date',
        'raw_data' => 'array',
        'score_human' => 'float',
        'score_physical' => 'float',
        'score_financial' => 'float',
        'score_natural' => 'float',
        'score_social' => 'float',
        'score_aggregate' => 'float',
    ];

    /** Returns the Thai label for this response's poverty level, or null. */
    public function getPovertyLevelLabelAttribute(): ?string
    {
        return self::POVERTY_LEVEL_LABELS[$this->poverty_level] ?? null;
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function detailedAnswers(): HasMany
    {
        return $this->hasMany(DetailedAnswer::class);
    }
}
