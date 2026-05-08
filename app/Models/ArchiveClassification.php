<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArchiveClassification extends Model
{
    protected $fillable = [
        'code',
        'name',
        'parent_code',
        'description',
    ];

    public function letters(): HasMany
    {
        return $this->hasMany(Letter::class, 'classification_code', 'code');
    }
}
