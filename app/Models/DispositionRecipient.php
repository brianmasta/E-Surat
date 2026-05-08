<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DispositionRecipient extends Model
{
    protected $fillable = [
        'name',
        'position',
        'unit',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function dispositions(): HasMany
    {
        return $this->hasMany(Disposition::class);
    }
}
