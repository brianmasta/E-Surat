<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LetterApproval extends Model
{
    protected $fillable = [
        'letter_id',
        'step',
        'target_role',
        'status',
        'actor_name',
        'actor_role',
        'note',
        'acted_at',
        'sort_order',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
    ];

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }
}
