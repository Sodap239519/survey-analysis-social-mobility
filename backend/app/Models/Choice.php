<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Choice extends Model
{
    protected $fillable = [
        'question_id', 'choice_key', 'text_th', 'weight', 'is_exclusive', 'sort_order',
    ];

    protected $casts = [
        'weight' => 'float',
        'is_exclusive' => 'boolean',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
