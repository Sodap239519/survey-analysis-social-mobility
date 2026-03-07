<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Household extends Model
{
    protected $fillable = [
        'house_code', 'village_name', 'village_no', 'subdistrict_code',
        'subdistrict_name', 'district_code', 'district_name', 'province_code',
        'province_name', 'postal_code', 'house_no', 'road', 'alley',
        'latitude', 'longitude', 'survey_year', 'survey_round', 'raw_data',
    ];

    protected $casts = [
        'raw_data' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function persons(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    public function surveyResponses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }
}
