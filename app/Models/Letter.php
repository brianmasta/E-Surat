<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Letter extends Model
{
    protected $fillable = [
        'type',
        'outgoing_input_mode',
        'unit_code',
        'classification_code',
        'agenda_number',
        'number',
        'subject',
        'outgoing_body',
        'signer_name',
        'signer_title',
        'external_party',
        'letter_date',
        'received_date',
        'nature',
        'urgency',
        'due_date',
        'archive_location',
        'archive_box',
        'retention_category',
        'retention_until',
        'archive_notes',
        'file_path',
        'status',
    ];

    protected $casts = [
        'letter_date' => 'date',
        'received_date' => 'date',
        'due_date' => 'date',
        'retention_until' => 'date',
    ];

    public function dispositions(): HasMany
    {
        return $this->hasMany(Disposition::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(LetterAttachment::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(LetterApproval::class)->orderBy('sort_order');
    }

    public function classification(): BelongsTo
    {
        return $this->belongsTo(ArchiveClassification::class, 'classification_code', 'code');
    }

    public function scopeApplyDashboardFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(($filters['type'] ?? 'Semua') !== 'Semua', fn (Builder $query) => $query->where('type', $filters['type']))
            ->when(($filters['unit'] ?? 'Semua') !== 'Semua', fn (Builder $query) => $query->where('unit_code', $filters['unit']))
            ->when(($filters['status'] ?? 'Semua') !== 'Semua', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(($filters['urgency'] ?? 'Semua') !== 'Semua', fn (Builder $query) => $query->where('urgency', $filters['urgency']))
            ->when(($filters['date_from'] ?? '') !== '', fn (Builder $query) => $query->whereDate('letter_date', '>=', $filters['date_from']))
            ->when(($filters['date_to'] ?? '') !== '', fn (Builder $query) => $query->whereDate('letter_date', '<=', $filters['date_to']))
            ->when(($filters['due'] ?? 'Semua') === 'Lewat Batas', function (Builder $query) {
                $query
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<', today())
                    ->where('status', '!=', 'Selesai');
            })
            ->when(($filters['due'] ?? 'Semua') === 'Dekat Tenggat', function (Builder $query) {
                $query
                    ->whereNotNull('due_date')
                    ->whereBetween('due_date', [today(), today()->addDays(3)])
                    ->where('status', '!=', 'Selesai');
            })
            ->when(($filters['due'] ?? 'Semua') === 'Tanpa Batas', fn (Builder $query) => $query->whereNull('due_date'))
            ->when(($filters['search'] ?? '') !== '', function (Builder $query) use ($filters) {
                $search = '%'.$filters['search'].'%';

                $query->where(function (Builder $query) use ($search) {
                    $query
                        ->where('number', 'like', $search)
                        ->orWhere('agenda_number', 'like', $search)
                        ->orWhere('unit_code', 'like', $search)
                        ->orWhere('classification_code', 'like', $search)
                        ->orWhere('subject', 'like', $search)
                        ->orWhere('external_party', 'like', $search)
                        ->orWhere('nature', 'like', $search)
                        ->orWhere('urgency', 'like', $search)
                        ->orWhere('status', 'like', $search)
                        ->orWhereHas('classification', function (Builder $query) use ($search) {
                            $query->where('name', 'like', $search);
                        });
                });
            });
    }
}
