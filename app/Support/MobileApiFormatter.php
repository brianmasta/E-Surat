<?php

namespace App\Support;

use App\Models\Disposition;
use App\Models\Letter;
use App\Models\LetterApproval;
use App\Models\LetterAttachment;
use App\Models\User;

class MobileApiFormatter
{
    public static function user(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'permissions' => [
                'manage_letters' => $user->isAdmin(),
                'dispose_letters' => $user->isAdmin() || $user->isLeader() || $user->isPersonalSecretary(),
                'act_on_dispositions' => $user->isAdmin() || $user->isLeader() || $user->isDepartmentHead(),
                'manage_settings' => $user->isAdmin(),
            ],
        ];
    }

    public static function letter(Letter $letter, bool $detail = false): array
    {
        $data = [
            'id' => $letter->id,
            'type' => $letter->type,
            'unit_code' => $letter->unit_code,
            'classification_code' => $letter->classification_code,
            'classification_name' => $letter->classification?->name,
            'agenda_number' => $letter->agenda_number,
            'number' => $letter->number,
            'subject' => $letter->subject,
            'external_party' => $letter->external_party,
            'letter_date' => $letter->letter_date?->toDateString(),
            'received_date' => $letter->received_date?->toDateString(),
            'nature' => $letter->nature,
            'urgency' => $letter->urgency,
            'due_date' => $letter->due_date?->toDateString(),
            'status' => $letter->status,
            'has_document' => (bool) $letter->file_path,
            'document_url' => $letter->file_path ? route('api.mobile.letters.document', $letter, false) : null,
            'archive_location' => $letter->archive_location,
            'archive_box' => $letter->archive_box,
            'retention_category' => $letter->retention_category,
            'retention_until' => $letter->retention_until?->toDateString(),
            'updated_at' => $letter->updated_at?->toIso8601String(),
        ];

        if (! $detail) {
            return $data;
        }

        return [
            ...$data,
            'outgoing_input_mode' => $letter->outgoing_input_mode,
            'outgoing_body' => $letter->outgoing_body,
            'signer_name' => $letter->signer_name,
            'signer_title' => $letter->signer_title,
            'archive_notes' => $letter->archive_notes,
            'attachments' => $letter->attachments->map(fn (LetterAttachment $attachment) => [
                'id' => $attachment->id,
                'category' => $attachment->category,
                'original_name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
                'review_url' => route('api.mobile.attachments.document', $attachment, false),
            ])->values(),
            'dispositions' => $letter->dispositions->map(fn (Disposition $disposition) => static::disposition($disposition))->values(),
            'approvals' => $letter->approvals->map(fn (LetterApproval $approval) => [
                'id' => $approval->id,
                'step' => $approval->step,
                'target_role' => $approval->target_role,
                'status' => $approval->status,
                'actor_name' => $approval->actor_name,
                'actor_role' => $approval->actor_role,
                'note' => $approval->note,
                'acted_at' => $approval->acted_at?->toIso8601String(),
                'sort_order' => $approval->sort_order,
            ])->values(),
        ];
    }

    public static function disposition(Disposition $disposition): array
    {
        return [
            'id' => $disposition->id,
            'letter_id' => $disposition->letter_id,
            'parent_id' => $disposition->parent_id,
            'sender_name' => $disposition->sender_name,
            'recipient_name' => $disposition->recipient_name,
            'recipient_id' => $disposition->disposition_recipient_id,
            'instruction' => $disposition->instruction,
            'status' => $disposition->status,
            'input_method' => $disposition->input_method,
            'input_by_name' => $disposition->input_by_name,
            'input_by_role' => $disposition->input_by_role,
            'has_scan' => (bool) $disposition->scan_path,
            'scan_original_name' => $disposition->scan_original_name,
            'scan_url' => $disposition->scan_path ? route('api.mobile.dispositions.scan', $disposition, false) : null,
            'created_at' => $disposition->created_at?->toIso8601String(),
            'letter' => $disposition->relationLoaded('letter') && $disposition->letter
                ? static::letter($disposition->letter)
                : null,
            'children' => $disposition->relationLoaded('children')
                ? $disposition->children->map(fn (Disposition $child) => static::disposition($child))->values()
                : [],
        ];
    }
}
