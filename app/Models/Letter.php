<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Letter extends Model
{
    protected $fillable = [
        'type',
        'unit_code',
        'number',
        'subject',
        'external_party',
        'letter_date',
        'file_path',
        'status',
    ];

    protected $casts = [
        'letter_date' => 'date',
    ];

    public function dispositions(): HasMany
    {
        return $this->hasMany(Disposition::class);
    }
}
