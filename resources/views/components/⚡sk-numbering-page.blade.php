<?php

use App\Models\ActivityLog;
use App\Models\AppSetting;
use App\Models\DecisionLetterNumber;
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

    public string $unitCode = 'SET-MRP';

    public string $classificationCode = 'SK';

    public int $year;

    public bool $includeYear = true;

    public string $title = '';

    public string $decisionDate = '';

    public string $sequenceOverride = '';

    public string $status = 'Dipesan';

    public string $notes = '';

    public $decisionFile = null;

    public string $search = '';

    public string $statusFilter = 'Semua';

    public int $perPage = 10;

    public bool $showNumberModal = false;

    public bool $showDetailModal = false;

    public ?int $selectedRecordId = null;

    public ?int $editingRecordId = null;

    public function mount(): void
    {
        $settings = AppSetting::getValue('sk_numbering', ['include_year' => true, 'classification_code' => 'SK']);

        $this->unitCode = AppSetting::defaultLetterUnitCode();
        $this->classificationCode = (string) ($settings['classification_code'] ?? 'SK');
        $this->year = (int) now()->format('Y');
        $this->includeYear = (bool) ($settings['include_year'] ?? true);
        $this->decisionDate = now()->toDateString();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['unitCode', 'year', 'search', 'statusFilter'], true)) {
            $this->resetPage();
        }

        if (in_array($property, ['includeYear', 'classificationCode'], true)) {
            $this->saveSkNumberingSettings();
        }
    }

    public function units(): array
    {
        return AppSetting::letterUnits();
    }

    public function statuses(): array
    {
        return ['Dipesan', 'Dipakai', 'Batal'];
    }

    public function records()
    {
        return DecisionLetterNumber::query()
            ->with('creator')
            ->where('unit_code', $this->unitCode)
            ->where('year', $this->year)
            ->when($this->statusFilter !== 'Semua', fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->search !== '', function ($query) {
                $search = '%'.$this->search.'%';

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('number', 'like', $search)
                        ->orWhere('classification_code', 'like', $search)
                        ->orWhere('title', 'like', $search)
                        ->orWhere('notes', 'like', $search);
                });
            })
            ->orderByDesc('sequence')
            ->paginate($this->perPage);
    }

    public function selectedRecord(): ?DecisionLetterNumber
    {
        return $this->selectedRecordId
            ? DecisionLetterNumber::with('creator')->find($this->selectedRecordId)
            : null;
    }

    public function nextSequence(): int
    {
        return ((int) DecisionLetterNumber::query()
            ->where('unit_code', $this->unitCode)
            ->where('year', $this->year)
            ->max('sequence')) + 1;
    }

    public function nextNumber(): string
    {
        return $this->formatNumber($this->sequenceOverride !== '' ? (int) $this->sequenceOverride : $this->nextSequence());
    }

    public function formatNumber(int $sequence): string
    {
        $parts = [$this->normalizedClassificationCode(), str_pad((string) $sequence, 3, '0', STR_PAD_LEFT), $this->unitCode];

        if ($this->includeYear) {
            $parts[] = (string) $this->year;
        }

        return implode('/', $parts);
    }

    public function normalizedClassificationCode(): string
    {
        $code = strtoupper(trim($this->classificationCode));

        return $code !== '' ? $code : 'SK';
    }

    public function missingItems(): array
    {
        $used = DecisionLetterNumber::query()
            ->where('unit_code', $this->unitCode)
            ->where('year', $this->year)
            ->pluck('sequence')
            ->map(fn ($sequence) => (int) $sequence)
            ->unique()
            ->sort()
            ->values()
            ->all();
        $max = max($this->nextSequence() - 1, $used ? max($used) : 0);

        return collect(range(1, max(1, $max)))
            ->reject(fn (int $sequence) => in_array($sequence, $used, true))
            ->take(30)
            ->map(fn (int $sequence) => [
                'sequence' => $sequence,
                'number' => $this->formatNumber($sequence),
            ])
            ->values()
            ->all();
    }

    public function useSequence(int $sequence): void
    {
        $this->openNumberModal($sequence);
    }

    public function openNumberModal(?int $sequence = null): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $this->resetValidation();
        $this->editingRecordId = null;
        $this->resetForm();

        if ($sequence) {
            $this->sequenceOverride = (string) $sequence;
        }

        $this->showNumberModal = true;
    }

    public function openDetailModal(int $recordId): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $this->selectedRecordId = $recordId;
        $this->showDetailModal = true;
    }

    public function openEditModal(int $recordId): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $record = DecisionLetterNumber::findOrFail($recordId);

        $this->resetValidation();
        $this->editingRecordId = $record->id;
        $this->unitCode = $record->unit_code;
        $this->classificationCode = $record->classification_code ?: 'SK';
        $this->year = (int) $record->year;
        $this->title = $record->title;
        $this->decisionDate = $record->decision_date?->toDateString() ?: now()->toDateString();
        $this->sequenceOverride = (string) $record->sequence;
        $this->status = $record->status;
        $this->notes = $record->notes ?: '';
        $this->decisionFile = null;
        $this->showDetailModal = false;
        $this->showNumberModal = true;
    }

    public function saveNumber(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $validated = $this->validate([
            'unitCode' => ['required', Rule::in(AppSetting::letterUnitCodes())],
            'classificationCode' => ['required', 'string', 'max:40', 'not_regex:/[\/\\\\]/'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'includeYear' => ['boolean'],
            'title' => ['required', 'string', 'max:255'],
            'decisionDate' => ['nullable', 'date'],
            'sequenceOverride' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', Rule::in($this->statuses())],
            'notes' => ['nullable', 'string', 'max:1000'],
            'decisionFile' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        $sequence = $validated['sequenceOverride'] !== '' && $validated['sequenceOverride'] !== null
            ? (int) $validated['sequenceOverride']
            : $this->nextSequence();

        $editingRecord = $this->editingRecordId
            ? DecisionLetterNumber::findOrFail($this->editingRecordId)
            : null;
        $duplicateQuery = DecisionLetterNumber::query()
            ->where('unit_code', $validated['unitCode'])
            ->where('year', $validated['year'])
            ->where('sequence', $sequence);

        if ($editingRecord) {
            $duplicateQuery->whereKeyNot($editingRecord->id);
        }

        if ($duplicateQuery->exists()) {
            $this->addError('sequenceOverride', 'Nomor urut SK ini sudah dipakai.');

            return;
        }

        $fileData = [];

        if ($this->decisionFile) {
            if ($editingRecord?->file_path && Storage::disk('public')->exists($editingRecord->file_path)) {
                Storage::disk('public')->delete($editingRecord->file_path);
            }

            $path = $this->decisionFile->store('file-sk', 'public');
            $fileData = [
                'file_path' => $path,
                'file_original_name' => $this->decisionFile->getClientOriginalName(),
                'file_mime_type' => $this->decisionFile->getMimeType(),
                'file_size' => $this->decisionFile->getSize(),
            ];
        }

        $data = [
            'unit_code' => $validated['unitCode'],
            'classification_code' => $this->normalizedClassificationCode(),
            'sequence' => $sequence,
            'year' => (int) $validated['year'],
            'number' => $this->formatNumber($sequence),
            'title' => $validated['title'],
            'decision_date' => $validated['decisionDate'] ?: null,
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?: null,
            'created_by' => auth()->id(),
        ] + $fileData;

        if ($editingRecord) {
            unset($data['created_by']);
            $editingRecord->update($data);
            $record = $editingRecord->fresh();
            ActivityLog::record('sk_number.updated', 'Nomor SK diperbarui: '.$record->number, $record);
            $message = 'Nomor SK berhasil diperbarui.';
        } else {
            $record = DecisionLetterNumber::create($data);
            ActivityLog::record('sk_number.created', 'Nomor SK dicatat: '.$record->number, $record);
            $message = 'Nomor SK berhasil dicatat.';
        }

        $this->saveSkNumberingSettings();
        $this->resetForm();
        $this->editingRecordId = null;
        $this->showNumberModal = false;
        $this->dispatch('notify', message: $message);
    }

    public function updateStatus(int $recordId, string $status): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        abort_unless(in_array($status, $this->statuses(), true), 422);

        $record = DecisionLetterNumber::findOrFail($recordId);
        $record->update(['status' => $status]);

        ActivityLog::record('sk_number.status_updated', 'Status nomor SK '.$record->number.' menjadi '.$status.'.', $record);
        $this->dispatch('notify', message: 'Status nomor SK diperbarui.');
    }

    public function deleteNumber(int $recordId): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $record = DecisionLetterNumber::findOrFail($recordId);

        if ($record->file_path && Storage::disk('public')->exists($record->file_path)) {
            Storage::disk('public')->delete($record->file_path);
        }

        ActivityLog::record('sk_number.deleted', 'Nomor SK dihapus: '.$record->number, $record);
        $record->delete();

        if ($this->selectedRecordId === $recordId) {
            $this->selectedRecordId = null;
            $this->showDetailModal = false;
        }

        $this->dispatch('notify', message: 'Nomor SK berhasil dihapus.');
    }

    public function resetForm(): void
    {
        $this->title = '';
        $this->decisionDate = now()->toDateString();
        $this->sequenceOverride = '';
        $this->status = 'Dipesan';
        $this->notes = '';
        $this->decisionFile = null;
    }

    public function saveSkNumberingSettings(): void
    {
        AppSetting::putValue('sk_numbering', [
            'include_year' => $this->includeYear,
            'classification_code' => $this->normalizedClassificationCode(),
        ]);
    }

    public function statusClass(string $status): string
    {
        return match ($status) {
            'Dipesan' => 'bg-amber-100 text-amber-700 ring-amber-200',
            'Dipakai' => 'bg-emerald-100 text-emerald-700 ring-emerald-200',
            'Batal' => 'bg-rose-100 text-rose-700 ring-rose-200',
            default => 'bg-slate-100 text-slate-700 ring-slate-200',
        };
    }
};
?>

