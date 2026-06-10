<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AppSetting;
use App\Models\Letter;
use App\Support\LetterNumbering;
use App\Support\MobileApiFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class LetterController extends Controller
{
    public function index(Request $request)
    {
        $filters = [
            'search' => $request->query('search', ''),
            'type' => $request->query('type', 'Semua'),
            'unit' => $request->query('unit', 'Semua'),
            'status' => $request->query('status', 'Semua'),
            'urgency' => $request->query('urgency', 'Semua'),
            'due' => $request->query('due', 'Semua'),
            'date_from' => $request->query('date_from', ''),
            'date_to' => $request->query('date_to', ''),
        ];

        $letters = Letter::query()
            ->with('classification')
            ->applyDashboardFilters($filters)
            ->latest('letter_date')
            ->latest()
            ->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'data' => $letters->getCollection()->map(fn (Letter $letter) => MobileApiFormatter::letter($letter))->values(),
            'meta' => [
                'current_page' => $letters->currentPage(),
                'last_page' => $letters->lastPage(),
                'per_page' => $letters->perPage(),
                'total' => $letters->total(),
            ],
        ]);
    }

    public function show(Letter $letter)
    {
        $letter->load('classification', 'attachments', 'approvals', 'dispositions.children');

        return response()->json([
            'letter' => MobileApiFormatter::letter($letter, true),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'type' => ['required', 'in:Masuk,Keluar'],
            'unit_code' => ['nullable', Rule::in(AppSetting::letterUnitCodes())],
            'classification_code' => ['nullable', 'exists:archive_classifications,code'],
            'agenda_number' => ['nullable', 'string', 'max:100', 'unique:letters,agenda_number'],
            'number' => ['nullable', 'string', 'max:100', 'unique:letters,number'],
            'subject' => ['required', 'string', 'max:255'],
            'outgoing_input_mode' => ['nullable', 'in:template,upload'],
            'outgoing_body' => ['nullable', 'string', 'max:5000'],
            'signer_name' => ['nullable', 'string', 'max:255'],
            'signer_title' => ['nullable', 'string', 'max:255'],
            'external_party' => ['required', 'string', 'max:255'],
            'letter_date' => ['required', 'date'],
            'received_date' => ['nullable', 'required_if:type,Masuk', 'date'],
            'nature' => ['nullable', 'in:Biasa,Penting,Rahasia,Sangat Rahasia'],
            'urgency' => ['nullable', 'in:Normal,Segera,Sangat Segera'],
            'due_date' => ['nullable', 'date', 'after_or_equal:received_date'],
            'archive_location' => ['nullable', 'string', 'max:255'],
            'archive_box' => ['nullable', 'string', 'max:100'],
            'retention_category' => ['nullable', 'in:Aktif,Inaktif,Permanen,Siap Musnah,Dimusnahkan'],
            'retention_until' => ['nullable', 'date'],
            'archive_notes' => ['nullable', 'string', 'max:1000'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'attachments.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'memos.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'supporting_files.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $unitCode = LetterNumbering::normalizeUnitCode($validated['unit_code'] ?? null);
        $number = $validated['number'] ?? (
            $validated['type'] === 'Keluar'
                ? LetterNumbering::nextLetterNumber($unitCode, $validated['classification_code'] ?? null)
                : 'SM/'.$unitCode.'/'.now()->format('YmdHis')
        );

        $filePath = $request->file('document')?->store('dokumen-surat', 'public');
        $outgoingMode = $validated['type'] === 'Keluar' ? ($validated['outgoing_input_mode'] ?? 'template') : null;

        $letter = Letter::create([
            'type' => $validated['type'],
            'outgoing_input_mode' => $outgoingMode,
            'unit_code' => $unitCode,
            'classification_code' => $validated['classification_code'] ?? null,
            'agenda_number' => $validated['type'] === 'Masuk'
                ? ($validated['agenda_number'] ?? LetterNumbering::nextAgendaNumber($unitCode))
                : null,
            'number' => $number,
            'subject' => $validated['subject'],
            'outgoing_body' => $validated['type'] === 'Keluar' && $outgoingMode === 'template'
                ? ($validated['outgoing_body'] ?? LetterNumbering::defaultOutgoingBody())
                : null,
            'signer_name' => $validated['signer_name'] ?? null,
            'signer_title' => $validated['signer_title'] ?? AppSetting::agency()['leader_title'],
            'external_party' => $validated['external_party'],
            'letter_date' => $validated['letter_date'],
            'received_date' => $validated['type'] === 'Masuk' ? $validated['received_date'] : null,
            'nature' => $validated['nature'] ?? 'Biasa',
            'urgency' => $validated['urgency'] ?? 'Normal',
            'due_date' => $validated['due_date'] ?? null,
            'archive_location' => $validated['archive_location'] ?? null,
            'archive_box' => $validated['archive_box'] ?? null,
            'retention_category' => $validated['retention_category'] ?? 'Aktif',
            'retention_until' => $validated['retention_until'] ?? null,
            'archive_notes' => $validated['archive_notes'] ?? null,
            'file_path' => $filePath,
            'status' => $validated['type'] === 'Masuk' ? 'Baru' : 'Selesai',
        ]);

        if ($validated['type'] === 'Keluar') {
            LetterNumbering::advanceNextLetterSequence($number);
        }

        $this->storeFiles($letter, 'Lampiran', $request->file('attachments', []));
        $this->storeFiles($letter, 'Nota Dinas', $request->file('memos', []));
        $this->storeFiles($letter, 'Dokumen Pendukung', $request->file('supporting_files', []));

        ActivityLog::record('mobile.letter.created', 'Surat dibuat dari Android: '.$letter->number, $letter);

        return response()->json([
            'message' => 'Surat berhasil dicatat.',
            'letter' => MobileApiFormatter::letter($letter->fresh(['classification', 'attachments', 'approvals', 'dispositions']), true),
        ], 201);
    }

    public function updateStatus(Request $request, Letter $letter)
    {
        abort_unless($request->user()?->isAdmin() || $request->user()?->isStaff(), 403);

        $validated = $request->validate([
            'status' => ['required', 'in:Baru,Disposisi,Diproses,Selesai'],
        ]);

        $letter->update(['status' => $validated['status']]);
        ActivityLog::record('mobile.letter.status_updated', 'Status surat diperbarui dari Android: '.$letter->number, $letter);

        return response()->json([
            'message' => 'Status surat diperbarui.',
            'letter' => MobileApiFormatter::letter($letter->fresh('classification')),
        ]);
    }

    public function uploadDocument(Request $request, Letter $letter)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        if ($letter->file_path && Storage::disk('public')->exists($letter->file_path)) {
            Storage::disk('public')->delete($letter->file_path);
        }

        $letter->update([
            'file_path' => $validated['document']->store('dokumen-surat', 'public'),
        ]);

        ActivityLog::record('mobile.letter.document_uploaded', 'Dokumen surat diunggah dari Android: '.$letter->number, $letter);

        return response()->json([
            'message' => 'Dokumen berhasil diunggah.',
            'letter' => MobileApiFormatter::letter($letter->fresh('classification')),
        ]);
    }

    private function storeFiles(Letter $letter, string $category, array $files): void
    {
        foreach ($files as $file) {
            if (! $file) {
                continue;
            }

            $letter->attachments()->create([
                'category' => $category,
                'original_name' => $file->getClientOriginalName(),
                'file_path' => $file->store('lampiran-surat', 'public'),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }
}
