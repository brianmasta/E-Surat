<?php

use App\Models\AppSetting;
use App\Models\ActivityLog;
use App\Models\ArchiveClassification;
use App\Models\DispositionRecipient;
use App\Models\Letter;
use App\Models\LetterApproval;
use App\Support\TaskInbox;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component
{
    use WithFileUploads;
    use WithPagination;

    public string $search = '';

    public string $typeFilter = 'Semua';

    public string $unitFilter = 'Semua';

    public string $statusFilter = 'Semua';

    public string $urgencyFilter = 'Semua';

    public string $dueFilter = 'Semua';

    public string $dateFrom = '';

    public string $dateTo = '';

    public int $perPage = 10;

    public ?int $selectedLetterId = null;

    public ?int $editingLetterId = null;

    public bool $showDetailModal = false;

    public bool $showLetterForm = false;

    public bool $showDispositionForm = false;

    public string $type = 'Masuk';

    public string $unitCode = 'SET-MRP';

    public string $classificationCode = '';

    public string $agendaNumber = '';

    public string $number = '';

    public string $subject = '';

    public string $outgoingInputMode = 'template';

    public string $outgoingBody = '';

    public string $signerName = '';

    public string $signerTitle = '';

    public string $externalParty = '';

    public string $letterDate = '';

    public string $receivedDate = '';

    public string $nature = 'Biasa';

    public string $urgency = 'Normal';

    public string $dueDate = '';

    public string $archiveLocation = '';

    public string $archiveBox = '';

    public string $retentionCategory = 'Aktif';

    public string $retentionUntil = '';

    public string $archiveNotes = '';

    public $document = null;

    public $outgoingDocument = null;

    public array $attachmentFiles = [];

    public array $memoFiles = [];

    public array $supportingFiles = [];

    public ?int $recipientId = null;

    public array $recipientIds = [];

    public string $instruction = '';

    public $dispositionScan = null;

    public ?int $revisionApprovalId = null;

    public string $revisionNote = '';

    public bool $showRevisionModal = false;

    public function mount(): void
    {
        $unitCodes = $this->unitCodes();
        $this->letterDate = now()->toDateString();
        $this->typeFilter = in_array(request('filter'), ['Semua', 'Masuk', 'Keluar'], true)
            ? request('filter')
            : 'Semua';
        $this->unitFilter = in_array(request('unit'), ['Semua', ...$unitCodes], true)
            ? request('unit')
            : 'Semua';
        $this->selectedLetterId = Letter::latest('letter_date')->value('id');

        if (request('create') === 'Keluar' && $this->canManageLetters()) {
            $requestedUnit = is_string(request('unit')) ? request('unit') : null;
            $this->openLetterForm('Keluar', $requestedUnit);

            if (is_string(request('number')) && trim(request('number')) !== '') {
                $this->number = trim(request('number'));
            }

            if (is_string(request('letter_date')) && preg_match('/^\d{4}-\d{2}-\d{2}$/', request('letter_date'))) {
                $this->letterDate = request('letter_date');
            }
        }
    }

    public function letters()
    {
        return Letter::query()
            ->with('classification', 'dispositions')
            ->applyDashboardFilters($this->dashboardFilters())
            ->latest('letter_date')
            ->latest()
            ->paginate($this->perPage);
    }

    public function dashboardFilters(): array
    {
        return [
            'search' => $this->search,
            'type' => $this->typeFilter,
            'unit' => $this->unitFilter,
            'status' => $this->statusFilter,
            'urgency' => $this->urgencyFilter,
            'due' => $this->dueFilter,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ];
    }

    public function exportUrl(): string
    {
        return route('letters.export', array_filter($this->dashboardFilters(), fn ($value) => $value !== '' && $value !== 'Semua'));
    }

    public function updated(string $property, mixed $value = null): void
    {
        if (in_array($property, ['search', 'statusFilter', 'urgencyFilter', 'dueFilter', 'dateFrom', 'dateTo'], true)) {
            $this->resetPage();
        }
    }

    public function selectedLetter(): ?Letter
    {
        return Letter::with('classification', 'attachments', 'approvals', 'dispositions.children')->find($this->selectedLetterId);
    }

    public function editingLetter(): ?Letter
    {
        return $this->editingLetterId ? Letter::find($this->editingLetterId) : null;
    }

    public function archiveClassifications()
    {
        return ArchiveClassification::query()
            ->orderBy('code')
            ->get(['code', 'name']);
    }

    public function dispositionRecipients()
    {
        return DispositionRecipient::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function units(): array
    {
        return AppSetting::letterUnits();
    }

    public function unitCodes(): array
    {
        return AppSetting::letterUnitCodes();
    }

    public function defaultUnitCode(): string
    {
        return AppSetting::defaultLetterUnitCode();
    }

    public function normalizeUnitCode(?string $unitCode = null): string
    {
        return in_array($unitCode, $this->unitCodes(), true) ? $unitCode : $this->defaultUnitCode();
    }

    public function unitSummaries(): array
    {
        return collect($this->units())
            ->map(fn (array $unit) => [
                ...$unit,
                'incoming_count' => Letter::where('unit_code', $unit['code'])->where('type', 'Masuk')->count(),
                'outgoing_count' => Letter::where('unit_code', $unit['code'])->where('type', 'Keluar')->count(),
            ])
            ->values()
            ->all();
    }

    public function setFilter(string $filter): void
    {
        $this->typeFilter = $filter;
        $this->resetPage();
    }

    public function setUnitFilter(string $filter): void
    {
        $this->unitFilter = $filter;
        $this->resetPage();
    }

    public function resetAdvancedFilters(): void
    {
        $this->statusFilter = 'Semua';
        $this->urgencyFilter = 'Semua';
        $this->dueFilter = 'Semua';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->resetPage();
    }

    public function setCombinedFilter(string $typeFilter, string $unitFilter): void
    {
        $this->typeFilter = $typeFilter;
        $this->unitFilter = $unitFilter;
        $this->resetPage();
    }

    public function selectLetter(int $letterId): void
    {
        $this->selectedLetterId = $letterId;
    }

    public function openDetailModal(int $letterId): void
    {
        $this->selectedLetterId = $letterId;
        $this->outgoingDocument = null;
        $this->showDetailModal = true;
    }

    public function openLetterForm(string $type = 'Masuk', ?string $unitCode = null): void
    {
        abort_unless($this->canManageLetters(), 403);

        $this->resetValidation();
        $this->editingLetterId = null;
        $this->type = $type;
        $this->unitCode = $this->normalizeUnitCode($unitCode);
        $this->classificationCode = '';
        $this->agendaNumber = $type === 'Masuk' ? $this->nextAgendaNumber($this->unitCode) : '';
        $this->number = $type === 'Keluar' ? $this->nextLetterNumber($this->unitCode, $this->classificationCode) : '';
        $this->subject = '';
        $this->outgoingInputMode = 'template';
        $this->outgoingBody = $type === 'Keluar' ? $this->defaultOutgoingBody() : '';
        $this->signerName = '';
        $this->signerTitle = AppSetting::agency()['leader_title'];
        $this->externalParty = '';
        $this->letterDate = now()->toDateString();
        $this->receivedDate = now()->toDateString();
        $this->nature = 'Biasa';
        $this->urgency = 'Normal';
        $this->dueDate = '';
        $this->archiveLocation = '';
        $this->archiveBox = '';
        $this->retentionCategory = 'Aktif';
        $this->retentionUntil = '';
        $this->archiveNotes = '';
        $this->document = null;
        $this->attachmentFiles = [];
        $this->memoFiles = [];
        $this->supportingFiles = [];
        $this->showLetterForm = true;
    }

    public function openEditLetterForm(int $letterId): void
    {
        abort_unless($this->canEditLetters(), 403);

        $letter = Letter::findOrFail($letterId);

        $this->resetValidation();
        $this->editingLetterId = $letter->id;
        $this->type = $letter->type;
        $this->unitCode = $letter->unit_code;
        $this->classificationCode = $letter->classification_code ?? '';
        $this->agendaNumber = $letter->agenda_number ?? '';
        $this->number = $letter->number;
        $this->subject = $letter->subject;
        $this->outgoingInputMode = $letter->outgoing_input_mode ?: 'template';
        $this->outgoingBody = $letter->outgoing_body ?? ($letter->type === 'Keluar' ? $this->defaultOutgoingBody() : '');
        $this->signerName = $letter->signer_name ?? '';
        $this->signerTitle = $letter->signer_title ?? AppSetting::agency()['leader_title'];
        $this->externalParty = $letter->external_party;
        $this->letterDate = $letter->letter_date?->toDateString() ?? now()->toDateString();
        $this->receivedDate = $letter->received_date?->toDateString() ?? now()->toDateString();
        $this->nature = $letter->nature;
        $this->urgency = $letter->urgency;
        $this->dueDate = $letter->due_date?->toDateString() ?? '';
        $this->archiveLocation = $letter->archive_location ?? '';
        $this->archiveBox = $letter->archive_box ?? '';
        $this->retentionCategory = $letter->retention_category ?? 'Aktif';
        $this->retentionUntil = $letter->retention_until?->toDateString() ?? '';
        $this->archiveNotes = $letter->archive_notes ?? '';
        $this->document = null;
        $this->attachmentFiles = [];
        $this->memoFiles = [];
        $this->supportingFiles = [];
        $this->showDetailModal = false;
        $this->showLetterForm = true;
    }

    public function updatedType(string $value): void
    {
        if ($value === 'Keluar' && $this->number === '') {
            $this->number = $this->nextLetterNumber($this->unitCode, $this->classificationCode);
        }

        if ($value === 'Keluar' && $this->outgoingBody === '') {
            $this->outgoingInputMode = 'template';
            $this->outgoingBody = $this->defaultOutgoingBody();
        }

        if ($value === 'Masuk' && $this->agendaNumber === '') {
            $this->agendaNumber = $this->nextAgendaNumber($this->unitCode);
        }
    }

    public function updatedOutgoingInputMode(string $value): void
    {
        if ($value === 'template' && $this->outgoingBody === '') {
            $this->outgoingBody = $this->defaultOutgoingBody();
        }
    }

    public function updatedUnitCode(string $value): void
    {
        if ($this->editingLetterId) {
            return;
        }

        if ($this->type === 'Keluar') {
            $this->number = $this->nextLetterNumber($value, $this->classificationCode);
        }

        if ($this->type === 'Masuk') {
            $this->agendaNumber = $this->nextAgendaNumber($value);
        }
    }

    public function updatedClassificationCode(string $value): void
    {
        if ($this->editingLetterId) {
            return;
        }

        if ($this->type === 'Keluar') {
            $this->number = $this->nextLetterNumber($this->unitCode, $value);
        }
    }

    public function saveLetter(): void
    {
        abort_unless($this->canManageLetters(), 403);

        $editingLetter = $this->editingLetterId ? Letter::findOrFail($this->editingLetterId) : null;

        $validated = $this->validate([
            'type' => ['required', 'in:Masuk,Keluar'],
            'unitCode' => ['required', Rule::in($this->unitCodes())],
            'classificationCode' => ['nullable', 'exists:archive_classifications,code'],
            'agendaNumber' => ['nullable', 'required_if:type,Masuk', 'string', 'max:100', Rule::unique('letters', 'agenda_number')->ignore($this->editingLetterId)],
            'number' => ['nullable', 'string', 'max:100', Rule::unique('letters', 'number')->ignore($this->editingLetterId)],
            'subject' => ['required', 'string', 'max:255'],
            'outgoingInputMode' => ['required', 'in:template,upload'],
            'outgoingBody' => ['nullable', Rule::requiredIf($this->type === 'Keluar' && $this->outgoingInputMode === 'template'), 'string', 'max:5000'],
            'signerName' => ['nullable', 'string', 'max:255'],
            'signerTitle' => ['nullable', 'string', 'max:255'],
            'externalParty' => ['required', 'string', 'max:255'],
            'letterDate' => ['required', 'date'],
            'receivedDate' => ['nullable', 'required_if:type,Masuk', 'date'],
            'nature' => ['required', 'in:Biasa,Penting,Rahasia,Sangat Rahasia'],
            'urgency' => ['required', 'in:Normal,Segera,Sangat Segera'],
            'dueDate' => ['nullable', 'date', 'after_or_equal:receivedDate'],
            'archiveLocation' => ['nullable', 'string', 'max:255'],
            'archiveBox' => ['nullable', 'string', 'max:100'],
            'retentionCategory' => ['required', 'in:Aktif,Inaktif,Permanen,Siap Musnah,Dimusnahkan'],
            'retentionUntil' => ['nullable', 'date'],
            'archiveNotes' => ['nullable', 'string', 'max:1000'],
            'document' => ['nullable', Rule::requiredIf($this->type === 'Keluar' && $this->outgoingInputMode === 'upload' && ! $editingLetter?->file_path), 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'attachmentFiles.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'memoFiles.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'supportingFiles.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $number = $validated['number'] ?: ($validated['type'] === 'Keluar' ? $this->nextLetterNumber($validated['unitCode'], $validated['classificationCode'] ?? null) : 'SM/'.$validated['unitCode'].'/'.now()->format('YmdHis'));
        $filePath = $editingLetter?->file_path;

        if ($this->document) {
            if ($editingLetter?->file_path && Storage::disk('public')->exists($editingLetter->file_path)) {
                Storage::disk('public')->delete($editingLetter->file_path);
            }

            $filePath = $this->document->store('dokumen-surat', 'public');
        }

        $data = [
            'type' => $validated['type'],
            'outgoing_input_mode' => $validated['type'] === 'Keluar' ? $validated['outgoingInputMode'] : null,
            'unit_code' => $validated['unitCode'],
            'classification_code' => $validated['classificationCode'] ?: null,
            'agenda_number' => $validated['type'] === 'Masuk' ? $validated['agendaNumber'] : null,
            'number' => $number,
            'subject' => $validated['subject'],
            'outgoing_body' => $validated['type'] === 'Keluar' && $validated['outgoingInputMode'] === 'template' ? $validated['outgoingBody'] : null,
            'signer_name' => $validated['type'] === 'Keluar' && $validated['outgoingInputMode'] === 'template' ? ($validated['signerName'] ?: null) : null,
            'signer_title' => $validated['type'] === 'Keluar' && $validated['outgoingInputMode'] === 'template' ? ($validated['signerTitle'] ?: null) : null,
            'external_party' => $validated['externalParty'],
            'letter_date' => $validated['letterDate'],
            'received_date' => $validated['type'] === 'Masuk' ? $validated['receivedDate'] : null,
            'nature' => $validated['nature'],
            'urgency' => $validated['urgency'],
            'due_date' => $validated['dueDate'] ?: null,
            'archive_location' => $validated['archiveLocation'] ?: null,
            'archive_box' => $validated['archiveBox'] ?: null,
            'retention_category' => $validated['retentionCategory'],
            'retention_until' => $validated['retentionUntil'] ?: null,
            'archive_notes' => $validated['archiveNotes'] ?: null,
            'file_path' => $filePath,
            'status' => $editingLetter?->status ?? ($validated['type'] === 'Masuk' ? 'Baru' : 'Selesai'),
        ];

        if ($editingLetter) {
            $oldNumber = $editingLetter->number;
            $editingLetter->update($data);
            $letter = $editingLetter->refresh();
        } else {
            $oldNumber = null;
            $letter = Letter::create($data);
        }

        if ($validated['type'] === 'Keluar' && (! $editingLetter || $oldNumber !== $number)) {
            $this->advanceNextLetterSequence($number);
        }

        $this->storeAttachmentFiles($letter, 'Lampiran', $this->attachmentFiles);
        $this->storeAttachmentFiles($letter, 'Nota Dinas', $this->memoFiles);
        $this->storeAttachmentFiles($letter, 'Dokumen Pendukung', $this->supportingFiles);

        $this->selectedLetterId = $letter->id;
        $this->showLetterForm = false;
        ActivityLog::record(
            $editingLetter ? 'letter.updated' : 'letter.created',
            'Surat '.$letter->type.($editingLetter ? ' diperbarui: ' : ' dicatat: ').$letter->number,
            $letter,
            ['agenda_number' => $letter->agenda_number, 'urgency' => $letter->urgency],
        );
        $this->editingLetterId = null;
        $this->dispatch('notify', message: $editingLetter ? 'Surat berhasil diperbarui.' : 'Surat baru berhasil dicatat.');
    }

    public function deleteLetter(int $letterId): void
    {
        abort_unless($this->canDeleteLetters(), 403);

        $letter = Letter::with('attachments')->findOrFail($letterId);
        $number = $letter->number;

        if ($letter->file_path && Storage::disk('public')->exists($letter->file_path)) {
            Storage::disk('public')->delete($letter->file_path);
        }

        foreach ($letter->attachments as $attachment) {
            if (Storage::disk('public')->exists($attachment->file_path)) {
                Storage::disk('public')->delete($attachment->file_path);
            }
        }

        $letter->delete();

        if ($this->selectedLetterId === $letterId) {
            $this->selectedLetterId = Letter::latest('letter_date')->value('id');
            $this->showDetailModal = false;
        }

        ActivityLog::record('letter.deleted', 'Surat dihapus: '.$number);
        $this->dispatch('notify', message: 'Surat berhasil dihapus.');
    }

    public function uploadOutgoingDocument(int $letterId): void
    {
        abort_unless($this->canManageLetters(), 403);

        $letter = Letter::findOrFail($letterId);
        abort_unless($letter->type === 'Keluar', 403);

        $this->validate([
            'outgoingDocument' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        if ($letter->file_path && Storage::disk('public')->exists($letter->file_path)) {
            Storage::disk('public')->delete($letter->file_path);
        }

        $letter->update([
            'file_path' => $this->outgoingDocument->store('dokumen-surat', 'public'),
        ]);

        $this->outgoingDocument = null;
        ActivityLog::record('letter.document_uploaded', 'Dokumen surat keluar diunggah: '.$letter->number, $letter);
        $this->dispatch('notify', message: 'Dokumen surat keluar berhasil diunggah.');
    }

    public function uploadAdditionalDocuments(int $letterId): void
    {
        abort_unless($this->canManageLetters(), 403);

        $letter = Letter::findOrFail($letterId);

        $this->validate([
            'attachmentFiles.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'memoFiles.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'supportingFiles.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $total = 0;
        $total += $this->storeAttachmentFiles($letter, 'Lampiran', $this->attachmentFiles);
        $total += $this->storeAttachmentFiles($letter, 'Nota Dinas', $this->memoFiles);
        $total += $this->storeAttachmentFiles($letter, 'Dokumen Pendukung', $this->supportingFiles);

        $this->attachmentFiles = [];
        $this->memoFiles = [];
        $this->supportingFiles = [];

        ActivityLog::record('letter.attachments_uploaded', $total.' dokumen tambahan diunggah untuk surat '.$letter->number, $letter);
        $this->dispatch('notify', message: 'Dokumen tambahan berhasil diunggah.');
    }

    public function startSignatureWorkflow(int $letterId): void
    {
        abort_unless($this->canManageLetters(), 403);

        $letter = Letter::with('approvals')->findOrFail($letterId);
        abort_unless($letter->type === 'Keluar', 403);

        if ($letter->approvals->isEmpty()) {
            foreach ($this->approvalSteps() as $step) {
                $letter->approvals()->create($step);
            }
        }

        $letter->update(['status' => 'Menunggu Paraf']);

        ActivityLog::record('letter.signature_workflow_started', 'Alur paraf dan tanda tangan dimulai untuk surat '.$letter->number, $letter);
        $this->dispatch('notify', message: 'Alur paraf dan tanda tangan dimulai.');
    }

    public function approveLetterStep(int $approvalId): void
    {
        $approval = LetterApproval::with('letter.approvals')->findOrFail($approvalId);

        abort_unless($this->canActOnApproval($approval), 403);
        abort_unless($approval->status === 'Menunggu', 422);

        $user = auth()->user();
        $approval->update([
            'status' => $approval->step === 'Tanda Tangan Elektronik' ? 'Ditandatangani' : 'Disetujui',
            'actor_name' => $user?->name,
            'actor_role' => $user?->role,
            'acted_at' => now(),
        ]);

        $approval->letter->update(['status' => $this->nextSignatureStatus($approval)]);

        ActivityLog::record(
            'letter.approval_completed',
            $approval->step.' selesai untuk surat '.$approval->letter->number,
            $approval,
            ['letter_id' => $approval->letter_id],
        );

        $this->dispatch('notify', message: $approval->step.' berhasil diproses.');
    }

    public function openApprovalRevisionModal(int $approvalId): void
    {
        $approval = LetterApproval::with('letter.approvals')->findOrFail($approvalId);

        abort_unless($this->canActOnApproval($approval), 403);

        $this->resetValidation();
        $this->revisionApprovalId = $approval->id;
        $this->revisionNote = '';
        $this->showRevisionModal = true;
    }

    public function rejectApprovalStep(): void
    {
        $approval = LetterApproval::with('letter.approvals')->findOrFail((int) $this->revisionApprovalId);

        abort_unless($this->canActOnApproval($approval), 403);

        $validated = $this->validate([
            'revisionNote' => ['required', 'string', 'max:1000'],
        ]);

        $user = auth()->user();
        $approval->update([
            'status' => 'Ditolak',
            'actor_name' => $user?->name,
            'actor_role' => $user?->role,
            'note' => $validated['revisionNote'],
            'acted_at' => now(),
        ]);

        $approval->letter->update(['status' => 'Revisi Konsep']);

        ActivityLog::record(
            'letter.approval_rejected',
            $approval->step.' meminta revisi untuk surat '.$approval->letter->number,
            $approval,
            ['letter_id' => $approval->letter_id],
        );

        $this->revisionApprovalId = null;
        $this->revisionNote = '';
        $this->showRevisionModal = false;
        $this->dispatch('notify', message: 'Konsep surat dikembalikan untuk revisi.');
    }

    public function resubmitApprovalWorkflow(int $letterId): void
    {
        abort_unless($this->canManageLetters(), 403);

        $letter = Letter::with('approvals')->findOrFail($letterId);
        abort_unless($letter->type === 'Keluar', 403);

        $rejectedApproval = $letter->approvals
            ->where('status', 'Ditolak')
            ->sortBy('sort_order')
            ->first();

        abort_unless($rejectedApproval !== null, 422);

        $letter->approvals()
            ->where('sort_order', '>=', $rejectedApproval->sort_order)
            ->update([
                'status' => 'Menunggu',
                'actor_name' => null,
                'actor_role' => null,
                'note' => null,
                'acted_at' => null,
            ]);

        $letter->update(['status' => $this->waitingStatusForApprovalStep($rejectedApproval->step)]);

        ActivityLog::record(
            'letter.approval_resubmitted',
            'Konsep surat diajukan ulang: '.$letter->number,
            $letter,
            ['from_step' => $rejectedApproval->step],
        );

        $this->dispatch('notify', message: 'Konsep surat diajukan ulang ke alur paraf.');
    }

    public function approvalSteps(): array
    {
        return [
            [
                'step' => 'Paraf Konsep',
                'target_role' => 'Kepala Bagian',
                'status' => 'Menunggu',
                'sort_order' => 1,
            ],
            [
                'step' => 'Persetujuan Pimpinan',
                'target_role' => 'Pimpinan MRP',
                'status' => 'Menunggu',
                'sort_order' => 2,
            ],
            [
                'step' => 'Tanda Tangan Elektronik',
                'target_role' => 'Pimpinan MRP',
                'status' => 'Menunggu',
                'sort_order' => 3,
            ],
        ];
    }

    public function canActOnApproval(LetterApproval $approval): bool
    {
        $user = auth()->user();

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

    public function nextSignatureStatus(LetterApproval $approval): string
    {
        return match ($approval->step) {
            'Paraf Konsep' => 'Menunggu Persetujuan',
            'Persetujuan Pimpinan' => 'Menunggu Tanda Tangan',
            'Tanda Tangan Elektronik' => 'Selesai',
            default => $approval->letter->status,
        };
    }

    public function waitingStatusForApprovalStep(string $step): string
    {
        return match ($step) {
            'Paraf Konsep' => 'Menunggu Paraf',
            'Persetujuan Pimpinan' => 'Menunggu Persetujuan',
            'Tanda Tangan Elektronik' => 'Menunggu Tanda Tangan',
            default => 'Menunggu Paraf',
        };
    }

    public function storeAttachmentFiles(Letter $letter, string $category, array $files): int
    {
        $stored = 0;

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

            $stored++;
        }

        return $stored;
    }

    public function openDispositionForm(int $letterId): void
    {
        abort_unless($this->canDispose(), 403);

        $letter = Letter::findOrFail($letterId);
        abort_unless($letter->canReceiveDisposition(), 422);

        $this->resetValidation();
        $this->selectedLetterId = $letterId;
        $firstRecipientId = $this->dispositionRecipients()->first()?->id;
        $this->recipientId = null;
        $this->recipientIds = $firstRecipientId ? [$firstRecipientId] : [];
        $this->instruction = '';
        $this->dispositionScan = null;
        $this->showDetailModal = false;
        $this->showDispositionForm = true;
    }

    public function saveDisposition(): void
    {
        abort_unless($this->canDispose(), 403);

        $validated = $this->validate([
            'recipientIds' => ['nullable', 'array'],
            'recipientIds.*' => [Rule::exists('disposition_recipients', 'id')->where('is_active', true)],
            'instruction' => ['required', 'string', 'max:1000'],
            'dispositionScan' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $letter = $this->selectedLetter();
        $recipientIds = $this->selectedRecipientIds();
        if (! $letter) {
            return;
        }

        if (! $letter->canReceiveDisposition()) {
            $this->addError('selectedLetterId', 'Disposisi hanya bisa dibuat untuk surat masuk.');

            return;
        }

        if ($recipientIds === []) {
            $this->addError('recipientIds', 'Pilih minimal satu penerima disposisi.');

            return;
        }

        $recipients = DispositionRecipient::query()
            ->where('is_active', true)
            ->whereIn('id', $recipientIds)
            ->get();

        $scanData = [];

        if ($this->dispositionScan) {
            $path = $this->dispositionScan->store('file-disposisi', 'public');
            $scanData = [
                'input_method' => 'Upload File Disposisi',
                'scan_path' => $path,
                'scan_original_name' => $this->dispositionScan->getClientOriginalName(),
                'scan_mime_type' => $this->dispositionScan->getMimeType(),
                'scan_size' => $this->dispositionScan->getSize(),
            ];
        }

        foreach ($recipients as $recipient) {
            $disposition = $letter->dispositions()->create([
                'sender_name' => 'Pimpinan',
                'recipient_name' => $recipient->name,
                'disposition_recipient_id' => $recipient->id,
                'instruction' => $validated['instruction'],
                'status' => 'Belum Dibaca',
            ] + $scanData);

            ActivityLog::record(
                'disposition.created',
                'Disposisi dikirim ke '.$recipient->name.' untuk surat '.$letter->number,
                $disposition,
                ['letter_id' => $letter->id],
            );
        }

        $letter->syncDispositionStatus();
        $this->showDispositionForm = false;
        $this->dispositionScan = null;
        $this->dispatch('notify', message: 'Disposisi terkirim ke staf terkait.');
    }

    public function selectedRecipientIds(): array
    {
        $ids = collect($this->recipientIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($this->recipientId) {
            return [(int) $this->recipientId];
        }

        return $ids;
    }

    public function updateStatus(int $letterId, string $status): void
    {
        abort_unless($this->canUpdateStatus(), 403);

        $letter = Letter::findOrFail($letterId);
        $letter->update(['status' => $status]);

        if ($letter->canReceiveDisposition() && $letter->dispositions()->exists()) {
            if ($status === 'Selesai') {
                $letter->dispositions()->where('status', '!=', 'Selesai')->update(['status' => 'Selesai']);
            }

            $letter->syncDispositionStatus();
        }

        ActivityLog::record('letter.status_updated', 'Status surat '.$letter->number.' diperbarui menjadi '.$status.'.', $letter);
        $this->dispatch('notify', message: 'Status surat diperbarui.');
    }

    public function markDone(int $letterId): void
    {
        $this->updateStatus($letterId, 'Selesai');
    }

    public function canManageLetters(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function canEditLetters(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function canDeleteLetters(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function canDispose(): bool
    {
        $user = auth()->user();

        return $user?->isAdmin() || $user?->isLeader();
    }

    public function canUpdateStatus(): bool
    {
        $user = auth()->user();

        return $user?->isAdmin() || $user?->isStaff();
    }

    public function canManageSettings(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function nextLetterNumber(?string $unitCode = null, ?string $classificationCode = null): string
    {
        $unitCode = $this->normalizeUnitCode($unitCode);
        $numbering = $this->numberingSettings();
        $separator = $numbering['separator'];
        $parts = [
            $classificationCode ?: $numbering['prefix'],
            str_pad((string) $numbering['next_sequence'], 3, '0', STR_PAD_LEFT),
            $unitCode,
        ];

        if ($numbering['include_month']) {
            $parts[] = now()->format('m');
        }

        if ($numbering['include_year']) {
            $parts[] = now()->format('Y');
        }

        return implode($separator, $parts);
    }

    public function defaultOutgoingBody(): string
    {
        return "Dengan hormat,\n\nSehubungan dengan perihal tersebut di atas, bersama ini kami sampaikan surat ini untuk menjadi perhatian dan tindak lanjut sebagaimana mestinya.\n\nDemikian disampaikan. Atas perhatian dan kerja samanya, kami ucapkan terima kasih.";
    }

    public function nextAgendaNumber(?string $unitCode = null): string
    {
        $unitCode = $this->normalizeUnitCode($unitCode);
        $year = now()->format('Y');
        $next = Letter::query()
            ->where('type', 'Masuk')
            ->where('unit_code', $unitCode)
            ->whereYear('received_date', $year)
            ->count() + 1;

        return 'AG/'.$unitCode.'/'.$year.'/'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function numberingSettings(): array
    {
        $numbering = AppSetting::getValue('letter_numbering', [
            'prefix' => '800',
            'separator' => '/',
            'include_month' => true,
            'include_year' => true,
            'next_sequence' => 1,
        ]);

        return [
            'prefix' => (string) ($numbering['prefix'] ?? '800'),
            'separator' => (string) ($numbering['separator'] ?? '/'),
            'include_month' => (bool) ($numbering['include_month'] ?? true),
            'include_year' => (bool) ($numbering['include_year'] ?? true),
            'next_sequence' => max(1, (int) ($numbering['next_sequence'] ?? 1)),
        ];
    }

    public function advanceNextLetterSequence(string $number): void
    {
        $numbering = AppSetting::getValue('letter_numbering', [
            'prefix' => '800',
            'unit_code' => $this->defaultUnitCode(),
            'separator' => '/',
            'include_month' => true,
            'include_year' => true,
            'next_sequence' => 1,
        ]);

        $separator = (string) ($numbering['separator'] ?? '/');
        $parts = $separator !== '' ? explode($separator, $number) : [];
        $sequence = isset($parts[1]) && ctype_digit($parts[1]) ? (int) $parts[1] : null;

        if ($sequence === null) {
            return;
        }

        AppSetting::putValue('letter_numbering', [
            ...$numbering,
            'next_sequence' => max((int) ($numbering['next_sequence'] ?? 1), $sequence + 1),
        ]);
    }

    public function statusClass(string $status): string
    {
        return match ($status) {
            'Baru' => 'bg-rose-100 text-rose-700 ring-rose-200',
            'Disposisi', 'Disposisi Pimpinan' => 'bg-amber-100 text-amber-700 ring-amber-200',
            'Diproses' => 'bg-indigo-100 text-indigo-700 ring-indigo-200',
            'Menunggu Paraf', 'Menunggu Persetujuan', 'Menunggu Tanda Tangan' => 'bg-sky-100 text-sky-700 ring-sky-200',
            'Disetujui', 'Ditandatangani' => 'bg-emerald-100 text-emerald-700 ring-emerald-200',
            'Revisi Konsep', 'Ditolak' => 'bg-rose-100 text-rose-700 ring-rose-200',
            'Menunggu' => 'bg-amber-100 text-amber-700 ring-amber-200',
            'Selesai' => 'bg-emerald-100 text-emerald-700 ring-emerald-200',
            default => 'bg-slate-100 text-slate-700 ring-slate-200',
        };
    }

    public function typeClass(string $type): string
    {
        return $type === 'Masuk'
            ? 'bg-sky-100 text-sky-700 ring-sky-200'
            : 'bg-violet-100 text-violet-700 ring-violet-200';
    }

    public function unitClass(string $unitCode): string
    {
        return collect($this->unitCodes())->search($unitCode) % 2 === 0
            ? 'bg-teal-100 text-teal-700 ring-teal-200'
            : 'bg-orange-100 text-orange-700 ring-orange-200';
    }

    public function urgencyClass(string $urgency): string
    {
        return match ($urgency) {
            'Sangat Segera' => 'bg-rose-100 text-rose-700 ring-rose-200',
            'Segera' => 'bg-amber-100 text-amber-700 ring-amber-200',
            default => 'bg-slate-100 text-slate-700 ring-slate-200',
        };
    }

    public function dueClass(?Letter $letter): string
    {
        if (! $letter?->due_date || $letter->status === 'Selesai') {
            return 'bg-slate-100 text-slate-600 ring-slate-200';
        }

        if ($letter->due_date->isPast() && ! $letter->due_date->isToday()) {
            return 'bg-rose-100 text-rose-700 ring-rose-200';
        }

        if ($letter->due_date->betweenIncluded(today(), today()->addDays(3))) {
            return 'bg-amber-100 text-amber-700 ring-amber-200';
        }

        return 'bg-emerald-100 text-emerald-700 ring-emerald-200';
    }
};
?>

<div x-data="{ toast: '', showToast: false }"
     x-on:notify.window="toast = $event.detail.message; showToast = true; setTimeout(() => showToast = false, 2600)"
     class="min-h-screen lg:grid lg:grid-cols-[280px_minmax(0,1fr)]">
    @php
        $letters = $this->letters();
        $selectedLetter = $this->selectedLetter();
        $editingLetter = $this->editingLetter();
        $classifications = $this->archiveClassifications();
        $dispositionRecipients = $this->dispositionRecipients();
        $currentUser = auth()->user();
        $agencyProfile = AppSetting::agency();
        $taskCount = TaskInbox::countFor($currentUser);
        $units = $this->units();
        $unitSummaries = $this->unitSummaries();
        $overdueCount = App\Models\Letter::whereNotNull('due_date')->whereDate('due_date', '<', today())->where('status', '!=', 'Selesai')->count();
        $dueSoonCount = App\Models\Letter::whereNotNull('due_date')->whereBetween('due_date', [today(), today()->addDays(3)])->where('status', '!=', 'Selesai')->count();
    @endphp

    <aside class="bg-slate-900 px-5 py-5 text-slate-100 lg:sticky lg:top-0 lg:z-10 lg:h-screen lg:overflow-y-auto">
        <div class="flex items-center gap-3">
            <x-app-logo class="h-11 w-11" />
            <div>
                <div class="font-semibold">{{ $agencyProfile['app_name'] }}</div>
                <div class="text-sm text-slate-400">{{ $agencyProfile['name'] }}</div>
            </div>
        </div>

        <nav class="mt-8 flex gap-2 overflow-x-auto lg:grid lg:overflow-visible">
            <button type="button"
                    wire:click="setCombinedFilter('Semua', 'Semua')"
                    class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-semibold transition {{ $typeFilter === 'Semua' && $unitFilter === 'Semua' ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5' }}">
                <x-icon name="dashboard" class="h-4 w-4" />
                Dasbor
            </button>
            <a href="{{ route('my-tasks') }}" class="flex items-center justify-between rounded-lg px-3 py-2 text-left text-sm font-semibold text-slate-300 transition hover:bg-white/5">
                <span class="inline-flex items-center gap-2"><x-icon name="task" class="h-4 w-4" />Tugas Saya</span>
                @if ($taskCount > 0)
                    <span class="rounded-full bg-rose-500 px-2 py-0.5 text-xs font-bold text-white">{{ $taskCount }}</span>
                @endif
            </a>
            <a href="{{ route('tracking') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-semibold text-slate-300 transition hover:bg-white/5">
                <x-icon name="route" class="h-4 w-4" />
                Pelacakan Surat
            </a>

            @if ($this->canDispose())
                <button type="button"
                        wire:click="openDispositionForm({{ $selectedLetterId ?? 0 }})"
                        @disabled(! $selectedLetterId)
                        class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-semibold text-slate-300 transition hover:bg-white/5 disabled:cursor-not-allowed disabled:opacity-50">
                    <x-icon name="send" class="h-4 w-4" />
                    Disposisi
                </button>
            @endif
            @if ($currentUser?->isAdmin() || $currentUser?->isLeader() || $currentUser?->isPersonalSecretary())
                <a href="{{ route('leadership') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-semibold text-slate-300 transition hover:bg-white/5">
                    <x-icon name="send" class="h-4 w-4" />
                    Halaman Pimpinan
                </a>
            @endif
            @if ($currentUser?->isAdmin() || $currentUser?->isDepartmentHead())
                <a href="{{ route('department-head') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-semibold text-slate-300 transition hover:bg-white/5">
                    <x-icon name="users" class="h-4 w-4" />
                    Halaman Kepala Bagian
                </a>
            @endif
            @if ($this->canManageSettings())
                <a href="{{ route('number-monitor') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-semibold text-slate-300 transition hover:bg-white/5">
                    <x-icon name="list-numbered" class="h-4 w-4" />
                    Monitoring Nomor
                </a>
                <a href="{{ route('sk-numbering') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-semibold text-slate-300 transition hover:bg-white/5">
                    <x-icon name="gavel" class="h-4 w-4" />
                    Penomoran SK
                </a>
                <a href="{{ route('settings') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-semibold text-slate-300 transition hover:bg-white/5">
                    <x-icon name="settings" class="h-4 w-4" />
                    Setting
                </a>
            @endif
        </nav>

        <div class="mt-10 hidden border-t border-white/10 pt-5 lg:flex lg:items-center lg:gap-3">
            <div class="grid h-10 w-10 place-items-center rounded-lg bg-slate-700 font-semibold">AN</div>
            <div>
                <div class="text-sm font-semibold">{{ $currentUser->name }}</div>
                <div class="text-xs text-slate-400">{{ $currentUser->role }}</div>
            </div>
        </div>
    </aside>

    <main class="min-w-0 px-4 py-6 sm:px-6 lg:px-8">
        <header class="sticky top-0 z-10 -mx-4 border-b border-slate-200 bg-slate-100/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
            <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase text-teal-700">{{ \Carbon\Carbon::now()->translatedFormat('l, j F Y') }}</p>
                    <h1 class="mt-0.5 text-xl font-bold leading-tight tracking-normal text-slate-950 sm:text-2xl">Dasbor Persuratan {{ $agencyProfile['short_name'] }}</h1>
                    <p class="mt-1 max-w-xl text-xs text-slate-600">Pengelolaan surat {{ $agencyProfile['name'] }}.</p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center xl:justify-end">
                    <label class="flex h-10 w-full items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 shadow-sm sm:w-72 lg:w-80">
                        <x-icon name="search" class="h-4 w-4 text-slate-400" />
                        <input wire:model.live.debounce.250ms="search"
                               type="search"
                               class="min-w-0 flex-1 border-0 bg-transparent text-sm outline-none"
                               placeholder="Cari nomor, agenda, perihal, pihak luar...">
                    </label>
                    <div class="flex flex-wrap gap-2">
                        @if ($this->canManageLetters())
                            @php($actionUnit = $unitFilter === 'Semua' ? $this->defaultUnitCode() : $unitFilter)
                            <button type="button"
                                    wire:click="openLetterForm('Masuk', '{{ $actionUnit }}')"
                                    class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-sm font-bold text-slate-800 shadow-sm hover:border-teal-600">
                                <x-icon name="inbox" class="mr-2 h-4 w-4" />
                                Input Surat Masuk
                            </button>
                            <button type="button"
                                    wire:click="openLetterForm('Keluar', '{{ $actionUnit }}')"
                                    class="inline-flex h-10 items-center justify-center rounded-lg bg-teal-700 px-3 text-sm font-bold text-white shadow-sm hover:bg-teal-800">
                                <x-icon name="outbox" class="mr-2 h-4 w-4" />
                                Input Surat Keluar
                            </button>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-3 text-sm font-bold text-slate-800 shadow-sm hover:border-rose-500">
                                <x-icon name="logout" class="mr-2 h-4 w-4" />
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <section class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
            @foreach ($unitSummaries as $unit)
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="text-sm text-slate-500">{{ $unit['code'] }} Masuk</div>
                    <div class="mt-2 text-3xl font-bold">{{ $unit['incoming_count'] }}</div>
                    <div class="text-sm text-slate-500">{{ $unit['name'] }}</div>
                </div>
                <div class="rounded-lg border border-orange-200 bg-orange-50 p-5 shadow-sm">
                    <div class="text-sm text-orange-700">{{ $unit['code'] }} Keluar</div>
                    <div class="mt-2 text-3xl font-bold">{{ $unit['outgoing_count'] }}</div>
                    <div class="text-sm text-orange-700">{{ $unit['name'] }}</div>
                </div>
            @endforeach
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-5 shadow-sm">
                <div class="text-sm text-rose-700">Lewat Batas</div>
                <div class="mt-2 text-3xl font-bold">{{ $overdueCount }}</div>
                <div class="text-sm text-rose-700">butuh tindak lanjut</div>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <div class="text-sm text-amber-700">Dekat Tenggat</div>
                <div class="mt-2 text-3xl font-bold">{{ $dueSoonCount }}</div>
                <div class="text-sm text-amber-700">dalam 3 hari</div>
            </div>
        </section>

        <section class="mt-5">
            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-200 p-5 xl:flex-row xl:items-center xl:justify-between">
                    <div>
                        <h2 class="text-lg font-bold">Daftar Surat</h2>
                        <p class="text-sm text-slate-500">
                            @if ($letters->total() > 0)
                                Menampilkan {{ $letters->firstItem() }}-{{ $letters->lastItem() }} dari {{ $letters->total() }} surat{{ $search ? ' dari pencarian langsung' : '' }}
                            @else
                                Tidak ada surat ditampilkan
                            @endif
                        </p>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:justify-end">
                        <a href="{{ $this->exportUrl() }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-800 hover:border-teal-600">
                            Export CSV
                        </a>
                        <div class="inline-flex overflow-x-auto rounded-lg bg-slate-100 p-1">
                            @foreach (['Semua', 'Masuk', 'Keluar'] as $filter)
                                <button type="button"
                                        wire:click="setFilter('{{ $filter }}')"
                                        class="whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-bold {{ $typeFilter === $filter ? 'bg-white text-teal-700 shadow-sm' : 'text-slate-600' }}">
                                    {{ $filter }}
                                </button>
                            @endforeach
                        </div>
                        <div class="inline-flex overflow-x-auto rounded-lg bg-slate-100 p-1">
                            @foreach (['Semua', ...collect($units)->pluck('code')->all()] as $filter)
                                <button type="button"
                                        wire:click="setUnitFilter('{{ $filter }}')"
                                        class="whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-bold {{ $unitFilter === $filter ? 'bg-white text-teal-700 shadow-sm' : 'text-slate-600' }}">
                                    {{ $filter }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 border-b border-slate-200 bg-slate-50 p-5 md:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_1fr_1fr_auto]">
                    <label class="grid gap-1 text-xs font-bold uppercase text-slate-500">
                        Status
                        <select wire:model.live="statusFilter" class="min-h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold normal-case text-slate-900">
                            @foreach (['Semua', 'Baru', 'Disposisi Pimpinan', 'Diproses', 'Menunggu Paraf', 'Menunggu Persetujuan', 'Menunggu Tanda Tangan', 'Revisi Konsep', 'Selesai'] as $status)
                                <option value="{{ $status }}">{{ $status }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="grid gap-1 text-xs font-bold uppercase text-slate-500">
                        Prioritas
                        <select wire:model.live="urgencyFilter" class="min-h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold normal-case text-slate-900">
                            @foreach (['Semua', 'Normal', 'Segera', 'Sangat Segera'] as $urgencyOption)
                                <option value="{{ $urgencyOption }}">{{ $urgencyOption }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="grid gap-1 text-xs font-bold uppercase text-slate-500">
                        Tenggat
                        <select wire:model.live="dueFilter" class="min-h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold normal-case text-slate-900">
                            @foreach (['Semua', 'Lewat Batas', 'Dekat Tenggat', 'Tanpa Batas'] as $dueOption)
                                <option value="{{ $dueOption }}">{{ $dueOption }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="grid gap-1 text-xs font-bold uppercase text-slate-500">
                        Dari Tanggal
                        <input wire:model.live="dateFrom" type="date" class="min-h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold normal-case text-slate-900">
                    </label>
                    <label class="grid gap-1 text-xs font-bold uppercase text-slate-500">
                        Sampai Tanggal
                        <input wire:model.live="dateTo" type="date" class="min-h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold normal-case text-slate-900">
                    </label>
                    <div class="flex items-end">
                        <button type="button" wire:click="resetAdvancedFilters" class="min-h-10 w-full rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-700 hover:border-teal-600">
                            Reset
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-[1180px] w-full border-collapse text-left text-sm">
                        <thead class="text-xs uppercase text-slate-500">
                            <tr>
                                <th class="border-b border-slate-200 px-5 py-3">Nomor</th>
                                <th class="border-b border-slate-200 px-5 py-3">Jenis</th>
                                <th class="border-b border-slate-200 px-5 py-3">Unit</th>
                                <th class="border-b border-slate-200 px-5 py-3">Kode Arsip</th>
                                <th class="border-b border-slate-200 px-5 py-3">Perihal</th>
                                <th class="border-b border-slate-200 px-5 py-3">Pihak Luar</th>
                                <th class="border-b border-slate-200 px-5 py-3">Prioritas</th>
                                <th class="border-b border-slate-200 px-5 py-3">Tenggat</th>
                                <th class="border-b border-slate-200 px-5 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($letters as $letter)
                                <tr wire:key="letter-{{ $letter->id }}"
                                    wire:click="openDetailModal({{ $letter->id }})"
                                    wire:keydown.enter="openDetailModal({{ $letter->id }})"
                                    role="button"
                                    tabindex="0"
                                    class="cursor-pointer transition {{ $selectedLetterId === $letter->id ? 'bg-teal-50' : 'hover:bg-slate-50' }}">
                                    <td class="border-b border-slate-100 px-5 py-4">
                                        <div class="font-bold text-slate-950">{{ $letter->number }}</div>
                                        <div class="text-xs text-slate-500">{{ $letter->letter_date->translatedFormat('d F Y') }}</div>
                                        @if ($letter->agenda_number)
                                            <div class="mt-1 text-xs font-semibold text-teal-700">{{ $letter->agenda_number }}</div>
                                        @endif
                                    </td>
                                    <td class="border-b border-slate-100 px-5 py-4">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $this->typeClass($letter->type) }}">{{ $letter->type }}</span>
                                    </td>
                                    <td class="border-b border-slate-100 px-5 py-4">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $this->unitClass($letter->unit_code) }}">{{ $letter->unit_code }}</span>
                                    </td>
                                    <td class="border-b border-slate-100 px-5 py-4">
                                        @if ($letter->classification_code)
                                            <div class="font-bold text-slate-950">{{ $letter->classification_code }}</div>
                                            <div class="max-w-44 truncate text-xs text-slate-500">{{ $letter->classification?->name }}</div>
                                        @else
                                            <span class="text-xs text-slate-400">Belum dipilih</span>
                                        @endif
                                    </td>
                                    <td class="border-b border-slate-100 px-5 py-4">{{ $letter->subject }}</td>
                                    <td class="border-b border-slate-100 px-5 py-4">{{ $letter->external_party }}</td>
                                    <td class="border-b border-slate-100 px-5 py-4">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $this->urgencyClass($letter->urgency) }}">{{ $letter->urgency }}</span>
                                    </td>
                                    <td class="border-b border-slate-100 px-5 py-4">
                                        @if ($letter->due_date)
                                            <span class="rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $this->dueClass($letter) }}">{{ $letter->due_date->translatedFormat('d M Y') }}</span>
                                        @else
                                            <span class="text-xs text-slate-400">Tanpa batas</span>
                                        @endif
                                    </td>
                                    <td class="border-b border-slate-100 px-5 py-4">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $this->statusClass($letter->status) }}">{{ $letter->status }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-5 py-16 text-center text-slate-500">
                                        Tidak ada surat yang cocok dengan filter saat ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($letters->hasPages())
                    <div class="border-t border-slate-200 p-5">
                        {{ $letters->links() }}
                    </div>
                @endif
            </div>

        </section>

        @if ($showDetailModal && $selectedLetter)
            <div wire:click.self="$set('showDetailModal', false)" class="fixed inset-0 z-20 grid place-items-center bg-slate-950/50 p-4">
                <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-lg bg-white shadow-xl">
                    <div class="flex items-start justify-between gap-4 border-b border-slate-200 p-6">
                        <div>
                            <p class="text-xs font-bold uppercase text-teal-700">{{ $selectedLetter->unit_code }} | {{ $selectedLetter->type }}</p>
                            <h2 class="mt-1 text-xl font-bold">Detail Surat</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $selectedLetter->number }}</p>
                        </div>
                        <button type="button" wire:click="$set('showDetailModal', false)" class="grid h-10 w-10 place-items-center rounded-lg bg-slate-100 text-xl font-bold">×</button>
                    </div>

                    <div class="space-y-5 p-6">
                        <div class="flex flex-wrap gap-2">
                            <span class="rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $this->statusClass($selectedLetter->status) }}">{{ $selectedLetter->status }}</span>
                            <span class="rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $this->unitClass($selectedLetter->unit_code) }}">{{ $selectedLetter->unit_code }}</span>
                            <span class="rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $this->typeClass($selectedLetter->type) }}">{{ $selectedLetter->type }}</span>
                            @if ($selectedLetter->type === 'Keluar' && $selectedLetter->outgoing_input_mode)
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700 ring-1 ring-slate-200">
                                    {{ $selectedLetter->outgoing_input_mode === 'template' ? 'Dibuat dari Template' : 'Upload Surat Jadi' }}
                                </span>
                            @endif
                        </div>
                        @if (($this->canDispose() && $selectedLetter->canReceiveDisposition()) || $this->canEditLetters() || $this->canDeleteLetters() || $selectedLetter->file_path || ($selectedLetter->type === 'Keluar' && $selectedLetter->outgoing_body))
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <div class="text-sm font-bold text-slate-700">Aksi Surat</div>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @if ($this->canDispose() && $selectedLetter->canReceiveDisposition())
                                        <button type="button" wire:click="openDispositionForm({{ $selectedLetter->id }})" class="min-h-10 rounded-lg bg-teal-700 px-4 text-sm font-bold text-white hover:bg-teal-800">Disposisi</button>
                                    @endif
                                    @if ($this->canEditLetters())
                                        <button type="button" wire:click="openEditLetterForm({{ $selectedLetter->id }})" class="min-h-10 rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold hover:border-teal-600">Edit Surat</button>
                                    @endif
                                    @if ($selectedLetter->file_path)
                                        <a href="{{ route('letters.document.review', $selectedLetter) }}" target="_blank" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold hover:border-teal-600">Review Dokumen</a>
                                        <a href="{{ route('letters.document.download', $selectedLetter) }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold hover:border-teal-600">Download</a>
                                    @endif
                                    @if ($selectedLetter->type === 'Keluar' && $selectedLetter->outgoing_body)
                                        <a href="{{ route('letters.template.pdf', $selectedLetter) }}" target="_blank" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold hover:border-teal-600">Preview / PDF</a>
                                        <a href="{{ route('letters.template.docx', $selectedLetter) }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold hover:border-teal-600">Export DOCX</a>
                                    @endif
                                    @if ($this->canDeleteLetters())
                                        <button type="button" wire:click="deleteLetter({{ $selectedLetter->id }})" wire:confirm="Hapus surat ini? Data dan dokumen terkait akan ikut dihapus." class="min-h-10 rounded-lg border border-rose-200 bg-white px-4 text-sm font-bold text-rose-700 hover:border-rose-500">Hapus Surat</button>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <div class="text-xs font-bold uppercase text-slate-500">Kode Klasifikasi Arsip</div>
                                @if ($selectedLetter->classification_code)
                                    <div class="font-semibold">{{ $selectedLetter->classification_code }} - {{ $selectedLetter->classification?->name }}</div>
                                @else
                                    <div class="font-semibold text-slate-400">Belum dipilih</div>
                                @endif
                            </div>
                            <div>
                                <div class="text-xs font-bold uppercase text-slate-500">Tanggal Surat</div>
                                <div class="font-semibold">{{ $selectedLetter->letter_date->translatedFormat('d F Y') }}</div>
                            </div>
                            @if ($selectedLetter->type === 'Masuk')
                                <div>
                                    <div class="text-xs font-bold uppercase text-slate-500">Nomor Agenda</div>
                                    <div class="font-semibold">{{ $selectedLetter->agenda_number ?: '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-bold uppercase text-slate-500">Tanggal Diterima</div>
                                    <div class="font-semibold">{{ $selectedLetter->received_date?->translatedFormat('d F Y') ?: '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-bold uppercase text-slate-500">Sifat dan Prioritas</div>
                                    <div class="font-semibold">{{ $selectedLetter->nature }} | {{ $selectedLetter->urgency }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-bold uppercase text-slate-500">Batas Waktu</div>
                                    <div class="font-semibold">{{ $selectedLetter->due_date?->translatedFormat('d F Y') ?: '-' }}</div>
                                </div>
                            @endif
                            <div>
                                <div class="text-xs font-bold uppercase text-slate-500">Pihak Luar</div>
                                <div class="font-semibold">{{ $selectedLetter->external_party }}</div>
                            </div>
                            <div>
                                <div class="text-xs font-bold uppercase text-slate-500">Dokumen Scan</div>
                                @if ($selectedLetter->file_path)
                                    <div class="font-semibold">{{ basename($selectedLetter->file_path) }}</div>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <a href="{{ route('letters.document.review', $selectedLetter) }}" target="_blank" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-bold hover:border-teal-600">Buka Review</a>
                                        <a href="{{ route('letters.document.download', $selectedLetter) }}" class="rounded-lg bg-teal-700 px-3 py-1.5 text-sm font-bold text-white hover:bg-teal-800">Download</a>
                                    </div>
                                @else
                                    <div class="font-semibold text-slate-400">Belum ada dokumen terunggah</div>
                                @endif
                            </div>
                            <div class="sm:col-span-2">
                                <div class="text-xs font-bold uppercase text-slate-500">Perihal</div>
                                <div class="font-semibold">{{ $selectedLetter->subject }}</div>
                            </div>
                            <div class="sm:col-span-2">
                                <div class="text-xs font-bold uppercase text-slate-500">Lokasi Arsip Fisik</div>
                                <div class="font-semibold">{{ $selectedLetter->archive_location ?: 'Belum dicatat' }}</div>
                                @if ($selectedLetter->archive_box)
                                    <div class="mt-1 text-sm text-slate-500">Boks/Map/Rak: {{ $selectedLetter->archive_box }}</div>
                                @endif
                            </div>
                            <div>
                                <div class="text-xs font-bold uppercase text-slate-500">Retensi Arsip</div>
                                <div class="font-semibold">{{ $selectedLetter->retention_category ?: 'Aktif' }}</div>
                            </div>
                            <div>
                                <div class="text-xs font-bold uppercase text-slate-500">Batas Retensi</div>
                                <div class="font-semibold">{{ $selectedLetter->retention_until?->translatedFormat('d M Y') ?: 'Belum ditentukan' }}</div>
                            </div>
                            @if ($selectedLetter->archive_notes)
                                <div class="sm:col-span-2">
                                    <div class="text-xs font-bold uppercase text-slate-500">Catatan Arsip</div>
                                    <div class="font-semibold">{{ $selectedLetter->archive_notes }}</div>
                                </div>
                            @endif
                            @if ($selectedLetter->type === 'Keluar' && $selectedLetter->outgoing_body)
                                <div class="sm:col-span-2">
                                    <div class="text-xs font-bold uppercase text-slate-500">Naskah Surat Keluar</div>
                                    <div class="mt-1 whitespace-pre-line rounded-lg bg-slate-50 p-3 text-sm text-slate-700">{{ $selectedLetter->outgoing_body }}</div>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <a href="{{ route('letters.template.pdf', $selectedLetter) }}" target="_blank" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-bold hover:border-teal-600">Preview / PDF</a>
                                        <a href="{{ route('letters.template.docx', $selectedLetter) }}" class="rounded-lg bg-teal-700 px-3 py-1.5 text-sm font-bold text-white hover:bg-teal-800">Export DOCX</a>
                                    </div>
                                </div>
                            @endif
                        </div>

                        @if ($selectedLetter->file_path)
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h3 class="font-bold">Review Dokumen</h3>
                                        <p class="text-sm text-slate-500">Preview PDF atau gambar dokumen surat.</p>
                                    </div>
                                    <a href="{{ route('letters.document.download', $selectedLetter) }}" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-teal-700 px-4 text-sm font-bold text-white hover:bg-teal-800">Download</a>
                                </div>
                                <iframe src="{{ route('letters.document.review', $selectedLetter) }}"
                                        class="h-[520px] w-full rounded-lg border border-slate-200 bg-white"
                                        title="Review dokumen {{ $selectedLetter->number }}"></iframe>
                            </div>
                        @endif

                        @if ($selectedLetter->type === 'Keluar')
                            <div class="rounded-lg border border-slate-200 bg-white p-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <h3 class="font-bold">Paraf dan Tanda Tangan Elektronik</h3>
                                        <p class="text-sm text-slate-500">Alur formal konsep surat keluar sebelum dokumen dinyatakan selesai.</p>
                                    </div>
                                    @if ($this->canManageLetters())
                                        @if ($selectedLetter->approvals->isEmpty())
                                            <button type="button" wire:click="startSignatureWorkflow({{ $selectedLetter->id }})" class="min-h-10 rounded-lg bg-teal-700 px-4 text-sm font-bold text-white hover:bg-teal-800">
                                                Mulai Alur
                                            </button>
                                        @elseif ($selectedLetter->approvals->contains('status', 'Ditolak'))
                                            <button type="button" wire:click="resubmitApprovalWorkflow({{ $selectedLetter->id }})" class="min-h-10 rounded-lg bg-teal-700 px-4 text-sm font-bold text-white hover:bg-teal-800">
                                                Ajukan Ulang
                                            </button>
                                        @endif
                                    @endif
                                </div>

                                <div class="mt-4 space-y-3">
                                    @forelse ($selectedLetter->approvals as $approval)
                                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3" wire:key="approval-step-{{ $approval->id }}">
                                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                                <div>
                                                    <div class="font-semibold">{{ $approval->step }}</div>
                                                    <div class="text-xs text-slate-500">Target: {{ $approval->target_role }}</div>
                                                    @if ($approval->actor_name)
                                                        <div class="mt-1 text-xs text-slate-500">
                                                            Oleh {{ $approval->actor_name }} | {{ $approval->acted_at?->translatedFormat('d M Y H:i') }}
                                                        </div>
                                                    @endif
                                                    @if ($approval->note)
                                                        <div class="mt-2 rounded-lg bg-white px-3 py-2 text-sm text-rose-700 ring-1 ring-rose-100">
                                                            Catatan: {{ $approval->note }}
                                                        </div>
                                                    @endif
                                                </div>
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $this->statusClass($approval->status) }}">{{ $approval->status }}</span>
                                                    @if ($this->canActOnApproval($approval))
                                                        @php($actionLabel = match ($approval->step) {
                                                            'Paraf Konsep' => 'Paraf',
                                                            'Persetujuan Pimpinan' => 'Setujui',
                                                            'Tanda Tangan Elektronik' => 'Tanda Tangani',
                                                            default => 'Proses',
                                                        })
                                                        <button type="button" wire:click="approveLetterStep({{ $approval->id }})" class="rounded-lg bg-teal-700 px-3 py-1.5 text-sm font-bold text-white hover:bg-teal-800">
                                                            {{ $actionLabel }}
                                                        </button>
                                                        <button type="button" wire:click="openApprovalRevisionModal({{ $approval->id }})" class="rounded-lg border border-rose-200 bg-white px-3 py-1.5 text-sm font-bold text-rose-700 hover:bg-rose-50">
                                                            Revisi / Tolak
                                                        </button>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-sm text-slate-500">Alur paraf dan tanda tangan belum dimulai.</p>
                                    @endforelse
                                </div>
                            </div>
                        @endif

                        <div class="rounded-lg border border-slate-200 bg-white p-4">
                            <h3 class="font-bold">Lampiran Multi-file</h3>
                            <div class="mt-3 space-y-3">
                                @forelse ($selectedLetter->attachments->groupBy('category') as $category => $attachments)
                                    <div class="rounded-lg border border-slate-200 p-3">
                                        <div class="text-sm font-bold text-slate-700">{{ $category }}</div>
                                        <div class="mt-2 grid gap-2">
                                            @foreach ($attachments as $attachment)
                                                <div class="flex flex-col gap-2 rounded-lg bg-slate-50 p-3 sm:flex-row sm:items-center sm:justify-between" wire:key="letter-attachment-{{ $attachment->id }}">
                                                    <div class="min-w-0">
                                                        <div class="truncate text-sm font-semibold">{{ $attachment->original_name }}</div>
                                                        <div class="text-xs text-slate-500">{{ number_format((int) $attachment->size / 1024, 1) }} KB</div>
                                                    </div>
                                                    <div class="flex flex-wrap gap-2">
                                                        <a href="{{ route('letter-attachments.review', $attachment) }}" target="_blank" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-bold hover:border-teal-600">Review</a>
                                                        <a href="{{ route('letter-attachments.download', $attachment) }}" class="rounded-lg bg-teal-700 px-3 py-1.5 text-sm font-bold text-white hover:bg-teal-800">Download</a>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-sm text-slate-500">Belum ada lampiran, nota dinas, atau dokumen pendukung.</p>
                                @endforelse
                            </div>
                        </div>

                        @if ($selectedLetter->type === 'Keluar' && $this->canManageLetters())
                            <form wire:submit="uploadOutgoingDocument({{ $selectedLetter->id }})" class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4">
                                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                                    <label class="grid gap-1 text-sm font-bold text-slate-600">
                                        Upload Dokumen Surat Keluar
                                        <input wire:model="outgoingDocument" type="file" accept=".pdf,.jpg,.jpeg,.png" class="rounded-lg border border-slate-200 bg-white p-2 text-slate-950">
                                        <span class="text-xs font-normal text-slate-500">Bisa diunggah setelah surat disimpan. File lama akan diganti jika sudah ada.</span>
                                        @error('outgoingDocument') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                                    </label>
                                    <button type="submit" class="min-h-11 rounded-lg bg-teal-700 px-4 text-sm font-bold text-white hover:bg-teal-800">Upload File</button>
                                </div>
                            </form>
                        @endif

                        @if ($this->canManageLetters())
                            <form wire:submit="uploadAdditionalDocuments({{ $selectedLetter->id }})" class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4">
                                <div class="grid gap-4">
                                    <div>
                                        <h3 class="font-bold">Upload Dokumen Tambahan</h3>
                                        <p class="text-sm text-slate-500">Tambahkan lampiran, nota dinas, atau dokumen pendukung setelah surat tersimpan.</p>
                                    </div>
                                    <label class="grid gap-1 text-sm font-bold text-slate-600">
                                        Lampiran
                                        <input wire:model="attachmentFiles" type="file" multiple accept=".pdf,.jpg,.jpeg,.png" class="rounded-lg border border-slate-200 bg-white p-2 text-slate-950">
                                        @error('attachmentFiles.*') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                                    </label>
                                    <label class="grid gap-1 text-sm font-bold text-slate-600">
                                        Nota Dinas
                                        <input wire:model="memoFiles" type="file" multiple accept=".pdf,.jpg,.jpeg,.png" class="rounded-lg border border-slate-200 bg-white p-2 text-slate-950">
                                        @error('memoFiles.*') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                                    </label>
                                    <label class="grid gap-1 text-sm font-bold text-slate-600">
                                        Dokumen Pendukung
                                        <input wire:model="supportingFiles" type="file" multiple accept=".pdf,.jpg,.jpeg,.png" class="rounded-lg border border-slate-200 bg-white p-2 text-slate-950">
                                        @error('supportingFiles.*') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                                    </label>
                                    <div class="flex justify-end">
                                        <button type="submit" class="min-h-11 rounded-lg bg-teal-700 px-4 text-sm font-bold text-white hover:bg-teal-800">Upload Dokumen Tambahan</button>
                                    </div>
                                </div>
                            </form>
                        @endif

                        <div class="border-t border-slate-200 pt-5">
                            <h3 class="font-bold">Timeline Disposisi</h3>
                            <div class="mt-3 space-y-4">
                                @forelse ($selectedLetter->dispositions->filter(fn ($item) => $item->parent_id === null) as $disposition)
                                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <div class="font-semibold">{{ $disposition->sender_name }} ke {{ $disposition->recipient_name }}</div>
                                                <div class="text-xs text-slate-500">{{ $disposition->created_at->translatedFormat('d M Y H:i') }}</div>
                                            </div>
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $this->statusClass($disposition->status) }}">{{ $disposition->status }}</span>
                                        </div>
                                        <p class="mt-2 text-sm text-slate-600">{{ $disposition->instruction }}</p>
                                        @if ($disposition->scan_path)
                                            <div class="mt-3 flex flex-wrap gap-2">
                                                <a href="{{ route('dispositions.scan.review', $disposition) }}" target="_blank" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-bold hover:border-teal-600">Buka Scan Disposisi</a>
                                                <a href="{{ route('dispositions.scan.download', $disposition) }}" class="rounded-lg bg-teal-700 px-3 py-1.5 text-sm font-bold text-white hover:bg-teal-800">Download Scan</a>
                                            </div>
                                        @endif

                                        @if ($disposition->children->isNotEmpty())
                                            <div class="mt-4 space-y-3 border-l-4 border-teal-700 pl-4">
                                                @foreach ($disposition->children as $child)
                                                    <div class="rounded-lg border border-slate-200 bg-white p-3" wire:key="child-disposition-{{ $child->id }}">
                                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                                            <div>
                                                                <div class="font-semibold">{{ $child->sender_name }} ke {{ $child->recipient_name }}</div>
                                                                <div class="text-xs text-slate-500">{{ $child->created_at->translatedFormat('d M Y H:i') }}</div>
                                                            </div>
                                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $this->statusClass($child->status) }}">{{ $child->status }}</span>
                                                        </div>
                                                        <p class="mt-2 text-sm text-slate-600">{{ $child->instruction }}</p>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-sm text-slate-500">Belum ada riwayat disposisi.</p>
                                @endforelse
                            </div>
                        </div>

                        @if ($this->canUpdateStatus())
                            <div class="flex flex-col gap-2 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
                                <select wire:change="updateStatus({{ $selectedLetter->id }}, $event.target.value)" class="min-h-11 rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold">
                                    @foreach (['Baru', 'Disposisi Pimpinan', 'Diproses', 'Menunggu Paraf', 'Menunggu Persetujuan', 'Menunggu Tanda Tangan', 'Revisi Konsep', 'Selesai'] as $status)
                                        <option value="{{ $status }}" @selected($selectedLetter->status === $status)>{{ $status }}</option>
                                    @endforeach
                                </select>
                                <button type="button" wire:click="markDone({{ $selectedLetter->id }})" class="min-h-11 rounded-lg border border-slate-200 px-4 text-sm font-bold hover:border-teal-600">Tandai Selesai</button>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        @if ($showLetterForm)
            <div wire:click.self="$set('showLetterForm', false)" class="fixed inset-0 z-20 grid place-items-center bg-slate-950/50 p-4">
                <form wire:submit="saveLetter" class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white p-6 shadow-xl">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase text-teal-700">Pencatatan Dokumen</p>
                            <h2 class="mt-1 text-xl font-bold">{{ $editingLetterId ? 'Edit Surat' : 'Catat Surat Baru' }}</h2>
                        </div>
                        <button type="button" wire:click="$set('showLetterForm', false)" class="grid h-10 w-10 place-items-center rounded-lg bg-slate-100 text-xl font-bold">×</button>
                    </div>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Jenis Surat
                            <select wire:model.live="type" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                                <option>Masuk</option>
                                <option>Keluar</option>
                            </select>
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Unit Surat
                            <select wire:model.live="unitCode" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                                @foreach ($units as $unit)
                                    <option value="{{ $unit['code'] }}">{{ $unit['code'] }} - {{ $unit['name'] }}</option>
                                @endforeach
                            </select>
                            @error('unitCode') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600 sm:col-span-2">
                            Kode Klasifikasi Arsip
                            <select wire:model.live="classificationCode" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                                <option value="">Pilih kode arsip Permendagri 83/2022</option>
                                @foreach ($classifications as $classification)
                                    <option value="{{ $classification->code }}">{{ $classification->code }} - {{ $classification->name }}</option>
                                @endforeach
                            </select>
                            <span class="text-xs font-normal text-slate-500">Untuk surat keluar, kode ini dipakai sebagai kode awal nomor surat otomatis.</span>
                            @error('classificationCode') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        @if ($type === 'Keluar')
                            <div class="grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 sm:col-span-2">
                                <div class="font-bold text-slate-800">Pilih Cara Membuat Surat Keluar</div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <label class="flex cursor-pointer gap-3 rounded-lg border bg-white p-4 {{ $outgoingInputMode === 'template' ? 'border-teal-600 ring-2 ring-teal-100' : 'border-slate-200' }}">
                                        <input wire:model.live="outgoingInputMode" type="radio" value="template" class="mt-1 h-4 w-4 border-slate-300 text-teal-700">
                                        <span>
                                            <span class="block font-bold text-slate-800">Buat dari Template</span>
                                            <span class="mt-1 block text-sm font-normal text-slate-500">Tulis naskah di sistem, lalu export PDF/DOCX.</span>
                                        </span>
                                    </label>
                                    <label class="flex cursor-pointer gap-3 rounded-lg border bg-white p-4 {{ $outgoingInputMode === 'upload' ? 'border-teal-600 ring-2 ring-teal-100' : 'border-slate-200' }}">
                                        <input wire:model.live="outgoingInputMode" type="radio" value="upload" class="mt-1 h-4 w-4 border-slate-300 text-teal-700">
                                        <span>
                                            <span class="block font-bold text-slate-800">Upload Surat Jadi</span>
                                            <span class="mt-1 block text-sm font-normal text-slate-500">Nomor tetap dicatat, file final wajib diunggah.</span>
                                        </span>
                                    </label>
                                </div>
                                @error('outgoingInputMode') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </div>
                        @endif
                        @if ($type === 'Masuk')
                            <label class="grid gap-1 text-sm font-bold text-slate-600">
                                Nomor Agenda
                                <input wire:model="agendaNumber" type="text" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                                @error('agendaNumber') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>
                            <label class="grid gap-1 text-sm font-bold text-slate-600">
                                Tanggal Diterima
                                <input wire:model="receivedDate" type="date" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                                @error('receivedDate') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>
                            <label class="grid gap-1 text-sm font-bold text-slate-600">
                                Sifat Surat
                                <select wire:model="nature" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                                    <option>Biasa</option>
                                    <option>Penting</option>
                                    <option>Rahasia</option>
                                    <option>Sangat Rahasia</option>
                                </select>
                                @error('nature') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>
                            <label class="grid gap-1 text-sm font-bold text-slate-600">
                                Prioritas
                                <select wire:model="urgency" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                                    <option>Normal</option>
                                    <option>Segera</option>
                                    <option>Sangat Segera</option>
                                </select>
                                @error('urgency') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>
                            <label class="grid gap-1 text-sm font-bold text-slate-600 sm:col-span-2">
                                Batas Waktu Tindak Lanjut
                                <input wire:model="dueDate" type="date" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                                @error('dueDate') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>
                        @endif
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Tanggal Surat
                            <input wire:model="letterDate" type="date" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                            @error('letterDate') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Nomor Surat
                            <input wire:model="number" type="text" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950" placeholder="Otomatis untuk surat keluar">
                            @error('number') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            {{ $type === 'Keluar' ? 'Tujuan Surat' : 'Pihak Luar' }}
                            <input wire:model="externalParty" type="text" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950" placeholder="{{ $type === 'Keluar' ? 'Nama penerima atau instansi tujuan' : 'Pengirim atau penerima' }}">
                            @error('externalParty') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600 sm:col-span-2">
                            Perihal
                            <input wire:model="subject" type="text" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950" placeholder="Ringkasan perihal surat">
                            @error('subject') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <div class="grid gap-4 rounded-lg border border-slate-200 bg-slate-50 p-4 sm:col-span-2">
                            <div>
                                <div class="font-bold text-slate-800">Arsip Fisik dan Retensi</div>
                                <p class="text-sm text-slate-500">Catat tempat simpan dokumen asli dan masa simpan arsip.</p>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="grid gap-1 text-sm font-bold text-slate-600">
                                    Lokasi Arsip Fisik
                                    <input wire:model="archiveLocation" type="text" class="min-h-11 rounded-lg border border-slate-200 bg-white px-3 text-slate-950" placeholder="Contoh: Ruang Arsip Lantai 2">
                                    @error('archiveLocation') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                                </label>
                                <label class="grid gap-1 text-sm font-bold text-slate-600">
                                    Boks / Map / Rak
                                    <input wire:model="archiveBox" type="text" class="min-h-11 rounded-lg border border-slate-200 bg-white px-3 text-slate-950" placeholder="Contoh: Rak A-03 / Boks 12">
                                    @error('archiveBox') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                                </label>
                                <label class="grid gap-1 text-sm font-bold text-slate-600">
                                    Kategori Retensi
                                    <select wire:model="retentionCategory" class="min-h-11 rounded-lg border border-slate-200 bg-white px-3 text-slate-950">
                                        @foreach (['Aktif', 'Inaktif', 'Permanen', 'Siap Musnah', 'Dimusnahkan'] as $retentionOption)
                                            <option value="{{ $retentionOption }}">{{ $retentionOption }}</option>
                                        @endforeach
                                    </select>
                                    @error('retentionCategory') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                                </label>
                                <label class="grid gap-1 text-sm font-bold text-slate-600">
                                    Batas Retensi
                                    <input wire:model="retentionUntil" type="date" class="min-h-11 rounded-lg border border-slate-200 bg-white px-3 text-slate-950">
                                    @error('retentionUntil') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                                </label>
                            </div>
                            <label class="grid gap-1 text-sm font-bold text-slate-600">
                                Catatan Arsip
                                <textarea wire:model="archiveNotes" rows="3" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-slate-950" placeholder="Catatan lokasi, kondisi fisik, atau jadwal pemindahan arsip."></textarea>
                                @error('archiveNotes') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>
                        </div>
                        @if ($type === 'Keluar' && $outgoingInputMode === 'template')
                            <div class="grid gap-4 rounded-lg border border-teal-100 bg-teal-50 p-4 sm:col-span-2">
                                <div>
                                    <div class="font-bold text-slate-800">Template Surat Keluar</div>
                                    <p class="text-sm text-slate-600">Kop surat memakai data Profil Instansi. Nomor surat, tujuan, dan perihal mengikuti isian di atas.</p>
                                </div>
                                <label class="grid gap-1 text-sm font-bold text-slate-600">
                                    Isi Surat
                                    <textarea wire:model="outgoingBody" rows="8" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-slate-950"></textarea>
                                    @error('outgoingBody') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                                </label>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <label class="grid gap-1 text-sm font-bold text-slate-600">
                                        Nama Penandatangan
                                        <input wire:model="signerName" type="text" class="min-h-11 rounded-lg border border-slate-200 bg-white px-3 text-slate-950" placeholder="Nama pejabat">
                                        @error('signerName') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                                    </label>
                                    <label class="grid gap-1 text-sm font-bold text-slate-600">
                                        Jabatan Penandatangan
                                        <input wire:model="signerTitle" type="text" class="min-h-11 rounded-lg border border-slate-200 bg-white px-3 text-slate-950" placeholder="Jabatan">
                                        @error('signerTitle') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                                    </label>
                                </div>
                            </div>
                        @endif
                        <label class="grid gap-1 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4 text-sm font-bold text-slate-600 sm:col-span-2">
                            {{ $type === 'Keluar' && $outgoingInputMode === 'upload' ? 'Upload Surat Jadi' : 'File Surat Utama' }}
                            <input wire:model="document" type="file" accept=".pdf,.jpg,.jpeg,.png" class="rounded-lg border border-slate-200 bg-white p-2 text-slate-950">
                            <span class="text-xs font-normal text-slate-500">
                                PDF, JPG, JPEG, atau PNG. Maksimal 5 MB. {{ $type === 'Keluar' && $outgoingInputMode === 'upload' ? 'Wajib untuk mode upload surat jadi.' : '' }}
                            </span>
                            @if ($editingLetter?->file_path)
                                <div class="rounded-lg bg-white p-3 text-xs font-normal text-slate-600 ring-1 ring-slate-200">
                                    <div class="font-bold text-slate-800">File lama masih tersimpan</div>
                                    <div class="mt-1 break-all">{{ basename($editingLetter->file_path) }}</div>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <a href="{{ route('letters.document.review', $editingLetter) }}" target="_blank" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-700 hover:border-teal-600">Review</a>
                                        <a href="{{ route('letters.document.download', $editingLetter) }}" class="rounded-lg bg-teal-700 px-3 py-1.5 text-xs font-bold text-white hover:bg-teal-800">Download</a>
                                    </div>
                                    <div class="mt-2 text-slate-500">Upload file baru hanya jika ingin mengganti dokumen.</div>
                                </div>
                            @endif
                            @error('document') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <div class="grid gap-4 rounded-lg border border-slate-200 bg-slate-50 p-4 sm:col-span-2">
                            <div>
                                <div class="font-bold text-slate-700">Dokumen Tambahan</div>
                                <p class="text-sm text-slate-500">Bisa pilih lebih dari satu file untuk setiap kategori.</p>
                            </div>
                            <label class="grid gap-1 text-sm font-bold text-slate-600">
                                Lampiran
                                <input wire:model="attachmentFiles" type="file" multiple accept=".pdf,.jpg,.jpeg,.png" class="rounded-lg border border-slate-200 bg-white p-2 text-slate-950">
                                @error('attachmentFiles.*') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>
                            <label class="grid gap-1 text-sm font-bold text-slate-600">
                                Nota Dinas
                                <input wire:model="memoFiles" type="file" multiple accept=".pdf,.jpg,.jpeg,.png" class="rounded-lg border border-slate-200 bg-white p-2 text-slate-950">
                                @error('memoFiles.*') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>
                            <label class="grid gap-1 text-sm font-bold text-slate-600">
                                Dokumen Pendukung
                                <input wire:model="supportingFiles" type="file" multiple accept=".pdf,.jpg,.jpeg,.png" class="rounded-lg border border-slate-200 bg-white p-2 text-slate-950">
                                @error('supportingFiles.*') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" wire:click="$set('showLetterForm', false)" class="min-h-11 rounded-lg border border-slate-200 px-4 text-sm font-bold">Batal</button>
                        <button type="submit" class="min-h-11 rounded-lg bg-teal-700 px-4 text-sm font-bold text-white hover:bg-teal-800">{{ $editingLetterId ? 'Simpan Perubahan' : 'Simpan Surat' }}</button>
                    </div>
                </form>
            </div>
        @endif

        @if ($showRevisionModal)
            <div wire:click.self="$set('showRevisionModal', false)" class="fixed inset-0 z-30 grid place-items-center bg-slate-950/50 p-4">
                <form wire:submit="rejectApprovalStep" class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-lg bg-white p-6 shadow-xl">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase text-rose-700">Revisi Konsep Surat</p>
                            <h2 class="mt-1 text-xl font-bold">Tolak / Kembalikan Konsep</h2>
                        </div>
                        <button type="button" wire:click="$set('showRevisionModal', false)" class="grid h-10 w-10 place-items-center rounded-lg bg-slate-100 text-xl font-bold">&times;</button>
                    </div>

                    <label class="mt-5 grid gap-1 text-sm font-bold text-slate-600">
                        Catatan Revisi
                        <textarea wire:model="revisionNote" rows="5" class="rounded-lg border border-slate-200 px-3 py-2 text-slate-950" placeholder="Tuliskan alasan penolakan atau bagian yang harus direvisi."></textarea>
                        @error('revisionNote') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                    </label>

                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" wire:click="$set('showRevisionModal', false)" class="min-h-11 rounded-lg border border-slate-200 px-4 text-sm font-bold">Batal</button>
                        <button type="submit" class="min-h-11 rounded-lg bg-rose-700 px-4 text-sm font-bold text-white hover:bg-rose-800">Kembalikan untuk Revisi</button>
                    </div>
                </form>
            </div>
        @endif

        @if ($showDispositionForm)
            <div wire:click.self="$set('showDispositionForm', false)" class="fixed inset-0 z-20 grid place-items-center bg-slate-950/50 p-4">
                <form wire:submit="saveDisposition" class="w-full max-w-xl rounded-lg bg-white p-6 shadow-xl">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase text-teal-700">Disposisi Elektronik</p>
                            <h2 class="mt-1 text-xl font-bold">Kirim Instruksi</h2>
                        </div>
                        <button type="button" wire:click="$set('showDispositionForm', false)" class="grid h-10 w-10 place-items-center rounded-lg bg-slate-100 text-xl font-bold">×</button>
                    </div>

                    <div class="mt-5 grid gap-4">
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Penerima Disposisi
                            <div class="grid max-h-56 gap-2 overflow-y-auto rounded-lg border border-slate-200 bg-white p-2">
                                @foreach ($dispositionRecipients as $recipient)
                                    <label class="flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" wire:key="dashboard-disposition-recipient-{{ $recipient->id }}">
                                        <input wire:model="recipientIds" type="checkbox" value="{{ $recipient->id }}" class="h-4 w-4 rounded border-slate-300 text-teal-700">
                                        <span>{{ $recipient->name }}{{ $recipient->position ? ' - '.$recipient->position : '' }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('recipientIds') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            @error('recipientIds.*') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Instruksi
                            <textarea wire:model="instruction" rows="4" class="rounded-lg border border-slate-200 px-3 py-2 text-slate-950" placeholder="Tuliskan instruksi tindak lanjut..."></textarea>
                            @error('instruction') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            File Disposisi
                            <input wire:model="dispositionScan" type="file" accept=".pdf,.jpg,.jpeg,.png" class="rounded-lg border border-slate-200 bg-white p-2 text-slate-950">
                            <span class="text-xs font-normal text-slate-500">Opsional. Unggah PDF/JPG/PNG jika disposisi memiliki file pendukung.</span>
                            @error('dispositionScan') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" wire:click="$set('showDispositionForm', false)" class="min-h-11 rounded-lg border border-slate-200 px-4 text-sm font-bold">Batal</button>
                        <button type="submit" class="min-h-11 rounded-lg bg-teal-700 px-4 text-sm font-bold text-white hover:bg-teal-800">Kirim Disposisi</button>
                    </div>
                </form>
            </div>
        @endif
    </main>

    <div x-show="showToast"
         x-transition
         x-cloak
         class="fixed bottom-6 right-6 z-30 max-w-sm rounded-lg bg-slate-900 px-4 py-3 text-sm font-semibold text-white shadow-lg"
         x-text="toast"></div>
</div>
