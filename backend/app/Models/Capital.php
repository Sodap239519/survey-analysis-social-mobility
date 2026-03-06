<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Capital extends Model
{
    protected $fillable = ['slug', 'name_th', 'name_en', 'max_score', 'sort_order'];

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }
}
