<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportLog extends Model
{
    protected $fillable = [
        'user_id',
        'filename',
        'storage_path',
        'imported_count',
        'exists_count',
        'skipped_count',
        'sheet_results',
        'file_size_mb',
        'processing_time',
    ];

    protected $casts = [
        'sheet_results' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
