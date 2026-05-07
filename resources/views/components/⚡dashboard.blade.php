<?php

use App\Models\Letter;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $search = '';
    public string $typeFilter = 'Semua';
    public string $unitFilter = 'Semua';
    public ?int $selectedLetterId = null;
    public bool $showLetterForm = false;
    public bool $showDispositionForm = false;

    public string $type = 'Masuk';
    public string $unitCode = 'SET-MRP';
    public string $number = '';
    public string $subject = '';
    public string $externalParty = '';
    public string $letterDate = '';
    public $document = null;

    public string $recipientName = 'Staf Administrasi';
    public string $instruction = '';

    public function mount(): void
    {
        $this->letterDate = now()->toDateString();
        $this->typeFilter = in_array(request('filter'), ['Semua', 'Masuk', 'Keluar'], true)
            ? request('filter')
            : 'Semua';
        $this->unitFilter = in_array(request('unit'), ['Semua', 'SET-MRP', 'MRP'], true)
            ? request('unit')
            : 'Semua';
        $this->selectedLetterId = Letter::latest('letter_date')->value('id');
    }

    public function letters()
    {
        return Letter::query()
            ->with('dispositions')
            ->when($this->typeFilter !== 'Semua', fn ($query) => $query->where('type', $this->typeFilter))
            ->when($this->unitFilter !== 'Semua', fn ($query) => $query->where('unit_code', $this->unitFilter))
            ->when($this->search !== '', function ($query) {
                $search = '%'.$this->search.'%';

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('number', 'like', $search)
                        ->orWhere('unit_code', 'like', $search)
                        ->orWhere('subject', 'like', $search)
                        ->orWhere('external_party', 'like', $search)
                        ->orWhere('status', 'like', $search);
                });
            })
            ->latest('letter_date')
            ->latest()
            ->get();
    }

    public function selectedLetter(): ?Letter
    {
        return Letter::with('dispositions')->find($this->selectedLetterId);
    }

    public function setFilter(string $filter): void
    {
        $this->typeFilter = $filter;
    }

    public function setUnitFilter(string $filter): void
    {
        $this->unitFilter = $filter;
    }

    public function setCombinedFilter(string $typeFilter, string $unitFilter): void
    {
        $this->typeFilter = $typeFilter;
        $this->unitFilter = $unitFilter;
    }

    public function selectLetter(int $letterId): void
    {
        $this->selectedLetterId = $letterId;
    }

    public function openLetterForm(string $type = 'Masuk', ?string $unitCode = null): void
    {
        abort_unless($this->canManageLetters(), 403);

        $this->resetValidation();
        $this->type = $type;
        $this->unitCode = $unitCode && in_array($unitCode, ['SET-MRP', 'MRP'], true) ? $unitCode : 'SET-MRP';
        $this->number = $type === 'Keluar' ? $this->nextLetterNumber($this->unitCode) : '';
        $this->subject = '';
        $this->externalParty = '';
        $this->letterDate = now()->toDateString();
        $this->document = null;
        $this->showLetterForm = true;
    }

    public function updatedType(string $value): void
    {
        if ($value === 'Keluar' && $this->number === '') {
            $this->number = $this->nextLetterNumber($this->unitCode);
        }
    }

    public function updatedUnitCode(string $value): void
    {
        if ($this->type === 'Keluar') {
            $this->number = $this->nextLetterNumber($value);
        }
    }

    public function saveLetter(): void
    {
        abort_unless($this->canManageLetters(), 403);

        $validated = $this->validate([
            'type' => ['required', 'in:Masuk,Keluar'],
            'unitCode' => ['required', 'in:SET-MRP,MRP'],
            'number' => ['nullable', 'string', 'max:100'],
            'subject' => ['required', 'string', 'max:255'],
            'externalParty' => ['required', 'string', 'max:255'],
            'letterDate' => ['required', 'date'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $number = $validated['number'] ?: ($validated['type'] === 'Keluar' ? $this->nextLetterNumber($validated['unitCode']) : 'SM/'.$validated['unitCode'].'/'.now()->format('YmdHis'));
        $filePath = $this->document ? $this->document->store('dokumen-surat', 'public') : null;

        $letter = Letter::create([
            'type' => $validated['type'],
            'unit_code' => $validated['unitCode'],
            'number' => $number,
            'subject' => $validated['subject'],
            'external_party' => $validated['externalParty'],
            'letter_date' => $validated['letterDate'],
            'file_path' => $filePath,
            'status' => $validated['type'] === 'Masuk' ? 'Baru' : 'Selesai',
        ]);

        $this->selectedLetterId = $letter->id;
        $this->showLetterForm = false;
        $this->dispatch('notify', message: 'Surat baru berhasil dicatat.');
    }

    public function openDispositionForm(int $letterId): void
    {
        abort_unless($this->canDispose(), 403);

        $this->resetValidation();
        $this->selectedLetterId = $letterId;
        $this->recipientName = 'Staf Administrasi';
        $this->instruction = '';
        $this->showDispositionForm = true;
    }

    public function saveDisposition(): void
    {
        abort_unless($this->canDispose(), 403);

        $validated = $this->validate([
            'recipientName' => ['required', 'string', 'max:255'],
            'instruction' => ['required', 'string', 'max:1000'],
        ]);

        $letter = $this->selectedLetter();
        if (! $letter) {
            return;
        }

        $letter->dispositions()->create([
            'sender_name' => 'Pimpinan',
            'recipient_name' => $validated['recipientName'],
            'instruction' => $validated['instruction'],
            'status' => 'Belum Dibaca',
        ]);

        $letter->update(['status' => 'Disposisi']);
        $this->showDispositionForm = false;
        $this->dispatch('notify', message: 'Disposisi terkirim ke staf terkait.');
    }

    public function updateStatus(int $letterId, string $status): void
    {
        abort_unless($this->canUpdateStatus(), 403);

        Letter::whereKey($letterId)->update(['status' => $status]);
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

    public function nextLetterNumber(?string $unitCode = null): string
    {
        $unitCode = in_array($unitCode, ['SET-MRP', 'MRP'], true) ? $unitCode : 'SET-MRP';
        $next = Letter::where('type', 'Keluar')->where('unit_code', $unitCode)->count() + 19;

        return '800/'.str_pad((string) $next, 3, '0', STR_PAD_LEFT).'/'.$unitCode.'/'.now()->format('m').'/'.now()->format('Y');
    }

    public function statusClass(string $status): string
    {
        return match ($status) {
            'Baru' => 'bg-rose-100 text-rose-700 ring-rose-200',
            'Disposisi' => 'bg-amber-100 text-amber-700 ring-amber-200',
            'Diproses' => 'bg-indigo-100 text-indigo-700 ring-indigo-200',
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
        return $unitCode === 'SET-MRP'
            ? 'bg-teal-100 text-teal-700 ring-teal-200'
            : 'bg-orange-100 text-orange-700 ring-orange-200';
    }
};
?>

<div x-data="{ toast: '', showToast: false }"
     x-on:notify.window="toast = $event.detail.message; showToast = true; setTimeout(() => showToast = false, 2600)"
     class="min-h-screen lg:grid lg:grid-cols-[280px_minmax(0,1fr)]">
    @php
        $letters = $this->letters();
        $selectedLetter = $this->selectedLetter();
        $currentUser = auth()->user();
        $setIncomingCount = App\Models\Letter::where('unit_code', 'SET-MRP')->where('type', 'Masuk')->count();
        $setOutgoingCount = App\Models\Letter::where('unit_code', 'SET-MRP')->where('type', 'Keluar')->count();
        $mrpIncomingCount = App\Models\Letter::where('unit_code', 'MRP')->where('type', 'Masuk')->count();
        $mrpOutgoingCount = App\Models\Letter::where('unit_code', 'MRP')->where('type', 'Keluar')->count();
    @endphp

    <aside class="bg-slate-900 px-5 py-5 text-slate-100 lg:min-h-screen">
        <div class="flex items-center gap-3">
            <div class="grid h-11 w-11 place-items-center rounded-lg bg-teal-100 font-bold text-teal-800">ES</div>
            <div>
                <div class="font-semibold">E-Surat</div>
                <div class="text-sm text-slate-400">Sekretariat MRP Papua Tengah</div>
            </div>
        </div>

        <nav x-data="{ incomingOpen: true, outgoingOpen: true }" class="mt-8 flex gap-2 overflow-x-auto lg:grid lg:overflow-visible">
            <button type="button"
                    wire:click="setCombinedFilter('Semua', 'Semua')"
                    class="rounded-lg px-3 py-2 text-left text-sm font-semibold transition {{ $typeFilter === 'Semua' && $unitFilter === 'Semua' ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5' }}">
                Dasbor
            </button>

            <div class="min-w-40 lg:min-w-0">
                <button type="button"
                        x-on:click="incomingOpen = ! incomingOpen"
                        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm font-semibold text-slate-300 transition hover:bg-white/5">
                    <span>Surat Masuk</span>
                    <span x-text="incomingOpen ? '−' : '+'" class="text-slate-400"></span>
                </button>
                <div x-show="incomingOpen" class="mt-1 grid gap-1 pl-3">
                    @foreach (['SET-MRP', 'MRP'] as $unit)
                        <button type="button"
                                wire:click="setCombinedFilter('Masuk', '{{ $unit }}')"
                                class="rounded-lg px-3 py-2 text-left text-sm font-semibold transition {{ $typeFilter === 'Masuk' && $unitFilter === $unit ? 'bg-white/10 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-slate-200' }}">
                            {{ $unit }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="min-w-40 lg:min-w-0">
                <button type="button"
                        x-on:click="outgoingOpen = ! outgoingOpen"
                        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm font-semibold text-slate-300 transition hover:bg-white/5">
                    <span>Surat Keluar</span>
                    <span x-text="outgoingOpen ? '−' : '+'" class="text-slate-400"></span>
                </button>
                <div x-show="outgoingOpen" class="mt-1 grid gap-1 pl-3">
                    @foreach (['SET-MRP', 'MRP'] as $unit)
                        <button type="button"
                                wire:click="setCombinedFilter('Keluar', '{{ $unit }}')"
                                class="rounded-lg px-3 py-2 text-left text-sm font-semibold transition {{ $typeFilter === 'Keluar' && $unitFilter === $unit ? 'bg-white/10 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-slate-200' }}">
                            {{ $unit }}
                        </button>
                    @endforeach
                </div>
            </div>

            <button type="button"
                    wire:click="openDispositionForm({{ $selectedLetterId ?? 0 }})"
                    @disabled(! $selectedLetterId || ! $this->canDispose())
                    class="rounded-lg px-3 py-2 text-left text-sm font-semibold text-slate-300 transition hover:bg-white/5 disabled:cursor-not-allowed disabled:opacity-50">
                Disposisi
            </button>
            @if ($this->canManageSettings())
                <a href="{{ route('settings') }}" class="rounded-lg px-3 py-2 text-left text-sm font-semibold text-slate-300 transition hover:bg-white/5">
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
        <header class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <p class="text-xs font-bold uppercase text-teal-700">Rabu, 6 Mei 2026</p>
                <h1 class="mt-1 text-3xl font-bold tracking-normal text-slate-950">Dasbor Persuratan MRP</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-600">Pengelolaan surat Sekretariat MRP dan MRP Provinsi Papua Tengah.</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row xl:justify-end">
                <label class="flex min-h-11 w-full items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 shadow-sm sm:w-96">
                    <span class="text-slate-400">⌕</span>
                    <input wire:model.live.debounce.250ms="search"
                           type="search"
                           class="w-full border-0 bg-transparent text-sm outline-none"
                           placeholder="Cari nomor, perihal, pihak luar...">
                </label>
                @if ($this->canManageLetters())
                    <button type="button"
                            wire:click="openLetterForm('Keluar', '{{ $unitFilter === 'Semua' ? 'SET-MRP' : $unitFilter }}')"
                            class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-800 shadow-sm hover:border-teal-600">
                        # Generate
                    </button>
                    <button type="button"
                            wire:click="openLetterForm"
                            class="inline-flex min-h-11 items-center justify-center rounded-lg bg-teal-700 px-4 text-sm font-bold text-white shadow-sm hover:bg-teal-800">
                        + Catat Surat
                    </button>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-800 shadow-sm hover:border-rose-500">
                        Logout
                    </button>
                </form>
            </div>
        </header>

        <section class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-slate-500">SET-MRP Masuk</div>
                <div class="mt-2 text-3xl font-bold">{{ $setIncomingCount }}</div>
                <div class="text-sm text-slate-500">surat sekretariat</div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-slate-500">SET-MRP Keluar</div>
                <div class="mt-2 text-3xl font-bold">{{ $setOutgoingCount }}</div>
                <div class="text-sm text-slate-500">nomor sekretariat</div>
            </div>
            <div class="rounded-lg border border-orange-200 bg-orange-50 p-5 shadow-sm">
                <div class="text-sm text-orange-700">MRP Masuk</div>
                <div class="mt-2 text-3xl font-bold">{{ $mrpIncomingCount }}</div>
                <div class="text-sm text-orange-700">surat lembaga MRP</div>
            </div>
            <div class="rounded-lg border border-orange-200 bg-orange-50 p-5 shadow-sm">
                <div class="text-sm text-orange-700">MRP Keluar</div>
                <div class="mt-2 text-3xl font-bold">{{ $mrpOutgoingCount }}</div>
                <div class="text-sm text-orange-700">nomor lembaga MRP</div>
            </div>
        </section>

        <section class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1.6fr)_minmax(340px,0.8fr)]">
            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-200 p-5 xl:flex-row xl:items-center xl:justify-between">
                    <div>
                        <h2 class="text-lg font-bold">Daftar Surat</h2>
                        <p class="text-sm text-slate-500">{{ $letters->count() }} surat ditampilkan{{ $search ? ' dari pencarian langsung' : '' }}</p>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <div class="inline-flex rounded-lg bg-slate-100 p-1">
                            @foreach (['Semua', 'Masuk', 'Keluar'] as $filter)
                                <button type="button"
                                        wire:click="setFilter('{{ $filter }}')"
                                        class="rounded-md px-3 py-1.5 text-sm font-bold {{ $typeFilter === $filter ? 'bg-white text-teal-700 shadow-sm' : 'text-slate-600' }}">
                                    {{ $filter }}
                                </button>
                            @endforeach
                        </div>
                        <div class="inline-flex rounded-lg bg-slate-100 p-1">
                            @foreach (['Semua', 'SET-MRP', 'MRP'] as $filter)
                                <button type="button"
                                        wire:click="setUnitFilter('{{ $filter }}')"
                                        class="rounded-md px-3 py-1.5 text-sm font-bold {{ $unitFilter === $filter ? 'bg-white text-teal-700 shadow-sm' : 'text-slate-600' }}">
                                    {{ $filter }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-[920px] w-full border-collapse text-left text-sm">
                        <thead class="text-xs uppercase text-slate-500">
                            <tr>
                                <th class="border-b border-slate-200 px-5 py-3">Nomor</th>
                                <th class="border-b border-slate-200 px-5 py-3">Jenis</th>
                                <th class="border-b border-slate-200 px-5 py-3">Unit</th>
                                <th class="border-b border-slate-200 px-5 py-3">Perihal</th>
                                <th class="border-b border-slate-200 px-5 py-3">Pihak Luar</th>
                                <th class="border-b border-slate-200 px-5 py-3">Status</th>
                                <th class="border-b border-slate-200 px-5 py-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($letters as $letter)
                                <tr wire:key="letter-{{ $letter->id }}" class="{{ $selectedLetterId === $letter->id ? 'bg-teal-50' : 'hover:bg-slate-50' }}">
                                    <td class="border-b border-slate-100 px-5 py-4">
                                        <button type="button" wire:click="selectLetter({{ $letter->id }})" class="text-left font-bold text-slate-950">
                                            {{ $letter->number }}
                                        </button>
                                        <div class="text-xs text-slate-500">{{ $letter->letter_date->translatedFormat('d F Y') }}</div>
                                    </td>
                                    <td class="border-b border-slate-100 px-5 py-4">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $this->typeClass($letter->type) }}">{{ $letter->type }}</span>
                                    </td>
                                    <td class="border-b border-slate-100 px-5 py-4">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $this->unitClass($letter->unit_code) }}">{{ $letter->unit_code }}</span>
                                    </td>
                                    <td class="border-b border-slate-100 px-5 py-4">{{ $letter->subject }}</td>
                                    <td class="border-b border-slate-100 px-5 py-4">{{ $letter->external_party }}</td>
                                    <td class="border-b border-slate-100 px-5 py-4">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $this->statusClass($letter->status) }}">{{ $letter->status }}</span>
                                    </td>
                                    <td class="border-b border-slate-100 px-5 py-4">
                                        <div class="flex gap-2">
                                            <button type="button" wire:click="selectLetter({{ $letter->id }})" class="rounded-lg border border-slate-200 px-3 py-1.5 font-bold hover:border-teal-600">Detail</button>
                                            @if ($this->canDispose())
                                                <button type="button" wire:click="openDispositionForm({{ $letter->id }})" class="rounded-lg border border-slate-200 px-3 py-1.5 font-bold hover:border-teal-600">Disposisi</button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-16 text-center text-slate-500">
                                        Tidak ada surat yang cocok dengan filter saat ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <aside class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 p-5">
                    <h2 class="text-lg font-bold">Detail Surat</h2>
                    <p class="text-sm text-slate-500">{{ $selectedLetter ? $selectedLetter->unit_code.' | '.$selectedLetter->type.' | '.$selectedLetter->number : 'Pilih surat dari daftar' }}</p>
                </div>

                @if ($selectedLetter)
                    <div class="space-y-5 p-5">
                        <span class="rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $this->statusClass($selectedLetter->status) }}">{{ $selectedLetter->status }}</span>

                        <div class="space-y-3">
                            <div>
                                <div class="text-xs font-bold uppercase text-slate-500">Unit</div>
                                <span class="mt-1 inline-flex rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $this->unitClass($selectedLetter->unit_code) }}">{{ $selectedLetter->unit_code }}</span>
                            </div>
                            <div>
                                <div class="text-xs font-bold uppercase text-slate-500">Perihal</div>
                                <div class="font-semibold">{{ $selectedLetter->subject }}</div>
                            </div>
                            <div>
                                <div class="text-xs font-bold uppercase text-slate-500">Pihak Luar</div>
                                <div class="font-semibold">{{ $selectedLetter->external_party }}</div>
                            </div>
                            <div>
                                <div class="text-xs font-bold uppercase text-slate-500">Tanggal Surat</div>
                                <div class="font-semibold">{{ $selectedLetter->letter_date->translatedFormat('d F Y') }}</div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4">
                            <div class="font-semibold">{{ $selectedLetter->file_path ? basename($selectedLetter->file_path) : 'Belum ada dokumen terunggah' }}</div>
                            <div class="text-sm text-slate-500">Dokumen digital tersimpan di disk lokal aplikasi</div>
                        </div>

                        <div class="border-t border-slate-200 pt-5">
                            <h3 class="font-bold">Riwayat Disposisi</h3>
                            <div class="mt-3 space-y-3">
                                @forelse ($selectedLetter->dispositions as $disposition)
                                    <div class="border-l-4 border-teal-700 pl-3">
                                        <div class="font-semibold">{{ $disposition->sender_name }} ke {{ $disposition->recipient_name }}</div>
                                        <p class="mt-1 text-sm text-slate-600">{{ $disposition->instruction }}</p>
                                        <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $this->statusClass($disposition->status) }}">{{ $disposition->status }}</span>
                                    </div>
                                @empty
                                    <p class="text-sm text-slate-500">Belum ada riwayat disposisi.</p>
                                @endforelse
                            </div>
                        </div>

                        @if ($this->canUpdateStatus())
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <select wire:change="updateStatus({{ $selectedLetter->id }}, $event.target.value)" class="min-h-11 rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold">
                                    @foreach (['Baru', 'Disposisi', 'Diproses', 'Selesai'] as $status)
                                        <option value="{{ $status }}" @selected($selectedLetter->status === $status)>{{ $status }}</option>
                                    @endforeach
                                </select>
                                <button type="button" wire:click="markDone({{ $selectedLetter->id }})" class="min-h-11 rounded-lg border border-slate-200 px-4 text-sm font-bold hover:border-teal-600">Tandai Selesai</button>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="grid min-h-80 place-items-center p-8 text-center text-slate-500">
                        Detail, dokumen scan, dan riwayat disposisi akan tampil di sini.
                    </div>
                @endif
            </aside>
        </section>

        @if ($showLetterForm)
            <div class="fixed inset-0 z-20 grid place-items-center bg-slate-950/50 p-4">
                <form wire:submit="saveLetter" class="w-full max-w-2xl rounded-lg bg-white p-6 shadow-xl">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase text-teal-700">Pencatatan Dokumen</p>
                            <h2 class="mt-1 text-xl font-bold">Catat Surat Baru</h2>
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
                                <option value="SET-MRP">SET-MRP</option>
                                <option value="MRP">MRP</option>
                            </select>
                            @error('unitCode') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
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
                            Pihak Luar
                            <input wire:model="externalParty" type="text" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950" placeholder="Pengirim atau penerima">
                            @error('externalParty') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600 sm:col-span-2">
                            Perihal
                            <input wire:model="subject" type="text" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950" placeholder="Ringkasan perihal surat">
                            @error('subject') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4 text-sm font-bold text-slate-600 sm:col-span-2">
                            Dokumen Scan
                            <input wire:model="document" type="file" accept=".pdf,.jpg,.jpeg,.png" class="rounded-lg border border-slate-200 bg-white p-2 text-slate-950">
                            <span class="text-xs font-normal text-slate-500">PDF, JPG, JPEG, atau PNG. Maksimal 5 MB.</span>
                            @error('document') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" wire:click="$set('showLetterForm', false)" class="min-h-11 rounded-lg border border-slate-200 px-4 text-sm font-bold">Batal</button>
                        <button type="submit" class="min-h-11 rounded-lg bg-teal-700 px-4 text-sm font-bold text-white hover:bg-teal-800">Simpan Surat</button>
                    </div>
                </form>
            </div>
        @endif

        @if ($showDispositionForm)
            <div class="fixed inset-0 z-20 grid place-items-center bg-slate-950/50 p-4">
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
                            <select wire:model="recipientName" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                                <option>Staf Administrasi</option>
                                <option>Kepala Subbagian Umum</option>
                                <option>Analis Kebijakan</option>
                            </select>
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Instruksi
                            <textarea wire:model="instruction" rows="4" class="rounded-lg border border-slate-200 px-3 py-2 text-slate-950" placeholder="Tuliskan instruksi tindak lanjut..."></textarea>
                            @error('instruction') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
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
