<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
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
        abort_unless($request->user()?->isAdmin() || $request->user()?->isLeader(), 403);

        $validated = $request->validate([
            'recipient_id' => ['required', Rule::exists('disposition_recipients', 'id')->where('is_active', true)],
            'instruction' => ['required', 'string', 'max:1000'],
        ]);

        $recipient = DispositionRecipient::findOrFail($validated['recipient_id']);
        $disposition = $letter->dispositions()->create([
            'sender_name' => $request->user()->name,
            'recipient_name' => $recipient->name,
            'disposition_recipient_id' => $recipient->id,
            'instruction' => $validated['instruction'],
            'status' => 'Belum Dibaca',
        ]);
        $letter->update(['status' => 'Disposisi']);

        ActivityLog::record('mobile.disposition.created', 'Disposisi dikirim dari Android ke '.$recipient->name, $disposition, [
            'letter_id' => $letter->id,
        ]);

        return response()->json([
            'message' => 'Disposisi berhasil dikirim.',
            'disposition' => MobileApiFormatter::disposition($disposition->fresh('letter.classification', 'children')),
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
        $disposition->letter?->update(['status' => 'Diproses']);

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

        if ($validated['status'] === 'Diproses') {
            $disposition->letter?->update(['status' => 'Diproses']);
        }

        if ($validated['status'] === 'Selesai') {
            $disposition->letter?->update(['status' => 'Selesai']);
        }

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
