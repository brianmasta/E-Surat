<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Letter;
use App\Models\LetterApproval;
use App\Support\MobileApiFormatter;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function start(Request $request, Letter $letter)
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($letter->type === 'Keluar', 403);

        if ($letter->approvals()->count() === 0) {
            foreach ($this->approvalSteps() as $step) {
                $letter->approvals()->create($step);
            }
        }

        $letter->update(['status' => 'Menunggu Paraf']);
        ActivityLog::record('mobile.letter.signature_workflow_started', 'Alur paraf dimulai dari Android: '.$letter->number, $letter);

        return response()->json([
            'message' => 'Alur paraf dan tanda tangan dimulai.',
            'letter' => MobileApiFormatter::letter($letter->fresh(['classification', 'attachments', 'approvals', 'dispositions']), true),
        ]);
    }

    public function approve(Request $request, LetterApproval $approval)
    {
        abort_unless($this->canActOnApproval($request, $approval), 403);
        abort_unless($approval->status === 'Menunggu', 422);

        $user = $request->user();
        $approval->update([
            'status' => $approval->step === 'Tanda Tangan Elektronik' ? 'Ditandatangani' : 'Disetujui',
            'actor_name' => $user->name,
            'actor_role' => $user->role,
            'acted_at' => now(),
        ]);
        $approval->letter->update(['status' => $this->nextSignatureStatus($approval)]);

        ActivityLog::record('mobile.letter.approval_completed', $approval->step.' selesai dari Android.', $approval, [
            'letter_id' => $approval->letter_id,
        ]);

        return response()->json([
            'message' => $approval->step.' berhasil diproses.',
            'letter' => MobileApiFormatter::letter($approval->letter->fresh(['classification', 'attachments', 'approvals', 'dispositions']), true),
        ]);
    }

    public function reject(Request $request, LetterApproval $approval)
    {
        abort_unless($this->canActOnApproval($request, $approval), 403);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $approval->update([
            'status' => 'Ditolak',
            'actor_name' => $user->name,
            'actor_role' => $user->role,
            'note' => $validated['note'],
            'acted_at' => now(),
        ]);
        $approval->letter->update(['status' => 'Revisi Konsep']);

        ActivityLog::record('mobile.letter.approval_rejected', $approval->step.' meminta revisi dari Android.', $approval, [
            'letter_id' => $approval->letter_id,
        ]);

        return response()->json([
            'message' => 'Konsep surat dikembalikan untuk revisi.',
            'letter' => MobileApiFormatter::letter($approval->letter->fresh(['classification', 'attachments', 'approvals', 'dispositions']), true),
        ]);
    }

    private function approvalSteps(): array
    {
        return [
            ['step' => 'Paraf Konsep', 'target_role' => 'Kepala Bagian', 'status' => 'Menunggu', 'sort_order' => 1],
            ['step' => 'Persetujuan Pimpinan', 'target_role' => 'Pimpinan MRP', 'status' => 'Menunggu', 'sort_order' => 2],
            ['step' => 'Tanda Tangan Elektronik', 'target_role' => 'Pimpinan MRP', 'status' => 'Menunggu', 'sort_order' => 3],
        ];
    }

    private function canActOnApproval(Request $request, LetterApproval $approval): bool
    {
        $approval->loadMissing('letter.approvals');
        $user = $request->user();

        if (! $user || $approval->status !== 'Menunggu') {
            return false;
        }

        $previousPending = $approval->letter
            ->approvals
            ->where('sort_order', '<', $approval->sort_order)
            ->contains(fn (LetterApproval $item) => in_array($item->status, ['Menunggu', 'Ditolak'], true));

        if ($previousPending) {
            return false;
        }

        return match ($approval->step) {
            'Paraf Konsep' => $user->isAdmin() || $user->isDepartmentHead(),
            'Persetujuan Pimpinan', 'Tanda Tangan Elektronik' => $user->isAdmin() || $user->isLeader(),
            default => false,
        };
    }

    private function nextSignatureStatus(LetterApproval $approval): string
    {
        return match ($approval->step) {
            'Paraf Konsep' => 'Menunggu Persetujuan',
            'Persetujuan Pimpinan' => 'Menunggu Tanda Tangan',
            'Tanda Tangan Elektronik' => 'Selesai',
            default => $approval->letter->status,
        };
    }
}
