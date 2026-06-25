<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AppSetting;
use App\Models\Disposition;
use App\Models\DispositionRecipient;
use App\Models\Letter;
use App\Support\MobileApiFormatter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DispositionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status', 'Semua');

        $dispositions = Disposition::query()
            ->with('letter.classification', 'children')
            ->where(function ($query) {
                $query
                    ->whereNull('letter_id')
                    ->orWhereHas('letter', fn ($query) => $query->where('type', 'Masuk'));
            })
            ->when($user?->isDepartmentHead(), fn ($query) => $query->where('recipient_name', $user->name))
            ->when($status !== 'Semua', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'data' => $dispositions->getCollection()->map(fn (Disposition $disposition) => MobileApiFormatter::disposition($disposition))->values(),
            'meta' => [
                'current_page' => $dispositions->currentPage(),
                'last_page' => $dispositions->lastPage(),
                'per_page' => $dispositions->perPage(),
                'total' => $dispositions->total(),
            ],
        ]);
    }

    public function store(Request $request, Letter $letter)
    {
        abort_unless($request->user()?->isAdmin() || $request->user()?->isLeader() || $request->user()?->isPersonalSecretary(), 403);
        abort_unless($letter->canReceiveDisposition(), 422, 'Disposisi hanya bisa dibuat untuk surat masuk.');

        return $this->storeDispositions($request, $letter);
    }

    public function storeStandalone(Request $request)
    {
        abort_unless($request->user()?->isAdmin() || $request->user()?->isLeader() || $request->user()?->isPersonalSecretary(), 403);

        return $this->storeDispositions($request);
    }

    private function storeDispositions(Request $request, ?Letter $letter = null)
    {
        $validated = $request->validate([
            'recipient_id' => ['nullable', Rule::exists('disposition_recipients', 'id')->where('is_active', true)],
            'recipient_ids' => ['nullable', 'array'],
            'recipient_ids.*' => [Rule::exists('disposition_recipients', 'id')->where('is_active', true)],
            'instruction' => ['required', 'string', 'max:1000'],
            'disposition_scan' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $recipientIds = collect($validated['recipient_ids'] ?? [])
            ->when($validated['recipient_id'] ?? null, fn ($items, $recipientId) => $items->push($recipientId))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($recipientIds === []) {
            return response()->json([
                'message' => 'Pilih minimal satu penerima disposisi.',
                'errors' => ['recipient_ids' => ['Pilih minimal satu penerima disposisi.']],
            ], 422);
        }

        $user = $request->user();
        $senderName = $user?->isPersonalSecretary()
            ? AppSetting::agency()['leader_title']
            : $user?->name;
        $scanData = [];

        if ($request->hasFile('disposition_scan')) {
            $file = $request->file('disposition_scan');
            $scanData = [
                'input_method' => 'Upload File Disposisi',
                'input_by_name' => $user?->name,
                'input_by_role' => $user?->role,
                'scan_path' => $file->store('file-disposisi', 'public'),
                'scan_original_name' => $file->getClientOriginalName(),
                'scan_mime_type' => $file->getMimeType(),
                'scan_size' => $file->getSize(),
            ];
        }

        $dispositions = DispositionRecipient::query()
            ->where('is_active', true)
            ->whereIn('id', $recipientIds)
            ->get()
            ->map(function (DispositionRecipient $recipient) use ($letter, $validated, $senderName, $scanData) {
                $disposition = Disposition::create([
                    'letter_id' => $letter?->id,
                    'sender_name' => $senderName,
                    'recipient_name' => $recipient->name,
                    'disposition_recipient_id' => $recipient->id,
                    'instruction' => $validated['instruction'],
                    'status' => 'Belum Dibaca',
                ] + $scanData);

                ActivityLog::record('mobile.disposition.created', 'Disposisi dikirim dari Android ke '.$recipient->name, $disposition, [
                    'letter_id' => $letter?->id,
                ]);

                return $disposition;
            });

        $letter?->syncDispositionStatus();

        return response()->json([
            'message' => 'Disposisi berhasil dikirim.',
            'disposition' => MobileApiFormatter::disposition($dispositions->first()->fresh('letter.classification', 'children')),
            'dispositions' => $dispositions
                ->map(fn (Disposition $disposition) => MobileApiFormatter::disposition($disposition->fresh('letter.classification', 'children')))
                ->values(),
        ], 201);
    }

    public function forward(Request $request, Disposition $disposition)
    {
        abort_unless($this->canActOnDisposition($request, $disposition), 403);

        $validated = $request->validate([
            'recipient_id' => ['required', Rule::exists('disposition_recipients', 'id')->where('is_active', true)],
            'instruction' => ['required', 'string', 'max:1000'],
        ]);

        $recipient = DispositionRecipient::findOrFail($validated['recipient_id']);
        $forwarded = Disposition::create([
            'letter_id' => $disposition->letter_id,
            'parent_id' => $disposition->id,
            'sender_name' => $request->user()->name,
            'recipient_name' => $recipient->name,
            'disposition_recipient_id' => $recipient->id,
            'instruction' => $validated['instruction'],
            'status' => 'Belum Dibaca',
        ]);

        $disposition->update(['status' => 'Diproses']);
        $disposition->letter?->syncDispositionStatus();

        ActivityLog::record('mobile.disposition.forwarded', 'Disposisi diteruskan dari Android ke '.$recipient->name, $forwarded, [
            'parent_id' => $disposition->id,
            'letter_id' => $disposition->letter_id,
        ]);

        return response()->json([
            'message' => 'Disposisi berhasil diteruskan.',
            'disposition' => MobileApiFormatter::disposition($forwarded->fresh('letter.classification', 'children')),
        ], 201);
    }

    public function updateStatus(Request $request, Disposition $disposition)
    {
        abort_unless($this->canActOnDisposition($request, $disposition), 403);

        $validated = $request->validate([
            'status' => ['required', 'in:Belum Dibaca,Diproses,Selesai'],
        ]);

        $disposition->update(['status' => $validated['status']]);
        $disposition->letter?->syncDispositionStatus();

        ActivityLog::record('mobile.disposition.status_updated', 'Status disposisi diperbarui dari Android.', $disposition, [
            'letter_id' => $disposition->letter_id,
        ]);

        return response()->json([
            'message' => 'Status disposisi diperbarui.',
            'disposition' => MobileApiFormatter::disposition($disposition->fresh('letter.classification', 'children')),
        ]);
    }

    private function canActOnDisposition(Request $request, Disposition $disposition): bool
    {
        $user = $request->user();

        return $user?->isAdmin()
            || $user?->isLeader()
            || ($user?->isDepartmentHead() && $disposition->recipient_name === $user->name);
    }
}
