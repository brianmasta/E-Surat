<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Disposition extends Model
{
    protected $fillable = [
        'letter_id',
        'sender_name',
        'recipient_name',
        'instruction',
        'status',
    ];

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }
}
