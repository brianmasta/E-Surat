<?php

use App\Models\AppSetting;
use App\Models\ActivityLog;
use App\Models\Disposition;
use App\Models\Letter;
use App\Models\LetterApproval;
use App\Support\TaskInbox;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $typeFilter = 'Semua';

    public string $statusFilter = 'Semua';

    public int $perPage = 10;

    public ?int $selectedLetterId = null;

    public bool $showDetailModal = false;

    public function mount(): void
    {
        $this->selectedLetterId = Letter::latest('letter_date')->latest()->value('id');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'typeFilter', 'statusFilter'], true)) {
            $this->resetPage();
        }
    }

    public function letters()
    {
        return Letter::query()
            ->with('classification')
            ->when($this->typeFilter !== 'Semua', fn ($query) => $query->where('type', $this->typeFilter))
            ->when($this->statusFilter !== 'Semua', function ($query) {
                $this->statusFilter === 'Disposisi Pimpinan'
                    ? $query->whereIn('status', ['Disposisi Pimpinan', 'Disposisi'])
                    : $query->where('status', $this->statusFilter);
            })
            ->when($this->search !== '', function ($query) {
                $search = '%'.$this->search.'%';

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('number', 'like', $search)
                        ->orWhere('agenda_number', 'like', $search)
                        ->orWhere('subject', 'like', $search)
                        ->orWhere('external_party', 'like', $search)
                        ->orWhere('classification_code', 'like', $search);
                });
            })
            ->latest('letter_date')
            ->latest()
            ->paginate($this->perPage);
    }

    public function selectedLetter(): ?Letter
    {
        return $this->selectedLetterId
            ? Letter::with('classification', 'attachments', 'dispositions.children', 'approvals')->find($this->selectedLetterId)
            : null;
    }

    public function selectLetter(int $letterId): void
    {
        $this->selectedLetterId = $letterId;
        $this->showDetailModal = true;
    }

    public function statuses(): array
    {
        return [
            'Semua',
            'Baru',
            'Disposisi Pimpinan',
            'Diproses',
            'Menunggu Paraf',
            'Menunggu Persetujuan',
            'Menunggu Tanda Tangan',
            'Revisi Konsep',
            'Selesai',
        ];
    }

    public function trackingSteps(?Letter $letter): array
    {
        if (! $letter) {
            return [];
        }

        $steps = $letter->type === 'Keluar'
            ? ['Dicatat', 'Menunggu Paraf', 'Menunggu Persetujuan', 'Menunggu Tanda Tangan', 'Selesai']
            : ['Dicatat', 'Menunggu Disposisi', 'Disposisi Pimpinan', 'Diproses', 'Selesai'];
        $currentIndex = match ($letter->status) {
            'Baru' => 1,
            'Disposisi Pimpinan', 'Disposisi' => 2,
            'Diproses' => 3,
            'Menunggu Paraf' => 1,
            'Menunggu Persetujuan' => 2,
            'Menunggu Tanda Tangan' => 3,
            'Selesai', 'Disetujui', 'Ditandatangani' => 4,
            default => 0,
        };

        return collect($steps)
            ->map(fn (string $label, int $index) => [
                'label' => $label,
                'done' => $index <= $currentIndex,
                'active' => $index === $currentIndex,
                ...$this->stepMetadata($letter, $label),
            ])
            ->all();
    }

    public function stepMetadata(Letter $letter, string $label): array
    {
        $history = $this->activityHistory($letter);
        $dispositions = $letter->dispositions->sortBy('created_at');

        $fromLog = function (?ActivityLog $log): ?array {
            if (! $log) {
                return null;
            }

            return [
                'actor' => $log->user?->name ?: 'Sistem',
                'time' => $log->created_at,
                'note' => $log->description,
            ];
        };

        $fallback = fn (?string $actor, mixed $time, ?string $note = null): array => [
            'actor' => $actor ?: 'Sistem',
            'time' => $time,
            'note' => $note,
        ];

        if ($label === 'Dicatat') {
            return $fromLog($history->first(fn (ActivityLog $log) => str_contains($log->action, 'letter.created')))
                ?: $fallback(null, $letter->created_at, 'Surat dicatat dalam sistem.');
        }

        if ($label === 'Menunggu Disposisi') {
            return $fallback(null, $letter->created_at, 'Menunggu arahan disposisi pimpinan.');
        }

        if ($label === 'Disposisi Pimpinan' || $label === 'Disposisi') {
            $firstDisposition = $dispositions->first(fn (Disposition $disposition) => $disposition->parent_id === null);
            $log = $firstDisposition
                ? $history->first(fn (ActivityLog $log) => $log->subject_type === $firstDisposition->getMorphClass()
                    && (int) $log->subject_id === (int) $firstDisposition->id
                    && str_contains($log->action, 'disposition.created'))
                : null;

            return $fromLog($log)
                ?: $fallback($firstDisposition?->input_by_name ?: $firstDisposition?->sender_name, $firstDisposition?->created_at, $firstDisposition?->instruction);
        }

        if ($label === 'Diproses') {
            $processingLog = $history->first(fn (ActivityLog $log) => str_contains($log->action, 'status_updated'));
            $processingDisposition = $dispositions
                ->filter(fn (Disposition $disposition) => in_array($disposition->status, ['Diproses', 'Selesai'], true))
                ->sortByDesc('updated_at')
                ->first();

            return $fromLog($processingLog)
                ?: $fallback($processingDisposition?->recipient_name, $processingDisposition?->updated_at, 'Disposisi sedang diproses.');
        }

        if ($label === 'Selesai') {
            $doneLog = $history->first(fn (ActivityLog $log) => str_contains($log->description, 'Selesai') || str_contains($log->description, 'selesai'));

            return $fromLog($doneLog)
                ?: $fallback(null, $letter->status === 'Selesai' ? $letter->updated_at : null, 'Surat selesai diproses.');
        }

        $approval = $letter->approvals->first(fn (LetterApproval $approval) => $approval->step === match ($label) {
            'Menunggu Paraf' => 'Paraf Konsep',
            'Menunggu Persetujuan' => 'Persetujuan Pimpinan',
            'Menunggu Tanda Tangan' => 'Tanda Tangan Elektronik',
            default => '',
        });

        return $fallback(
            $approval?->actor_name ?: $approval?->target_role,
            $approval?->acted_at ?: $approval?->updated_at,
            $approval ? $approval->step.' - '.$approval->status : null,
        );
    }

    public function activityHistory(?Letter $letter)
    {
        if (! $letter) {
            return collect();
        }

        $dispositionIds = $letter->dispositions->pluck('id')->all();
        $approvalIds = $letter->approvals->pluck('id')->all();

        return ActivityLog::query()
            ->with('user')
            ->where(function ($query) use ($letter, $dispositionIds, $approvalIds) {
                $query->where(function ($query) use ($letter) {
                    $query
                        ->where('subject_type', $letter->getMorphClass())
                        ->where('subject_id', $letter->id);
                });

                if ($dispositionIds) {
                    $query->orWhere(function ($query) use ($dispositionIds) {
                        $query
                            ->where('subject_type', (new Disposition())->getMorphClass())
                            ->whereIn('subject_id', $dispositionIds);
                    });
                }

                if ($approvalIds) {
                    $query->orWhere(function ($query) use ($approvalIds) {
                        $query
                            ->where('subject_type', (new LetterApproval())->getMorphClass())
                            ->whereIn('subject_id', $approvalIds);
                    });
                }
            })
            ->latest()
            ->limit(20)
            ->get();
    }

    public function statusClass(string $status): string
    {
        return match ($status) {
            'Baru' => 'bg-rose-100 text-rose-700 ring-rose-200',
            'Disposisi', 'Disposisi Pimpinan', 'Belum Dibaca', 'Menunggu' => 'bg-amber-100 text-amber-700 ring-amber-200',
            'Diproses', 'Menunggu Paraf', 'Menunggu Persetujuan', 'Menunggu Tanda Tangan' => 'bg-sky-100 text-sky-700 ring-sky-200',
            'Selesai', 'Disetujui', 'Ditandatangani' => 'bg-emerald-100 text-emerald-700 ring-emerald-200',
            'Revisi Konsep', 'Ditolak' => 'bg-rose-100 text-rose-700 ring-rose-200',
            default => 'bg-slate-100 text-slate-700 ring-slate-200',
        };
    }
};
?>