<div x-data="{ toast: '', showToast: false }"
     x-on:notify.window="toast = $event.detail.message; showToast = true; setTimeout(() => showToast = false, 2600)"
     class="min-h-screen lg:grid lg:grid-cols-[280px_minmax(0,1fr)]">
    @php
        $currentUser = auth()->user();
        $agencyProfile = AppSetting::agency();
        $taskCount = TaskInbox::countFor($currentUser);
        $units = $this->units();
        $records = $this->records();
        $missingItems = $this->missingItems();
        $selectedRecord = $this->selectedRecord();
    @endphp

    <aside class="bg-slate-900 px-5 py-5 text-slate-100 lg:sticky lg:top-0 lg:z-10 lg:h-screen lg:overflow-y-auto">
        <div class="flex items-center gap-3">
            <x-app-logo class="h-11 w-11" />
            <div>
                <div class="font-semibold">{{ $agencyProfile['app_name'] }}</div>
                <div class="text-sm text-slate-400">{{ $agencyProfile['name'] }}</div>
            </div>
        </div>

        <nav class="mt-8 grid gap-2">
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5"><x-icon name="dashboard" class="h-4 w-4" />Dasbor</a>
            <a href="{{ route('tracking') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5"><x-icon name="route" class="h-4 w-4" />Pelacakan Surat</a>
            <a href="{{ route('my-tasks') }}" class="flex items-center justify-between rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5">
                <span class="inline-flex items-center gap-2"><x-icon name="task" class="h-4 w-4" />Tugas Saya</span>
                @if ($taskCount > 0)
                    <span class="rounded-full bg-rose-500 px-2 py-0.5 text-xs font-bold text-white">{{ $taskCount }}</span>
                @endif
            </a>
            <a href="{{ route('number-monitor') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5"><x-icon name="list-numbered" class="h-4 w-4" />Monitoring Nomor</a>
            <a href="{{ route('sk-numbering') }}" class="inline-flex items-center gap-2 rounded-lg bg-white/10 px-3 py-2 text-sm font-semibold text-white"><x-icon name="gavel" class="h-4 w-4" />Penomoran SK</a>
            @if ($currentUser?->isAdmin() || $currentUser?->isLeader() || $currentUser?->isPersonalSecretary())
                <a href="{{ route('leadership') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5"><x-icon name="send" class="h-4 w-4" />Halaman Pimpinan</a>
            @endif
            @if ($currentUser?->isAdmin() || $currentUser?->isDepartmentHead())
                <a href="{{ route('department-head') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5"><x-icon name="users" class="h-4 w-4" />Halaman Kepala Bagian</a>
            @endif
            <a href="{{ route('settings') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5"><x-icon name="settings" class="h-4 w-4" />Setting</a>
        </nav>

        <div class="mt-10 hidden border-t border-white/10 pt-5 lg:block">
            <div class="text-sm font-semibold">{{ $currentUser->name }}</div>
            <div class="text-xs text-slate-400">{{ $currentUser->role }}</div>
        </div>
    </aside>

    <main class="min-w-0 px-4 py-6 sm:px-6 lg:px-8">
        <header class="sticky top-0 z-10 -mx-4 flex flex-col gap-4 border-b border-slate-200 bg-slate-100/95 px-4 py-4 backdrop-blur sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <p class="text-xs font-bold uppercase text-teal-700">Penomoran</p>
                <h1 class="mt-1 text-3xl font-bold tracking-normal text-slate-950">Penomoran Surat Keputusan</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-600">Catat nomor SK, lihat nomor berikutnya, dan pantau nomor urut yang kosong pada unit dan tahun terpilih.</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <button type="button" wire:click="openNumberModal" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-teal-700 px-4 text-sm font-bold text-white shadow-sm hover:bg-teal-800">
                    <x-icon name="plus" class="mr-2 h-4 w-4" />
                    Tambah Nomor SK
                </button>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-800 shadow-sm hover:border-rose-500">
                        <x-icon name="logout" class="mr-2 h-4 w-4" />
                        Logout
                    </button>
                </form>
            </div>
        </header>

        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-lg font-bold">Nomor Kosong</h2>
                    <p class="mt-1 text-sm text-slate-500">Nomor urut kosong pada {{ $unitCode }} tahun {{ $year }}.</p>
                </div>
                <button type="button" wire:click="openNumberModal" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-teal-700 px-4 text-sm font-bold text-white hover:bg-teal-800">
                    <x-icon name="plus" class="mr-2 h-4 w-4" />
                    Tambah Nomor SK
                </button>
            </div>
                <div class="mt-4 grid gap-2">
                    @forelse ($missingItems as $item)
                        <button type="button" wire:click="useSequence({{ $item['sequence'] }})" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-left text-sm font-bold text-amber-900 hover:border-amber-500">
                            <span class="inline-flex items-center gap-2"><x-icon name="tag" class="h-4 w-4" />{{ $item['number'] }}</span>
                        </button>
                    @empty
                        <p class="text-sm text-slate-500">Belum ada nomor kosong.</p>
                    @endforelse
                </div>
        </section>

        <section class="mt-5 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="grid gap-3 border-b border-slate-200 p-5 md:grid-cols-[minmax(0,1fr)_220px]">
                <label class="grid gap-1 text-sm font-bold text-slate-600">
                    Cari Nomor SK
                    <input wire:model.live.debounce.300ms="search" type="search" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950" placeholder="Nomor, judul, atau catatan...">
                </label>
                <label class="grid gap-1 text-sm font-bold text-slate-600">
                    Status
                    <select wire:model.live="statusFilter" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                        <option>Semua</option>
                        @foreach ($this->statuses() as $statusOption)
                            <option value="{{ $statusOption }}">{{ $statusOption }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1040px] border-collapse text-left text-sm">
                    <thead class="text-xs uppercase text-slate-500">
                        <tr>
                            <th class="border-b border-slate-200 px-5 py-3">Nomor SK</th>
                            <th class="border-b border-slate-200 px-5 py-3">Judul</th>
                            <th class="border-b border-slate-200 px-5 py-3">Tanggal</th>
                            <th class="border-b border-slate-200 px-5 py-3">Pencatat</th>
                            <th class="border-b border-slate-200 px-5 py-3">Status</th>
                            <th class="border-b border-slate-200 px-5 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($records as $record)
                            <tr>
                                <td class="border-b border-slate-100 px-5 py-4">
                                    <div class="font-bold">{{ $record->number }}</div>
                                    <div class="mt-1 inline-flex items-center gap-1 text-xs text-slate-500"><x-icon name="pin" class="h-3.5 w-3.5" />Urut {{ str_pad((string) $record->sequence, 3, '0', STR_PAD_LEFT) }}</div>
                                </td>
                                <td class="border-b border-slate-100 px-5 py-4">
                                    <div class="font-semibold">{{ $record->title }}</div>
                                    @if ($record->notes)
                                        <div class="mt-1 text-xs text-slate-500">{{ $record->notes }}</div>
                                    @endif
                                    @if ($record->file_path)
                                        <div class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-teal-700"><x-icon name="paperclip" class="h-3.5 w-3.5" />File SK tersedia</div>
                                    @endif
                                </td>
                                <td class="border-b border-slate-100 px-5 py-4">{{ $record->decision_date?->translatedFormat('d M Y') ?: '-' }}</td>
                                <td class="border-b border-slate-100 px-5 py-4">{{ $record->creator?->name ?: 'Sistem' }}</td>
                                <td class="border-b border-slate-100 px-5 py-4">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $this->statusClass($record->status) }}">
                                            <x-icon :name="$record->status === 'Dipesan' ? 'bookmark' : ($record->status === 'Dipakai' ? 'check' : 'cancel')" class="h-3.5 w-3.5" />
                                            {{ $record->status }}
                                        </span>
                                        <select wire:change="updateStatus({{ $record->id }}, $event.target.value)" class="min-h-9 rounded-lg border border-slate-200 bg-white px-2 text-xs font-semibold">
                                            @foreach ($this->statuses() as $statusOption)
                                                <option value="{{ $statusOption }}" @selected($record->status === $statusOption)>{{ $statusOption }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </td>
                                <td class="border-b border-slate-100 px-5 py-4">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <button type="button" wire:click="openDetailModal({{ $record->id }})" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold hover:border-teal-600"><x-icon name="eye" class="h-3.5 w-3.5" />Detail</button>
                                        <button type="button" wire:click="openEditModal({{ $record->id }})" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold hover:border-teal-600"><x-icon name="edit" class="h-3.5 w-3.5" />Edit</button>
                                        <button type="button" wire:click="deleteNumber({{ $record->id }})" wire:confirm="Hapus nomor SK ini?" class="inline-flex items-center gap-1 rounded-lg border border-rose-200 bg-white px-3 py-1.5 text-xs font-bold text-rose-700 hover:border-rose-500"><x-icon name="trash" class="h-3.5 w-3.5" />Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-slate-500">Belum ada nomor SK pada filter ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($records->hasPages())
                <div class="border-t border-slate-200 p-5">{{ $records->links() }}</div>
            @endif
        </section>

        @if ($showNumberModal)
            <div wire:click.self="$set('showNumberModal', false)" class="fixed inset-0 z-20 grid place-items-center bg-slate-950/50 p-4">
                <form wire:submit="saveNumber" class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-lg bg-white p-6 shadow-xl">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase text-teal-700">Penomoran SK</p>
                            <h2 class="mt-1 inline-flex items-center gap-2 text-xl font-bold"><x-icon :name="$editingRecordId ? 'edit' : 'plus'" class="h-5 w-5 text-teal-700" />{{ $editingRecordId ? 'Edit Nomor SK' : 'Tambah Nomor SK' }}</h2>
                        </div>
                        <button type="button" wire:click="$set('showNumberModal', false)" class="grid h-10 w-10 place-items-center rounded-lg bg-slate-100 text-xl font-bold">&times;</button>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-[180px_minmax(0,1fr)_150px]">
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Kode Klasifikasi
                            <div class="flex min-h-11 items-center gap-2 rounded-lg border border-slate-200 px-3">
                                <x-icon name="tag" class="h-4 w-4 text-slate-400" />
                                <input wire:model.live="classificationCode" type="text" class="min-w-0 flex-1 border-0 p-0 text-slate-950 outline-none focus:ring-0" placeholder="SK">
                            </div>
                            @error('classificationCode') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid min-w-0 gap-1 text-sm font-bold text-slate-600">
                            Unit SK
                            <select wire:model.live="unitCode" class="min-h-11 w-full rounded-lg border border-slate-200 px-3 text-slate-950">
                                @foreach ($units as $unit)
                                    <option value="{{ $unit['code'] }}">{{ $unit['code'] }} - {{ $unit['name'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Tahun Pencatatan
                            <input wire:model.live="year" type="number" min="2000" max="2100" disabled class="min-h-11 rounded-lg border border-slate-200 bg-slate-100 px-3 text-slate-500">
                        </label>
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-[220px_minmax(0,1fr)]">
                        <div class="grid gap-1 text-sm font-bold text-slate-600">
                            Format Nomor
                            <label class="flex min-h-11 items-center gap-3 rounded-lg border border-slate-200 bg-white px-3 text-sm font-bold text-slate-700">
                                <input wire:model.live="includeYear" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-teal-700">
                                Tampilkan Tahun
                            </label>
                        </div>
                        <div class="rounded-lg border border-teal-200 bg-teal-50 px-4 py-3">
                            <div class="text-sm font-bold text-teal-700">Nomor yang akan dicatat</div>
                            <div class="mt-1 flex items-start gap-2 break-all text-2xl font-bold leading-tight"><x-icon name="pin" class="mt-1 h-5 w-5 shrink-0" />{{ $this->nextNumber() }}</div>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4">
                        <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_200px]">
                            <label class="grid min-w-0 gap-1 text-sm font-bold text-slate-600">
                                Judul / Tentang SK
                                <input wire:model="title" type="text" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950" placeholder="Contoh: Penetapan panitia kegiatan">
                                @error('title') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>
                            <label class="grid gap-1 text-sm font-bold text-slate-600">
                                Tanggal SK
                                <input wire:model="decisionDate" type="date" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                                @error('decisionDate') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>
                        </div>

                        <div class="grid gap-4 md:grid-cols-[200px_minmax(0,1fr)]">
                            <label class="grid gap-1 text-sm font-bold text-slate-600">
                                Nomor Urut Khusus
                                <input wire:model.live="sequenceOverride" type="number" min="1" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950" placeholder="{{ $this->nextSequence() }}">
                                <span class="text-xs font-normal text-slate-500">Kosongkan untuk memakai nomor berikutnya.</span>
                                @error('sequenceOverride') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>
                            <label class="grid gap-1 text-sm font-bold text-slate-600">
                                Status
                                <select wire:model="status" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                                    @foreach ($this->statuses() as $statusOption)
                                        <option value="{{ $statusOption }}">{{ $statusOption }}</option>
                                    @endforeach
                                </select>
                                @error('status') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>
                        </div>

                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Catatan
                            <textarea wire:model="notes" rows="2" class="rounded-lg border border-slate-200 px-3 py-2 text-slate-950" placeholder="Catatan internal jika diperlukan..."></textarea>
                            @error('notes') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            File SK
                            <input wire:model="decisionFile" type="file" accept=".pdf,.jpg,.jpeg,.png" class="rounded-lg border border-slate-200 bg-white p-2 text-slate-950">
                            <span class="text-xs font-normal text-slate-500">Opsional. Unggah file SK dalam format PDF/JPG/PNG. Saat edit, kosongkan jika file lama tetap dipakai.</span>
                            @error('decisionFile') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    <div class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-4">
                        <button type="button" wire:click="resetForm" class="min-h-11 rounded-lg border border-slate-200 px-4 text-sm font-bold">Reset</button>
                        <button type="button" wire:click="$set('showNumberModal', false)" class="min-h-11 rounded-lg border border-slate-200 px-4 text-sm font-bold">Batal</button>
                        <button type="submit" class="min-h-11 rounded-lg bg-teal-700 px-4 text-sm font-bold text-white hover:bg-teal-800">{{ $editingRecordId ? 'Simpan Perubahan' : 'Simpan Nomor SK' }}</button>
                    </div>
                </form>
            </div>
        @endif

        @if ($showDetailModal)
            <div wire:click.self="$set('showDetailModal', false)" class="fixed inset-0 z-20 grid place-items-center bg-slate-950/50 p-4">
                @if ($selectedRecord)
                    <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white shadow-xl">
                        <div class="flex items-start justify-between gap-4 border-b border-slate-200 p-6">
                            <div>
                                <p class="text-xs font-bold uppercase text-teal-700">Detail SK</p>
                                <h2 class="mt-1 text-xl font-bold">{{ $selectedRecord->number }}</h2>
                                <p class="mt-1 text-sm text-slate-500">Urut {{ str_pad((string) $selectedRecord->sequence, 3, '0', STR_PAD_LEFT) }}</p>
                            </div>
                            <button type="button" wire:click="$set('showDetailModal', false)" class="grid h-10 w-10 place-items-center rounded-lg bg-slate-100 text-xl font-bold">&times;</button>
                        </div>

                        <div class="space-y-5 p-6">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <div class="text-xs font-bold uppercase text-slate-500">Kode Klasifikasi</div>
                                    <div class="font-semibold">{{ $selectedRecord->classification_code ?: 'SK' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-bold uppercase text-slate-500">Unit</div>
                                    <div class="font-semibold">{{ $selectedRecord->unit_code }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-bold uppercase text-slate-500">Tahun</div>
                                    <div class="font-semibold">{{ $selectedRecord->year }}</div>
                                </div>
                                <div class="sm:col-span-2">
                                    <div class="text-xs font-bold uppercase text-slate-500">Judul / Tentang SK</div>
                                    <div class="font-semibold">{{ $selectedRecord->title }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-bold uppercase text-slate-500">Tanggal SK</div>
                                    <div class="font-semibold">{{ $selectedRecord->decision_date?->translatedFormat('d M Y') ?: '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-bold uppercase text-slate-500">Pencatat</div>
                                    <div class="font-semibold">{{ $selectedRecord->creator?->name ?: 'Sistem' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-bold uppercase text-slate-500">Status</div>
                                    <span class="mt-1 inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $this->statusClass($selectedRecord->status) }}">
                                        <x-icon :name="$selectedRecord->status === 'Dipesan' ? 'bookmark' : ($selectedRecord->status === 'Dipakai' ? 'check' : 'cancel')" class="h-3.5 w-3.5" />
                                        {{ $selectedRecord->status }}
                                    </span>
                                </div>
                                <div>
                                    <div class="text-xs font-bold uppercase text-slate-500">Dicatat</div>
                                    <div class="font-semibold">{{ $selectedRecord->created_at?->translatedFormat('d M Y H:i') }}</div>
                                </div>
                            </div>

                            @if ($selectedRecord->notes)
                                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                    <div class="text-xs font-bold uppercase text-slate-500">Catatan</div>
                                    <p class="mt-2 text-sm text-slate-700">{{ $selectedRecord->notes }}</p>
                                </div>
                            @endif

                            <div class="rounded-lg border border-slate-200 p-4">
                                <h3 class="font-bold">File SK</h3>
                                @if ($selectedRecord->file_path)
                                    <div class="mt-2 text-sm font-semibold text-slate-600">{{ $selectedRecord->file_original_name ?: basename($selectedRecord->file_path) }}</div>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <a href="{{ route('sk-numbering.file.review', $selectedRecord) }}" target="_blank" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-bold hover:border-teal-600"><x-icon name="eye" class="h-4 w-4" />Buka File SK</a>
                                        <a href="{{ route('sk-numbering.file.download', $selectedRecord) }}" class="inline-flex items-center gap-1 rounded-lg bg-teal-700 px-3 py-1.5 text-sm font-bold text-white hover:bg-teal-800"><x-icon name="download" class="h-4 w-4" />Download</a>
                                    </div>
                                @else
                                    <p class="mt-2 text-sm text-slate-500">Belum ada file SK.</p>
                                @endif
                            </div>

                            <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-4">
                                <button type="button" wire:click="openEditModal({{ $selectedRecord->id }})" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-slate-200 px-4 text-sm font-bold"><x-icon name="edit" class="h-4 w-4" />Edit</button>
                                <button type="button" wire:click="deleteNumber({{ $selectedRecord->id }})" wire:confirm="Hapus nomor SK ini?" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-rose-200 px-4 text-sm font-bold text-rose-700"><x-icon name="trash" class="h-4 w-4" />Hapus</button>
                                <button type="button" wire:click="$set('showDetailModal', false)" class="min-h-11 rounded-lg bg-teal-700 px-4 text-sm font-bold text-white">Tutup</button>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="w-full max-w-lg rounded-lg bg-white p-6 text-center shadow-xl">
                        <p class="text-sm text-slate-500">Data SK tidak ditemukan.</p>
                        <button type="button" wire:click="$set('showDetailModal', false)" class="mt-4 min-h-11 rounded-lg border border-slate-200 px-4 text-sm font-bold">Tutup</button>
                    </div>
                @endif
            </div>
        @endif
    </main>

    <div x-show="showToast"
         x-transition
         x-cloak
         class="fixed bottom-6 right-6 z-30 max-w-sm rounded-lg bg-slate-900 px-4 py-3 text-sm font-semibold text-white shadow-lg"
         x-text="toast"></div>
</div>
