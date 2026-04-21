<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Person extends Model
{
    protected $table = 'persons';

    protected $fillable = [
        'household_id', 'title', 'first_name', 'last_name',
        'citizen_id', 'birthdate', 'phone', 'is_head',
        'baseline_income_monthly',
    ];

    protected $casts = [
        'is_head'                 => 'boolean',
        'baseline_income_monthly' => 'integer',
    ];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function surveyResponses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }
}
