<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Disposition extends Model
{
    protected $fillable = [
        'letter_id',
        'parent_id',
        'sender_name',
        'recipient_name',
        'disposition_recipient_id',
        'instruction',
        'status',
        'input_method',
        'input_by_name',
        'input_by_role',
        'scan_path',
        'scan_original_name',
        'scan_mime_type',
        'scan_size',
    ];

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(DispositionRecipient::class, 'disposition_recipient_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
