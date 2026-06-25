<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DecisionLetterNumber extends Model
{
    protected $fillable = [
        'unit_code',
        'classification_code',
        'sequence',
        'year',
        'number',
        'title',
        'decision_date',
        'status',
        'notes',
        'file_path',
        'file_original_name',
        'file_mime_type',
        'file_size',
        'created_by',
    ];

    protected $casts = [
        'decision_date' => 'date',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
