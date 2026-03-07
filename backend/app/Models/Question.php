<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    protected $fillable = [
        'capital_id', 'question_key', 'text_th', 'type',
        'max_score', 'has_exclusive_option', 'meta', 'sort_order',
    ];

    protected $casts = [
        'has_exclusive_option' => 'boolean',
        'meta' => 'array',
    ];

    public function capital(): BelongsTo
    {
        return $this->belongsTo(Capital::class);
    }

    public function choices(): HasMany
    {
        return $this->hasMany(Choice::class)->orderBy('sort_order');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }
}
