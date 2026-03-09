<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportLog extends Model
{
    protected $fillable = [
        'user_id',
        'filename',
        'imported_count',
        'exists_count',
        'skipped_count',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