<div class="min-h-screen lg:grid lg:grid-cols-[280px_minmax(0,1fr)]">
    @php
        $currentUser = auth()->user();
        $agencyProfile = AppSetting::agency();
        $taskCount = TaskInbox::countFor($currentUser);
        $letters = $this->letters();
        $selectedLetter = $this->selectedLetter();
        $steps = $this->trackingSteps($selectedLetter);
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
            <a href="{{ route('tracking') }}" class="inline-flex items-center gap-2 rounded-lg bg-white/10 px-3 py-2 text-sm font-semibold text-white"><x-icon name="route" class="h-4 w-4" />Pelacakan Surat</a>
            <a href="{{ route('my-tasks') }}" class="flex items-center justify-between rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5">
                <span class="inline-flex items-center gap-2"><x-icon name="task" class="h-4 w-4" />Tugas Saya</span>
                @if ($taskCount > 0)
                    <span class="rounded-full bg-rose-500 px-2 py-0.5 text-xs font-bold text-white">{{ $taskCount }}</span>
                @endif
            </a>
            @if ($currentUser?->isAdmin())
                <a href="{{ route('number-monitor') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5"><x-icon name="list-numbered" class="h-4 w-4" />Monitoring Nomor</a>
                <a href="{{ route('sk-numbering') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5"><x-icon name="gavel" class="h-4 w-4" />Penomoran SK</a>
            @endif
            @if ($currentUser?->isAdmin() || $currentUser?->isLeader() || $currentUser?->isPersonalSecretary())
                <a href="{{ route('leadership') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5"><x-icon name="send" class="h-4 w-4" />Halaman Pimpinan</a>
            @endif
            @if ($currentUser?->isAdmin() || $currentUser?->isDepartmentHead())
                <a href="{{ route('department-head') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5"><x-icon name="users" class="h-4 w-4" />Halaman Kepala Bagian</a>
            @endif
            @if ($currentUser?->isAdmin())
                <a href="{{ route('settings') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5"><x-icon name="settings" class="h-4 w-4" />Setting</a>
            @endif
        </nav>

        <div class="mt-10 hidden border-t border-white/10 pt-5 lg:block">
            <div class="text-sm font-semibold">{{ $currentUser->name }}</div>
            <div class="text-xs text-slate-400">{{ $currentUser->role }}</div>
        </div>
    </aside>

    <main class="min-w-0 px-4 py-6 sm:px-6 lg:px-8">
        <header class="sticky top-0 z-10 -mx-4 flex flex-col gap-4 border-b border-slate-200 bg-slate-100/95 px-4 py-4 backdrop-blur sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <p class="text-xs font-bold uppercase text-teal-700">Pelacakan</p>
                <h1 class="mt-1 text-3xl font-bold tracking-normal text-slate-950">Tracking Surat</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-600">Cari dan pantau posisi surat masuk atau keluar berdasarkan nomor, agenda, perihal, dan pihak luar.</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-800 shadow-sm hover:border-rose-500">
                    <x-icon name="logout" class="mr-2 h-4 w-4" />
                    Logout
                </button>
            </form>
        </header>

        <section class="mt-6 grid gap-3 rounded-lg border border-slate-200 bg-white p-5 shadow-sm lg:grid-cols-[minmax(0,1fr)_180px_220px]">
            <label class="grid gap-1 text-sm font-bold text-slate-600">
                Cari Surat
                <input wire:model.live.debounce.300ms="search" type="search" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950" placeholder="Nomor surat, agenda, perihal, pengirim...">
            </label>
            <label class="grid gap-1 text-sm font-bold text-slate-600">
                Jenis
                <select wire:model.live="typeFilter" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                    <option>Semua</option>
                    <option>Masuk</option>
                    <option>Keluar</option>
                </select>
            </label>
            <label class="grid gap-1 text-sm font-bold text-slate-600">
                Status
                <select wire:model.live="statusFilter" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                    @foreach ($this->statuses() as $status)
                        <option value="{{ $status }}">{{ $status }}</option>
                    @endforeach
                </select>
            </label>
        </section>

        <section class="mt-5">
            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 p-5">
                    <h2 class="text-lg font-bold">Hasil Pencarian</h2>
                    <p class="text-sm text-slate-500">{{ $letters->total() }} surat ditemukan.</p>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($letters as $letter)
                        <button type="button"
                                wire:click="selectLetter({{ $letter->id }})"
                                class="grid w-full gap-2 px-5 py-4 text-left transition hover:bg-slate-50">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <div class="font-bold">{{ $letter->number }}</div>
                                    <div class="mt-1 text-sm text-slate-600">{{ $letter->subject }}</div>
                                </div>
                                <span class="inline-flex w-fit items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $this->statusClass($letter->status) }}">
                                    <x-icon :name="$letter->status === 'Baru' ? 'mail' : ($letter->status === 'Disposisi Pimpinan' || $letter->status === 'Disposisi' ? 'send' : ($letter->status === 'Diproses' ? 'sync' : ($letter->status === 'Selesai' ? 'check' : 'clock')))" class="h-3.5 w-3.5" />
                                    {{ $letter->status }}
                                </span>
                            </div>
                            <div class="flex flex-wrap gap-2 text-xs font-semibold text-slate-500">
                                <span class="inline-flex items-center gap-1"><x-icon :name="$letter->type === 'Keluar' ? 'outbox' : 'inbox'" class="h-3.5 w-3.5" />{{ $letter->type }}</span>
                                <span>{{ $letter->unit_code }}</span>
                                <span class="inline-flex items-center gap-1"><x-icon name="user" class="h-3.5 w-3.5" />{{ $letter->external_party }}</span>
                                <span class="inline-flex items-center gap-1"><x-icon name="calendar" class="h-3.5 w-3.5" />{{ $letter->letter_date?->translatedFormat('d M Y') }}</span>
                            </div>
                        </button>
                    @empty
                        <p class="px-5 py-12 text-center text-sm text-slate-500">Belum ada surat yang cocok dengan pencarian.</p>
                    @endforelse
                </div>

                @if ($letters->hasPages())
                    <div class="border-t border-slate-200 p-5">{{ $letters->links() }}</div>
                @endif
            </div>
        </section>

        @if ($showDetailModal)
            <div wire:click.self="$set('showDetailModal', false)" class="fixed inset-0 z-20 grid place-items-center bg-slate-950/50 p-4">
                @if ($selectedLetter)
                    @php($activityHistory = $this->activityHistory($selectedLetter))
                    <div class="max-h-[90vh] w-full max-w-5xl overflow-y-auto rounded-lg bg-white shadow-xl">
                        <div class="flex flex-col gap-3 border-b border-slate-200 p-6 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-xs font-bold uppercase text-teal-700">{{ $selectedLetter->type }} | {{ $selectedLetter->unit_code }}</p>
                                <h2 class="mt-1 text-xl font-bold">{{ $selectedLetter->number }}</h2>
                                <p class="mt-1 text-sm text-slate-600">{{ $selectedLetter->subject }}</p>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="inline-flex w-fit items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $this->statusClass($selectedLetter->status) }}">
                                    <x-icon :name="$selectedLetter->status === 'Baru' ? 'mail' : ($selectedLetter->status === 'Disposisi Pimpinan' || $selectedLetter->status === 'Disposisi' ? 'send' : ($selectedLetter->status === 'Diproses' ? 'sync' : ($selectedLetter->status === 'Selesai' ? 'check' : 'clock')))" class="h-3.5 w-3.5" />
                                    {{ $selectedLetter->status }}
                                </span>
                                <button type="button" wire:click="$set('showDetailModal', false)" class="grid h-10 w-10 place-items-center rounded-lg bg-slate-100 text-xl font-bold">&times;</button>
                            </div>
                        </div>

                        <div class="space-y-5 p-6">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <div class="text-xs font-bold uppercase text-slate-500">Nomor Agenda</div>
                                    <div class="font-semibold">{{ $selectedLetter->agenda_number ?: '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-bold uppercase text-slate-500">Kode Arsip</div>
                                    <div class="font-semibold">{{ $selectedLetter->classification_code ? $selectedLetter->classification_code.' - '.$selectedLetter->classification?->name : '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-bold uppercase text-slate-500">Tanggal Surat</div>
                                    <div class="font-semibold">{{ $selectedLetter->letter_date?->translatedFormat('d F Y') ?: '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-bold uppercase text-slate-500">Tanggal Diterima</div>
                                    <div class="font-semibold">{{ $selectedLetter->received_date?->translatedFormat('d F Y') ?: '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-bold uppercase text-slate-500">Pihak Luar</div>
                                    <div class="font-semibold">{{ $selectedLetter->external_party }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-bold uppercase text-slate-500">Prioritas</div>
                                    <div class="font-semibold">{{ $selectedLetter->nature ?: '-' }} | {{ $selectedLetter->urgency ?: '-' }}</div>
                                </div>
                            </div>

                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <h3 class="inline-flex items-center gap-2 font-bold"><x-icon name="route" class="h-4 w-4 text-teal-700" />Posisi Surat</h3>
                                <div class="mt-4 grid gap-3 sm:grid-cols-5">
                                    @foreach ($steps as $step)
                                        <div class="rounded-lg border {{ $step['done'] ? 'border-teal-200 bg-teal-50' : 'border-slate-200 bg-white' }} p-3">
                                            <div class="inline-flex items-center gap-1 text-sm font-bold {{ $step['active'] ? 'text-teal-800' : ($step['done'] ? 'text-teal-700' : 'text-slate-500') }}">
                                                <x-icon :name="$step['done'] ? 'check' : 'clock'" class="h-4 w-4" />
                                                {{ $step['label'] }}
                                            </div>
                                            <div class="mt-1 text-xs text-slate-500">{{ $step['active'] ? 'Posisi saat ini' : ($step['done'] ? 'Sudah lewat' : 'Menunggu') }}</div>
                                            @if ($step['actor'] || $step['time'])
                                                <div class="mt-2 border-t border-black/5 pt-2 text-xs text-slate-600">
                                                    <div class="inline-flex items-center gap-1 font-semibold"><x-icon name="user" class="h-3.5 w-3.5" />Oleh {{ $step['actor'] ?: 'Sistem' }}</div>
                                                    <div class="mt-1 inline-flex items-center gap-1"><x-icon name="clock" class="h-3.5 w-3.5" />{{ $step['time'] ? $step['time']->translatedFormat('d M Y H:i') : 'Waktu belum tercatat' }}</div>
                                                    @if ($step['note'])
                                                        <div class="mt-1 font-normal text-slate-500">{{ $step['note'] }}</div>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="rounded-lg border border-slate-200 p-4">
                                    <h3 class="inline-flex items-center gap-2 font-bold"><x-icon name="file" class="h-4 w-4 text-teal-700" />Dokumen Surat</h3>
                                    @if ($selectedLetter->file_path)
                                        <div class="mt-2 text-sm font-semibold text-slate-600">{{ basename($selectedLetter->file_path) }}</div>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <a href="{{ route('letters.document.review', $selectedLetter) }}" target="_blank" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-bold hover:border-teal-600"><x-icon name="eye" class="h-4 w-4" />Buka</a>
                                            <a href="{{ route('letters.document.download', $selectedLetter) }}" class="inline-flex items-center gap-1 rounded-lg bg-teal-700 px-3 py-1.5 text-sm font-bold text-white hover:bg-teal-800"><x-icon name="download" class="h-4 w-4" />Download</a>
                                        </div>
                                    @else
                                        <p class="mt-2 text-sm text-slate-500">Belum ada dokumen utama.</p>
                                    @endif
                                </div>
                                <div class="rounded-lg border border-slate-200 p-4">
                                    <h3 class="inline-flex items-center gap-2 font-bold"><x-icon name="paperclip" class="h-4 w-4 text-teal-700" />Lampiran</h3>
                                    @forelse ($selectedLetter->attachments as $attachment)
                                        <div class="mt-2 flex flex-col gap-2 rounded-lg bg-slate-50 p-3">
                                            <div class="text-sm font-semibold">{{ $attachment->original_name }}</div>
                                            <div class="flex flex-wrap gap-2">
                                                <a href="{{ route('letter-attachments.review', $attachment) }}" target="_blank" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold hover:border-teal-600"><x-icon name="eye" class="h-3.5 w-3.5" />Buka</a>
                                                <a href="{{ route('letter-attachments.download', $attachment) }}" class="inline-flex items-center gap-1 rounded-lg bg-teal-700 px-3 py-1.5 text-xs font-bold text-white hover:bg-teal-800"><x-icon name="download" class="h-3.5 w-3.5" />Download</a>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="mt-2 text-sm text-slate-500">Belum ada lampiran.</p>
                                    @endforelse
                                </div>
                            </div>

                            <div class="rounded-lg border border-slate-200 p-4">
                                <h3 class="inline-flex items-center gap-2 font-bold"><x-icon name="send" class="h-4 w-4 text-teal-700" />Timeline Disposisi</h3>
                                <div class="mt-3 space-y-4">
                                    @forelse ($selectedLetter->dispositions->filter(fn ($item) => $item->parent_id === null) as $disposition)
                                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                                <div>
                                                    <div class="font-semibold">{{ $disposition->sender_name }} ke {{ $disposition->recipient_name }}</div>
                                                    <div class="text-xs text-slate-500">{{ $disposition->created_at->translatedFormat('d M Y H:i') }}</div>
                                                </div>
                                                <span class="inline-flex w-fit items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $this->statusClass($disposition->status) }}"><x-icon name="send" class="h-3.5 w-3.5" />{{ $disposition->status }}</span>
                                            </div>
                                            <p class="mt-2 text-sm text-slate-600">{{ $disposition->instruction }}</p>
                                            @if ($disposition->scan_path)
                                                <div class="mt-3 flex flex-wrap gap-2">
                                                    <a href="{{ route('dispositions.scan.review', $disposition) }}" target="_blank" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-bold hover:border-teal-600"><x-icon name="scanner" class="h-4 w-4" />Buka File Disposisi</a>
                                                    <a href="{{ route('dispositions.scan.download', $disposition) }}" class="inline-flex items-center gap-1 rounded-lg bg-teal-700 px-3 py-1.5 text-sm font-bold text-white hover:bg-teal-800"><x-icon name="download" class="h-4 w-4" />Download</a>
                                                </div>
                                            @endif

                                            @if ($disposition->children->isNotEmpty())
                                                <div class="mt-4 space-y-3 border-l-4 border-teal-700 pl-4">
                                                    @foreach ($disposition->children as $child)
                                                        <div class="rounded-lg border border-slate-200 bg-white p-3">
                                                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                                                <div>
                                                                    <div class="font-semibold">{{ $child->sender_name }} ke {{ $child->recipient_name }}</div>
                                                                    <div class="text-xs text-slate-500">{{ $child->created_at->translatedFormat('d M Y H:i') }}</div>
                                                                </div>
                                                                <span class="inline-flex w-fit items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $this->statusClass($child->status) }}"><x-icon name="send" class="h-3.5 w-3.5" />{{ $child->status }}</span>
                                                            </div>
                                                            <p class="mt-2 text-sm text-slate-600">{{ $child->instruction }}</p>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @empty
                                        <p class="text-sm text-slate-500">Belum ada riwayat disposisi untuk surat ini.</p>
                                    @endforelse
                                </div>
                            </div>

                            @if ($selectedLetter->approvals->isNotEmpty())
                                <div class="rounded-lg border border-slate-200 p-4">
                                    <h3 class="inline-flex items-center gap-2 font-bold"><x-icon name="check" class="h-4 w-4 text-teal-700" />Alur Paraf dan Tanda Tangan</h3>
                                    <div class="mt-3 space-y-3">
                                        @foreach ($selectedLetter->approvals as $approval)
                                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                                    <div>
                                                        <div class="font-semibold">{{ $approval->step }}</div>
                                                        <div class="text-xs text-slate-500">Target: {{ $approval->target_role }}</div>
                                                        @if ($approval->actor_name)
                                                            <div class="mt-1 text-xs text-slate-500">Oleh {{ $approval->actor_name }} | {{ $approval->acted_at?->translatedFormat('d M Y H:i') }}</div>
                                                        @endif
                                                    </div>
                                                    <span class="inline-flex w-fit items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $this->statusClass($approval->status) }}"><x-icon :name="$approval->status === 'Ditolak' ? 'cancel' : 'check'" class="h-3.5 w-3.5" />{{ $approval->status }}</span>
                                                </div>
                                                @if ($approval->note)
                                                    <p class="mt-2 rounded-lg bg-white px-3 py-2 text-sm text-rose-700 ring-1 ring-rose-100">{{ $approval->note }}</p>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            <div class="rounded-lg border border-slate-200 p-4">
                                <h3 class="inline-flex items-center gap-2 font-bold"><x-icon name="history" class="h-4 w-4 text-teal-700" />Riwayat Aksi</h3>
                                <div class="mt-3 space-y-3">
                                    @forelse ($activityHistory as $log)
                                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                                <div>
                                                    <div class="font-semibold">{{ $log->description }}</div>
                                                    <div class="mt-1 inline-flex items-center gap-1 text-xs text-slate-500"><x-icon name="user" class="h-3.5 w-3.5" />Oleh {{ $log->user?->name ?: 'Sistem' }}</div>
                                                </div>
                                                <div class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500"><x-icon name="clock" class="h-3.5 w-3.5" />{{ $log->created_at->translatedFormat('d M Y H:i') }}</div>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-sm text-slate-500">Belum ada riwayat aksi yang tercatat untuk surat ini.</p>
                                    @endforelse
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <button type="button" wire:click="$set('showDetailModal', false)" class="min-h-11 rounded-lg border border-slate-200 px-4 text-sm font-bold">Tutup</button>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="w-full max-w-lg rounded-lg bg-white p-6 text-center shadow-xl">
                        <p class="text-sm text-slate-500">Surat tidak ditemukan.</p>
                        <button type="button" wire:click="$set('showDetailModal', false)" class="mt-4 min-h-11 rounded-lg border border-slate-200 px-4 text-sm font-bold">Tutup</button>
                    </div>
                @endif
            </div>
        @endif
    </main>
</div>
