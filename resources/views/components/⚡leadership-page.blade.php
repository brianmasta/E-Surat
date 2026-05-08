<?php

use App\Models\ActivityLog;
use App\Models\AppSetting;
use App\Models\DispositionRecipient;
use App\Models\Letter;
use App\Support\TaskInbox;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $statusFilter = 'Semua';

    public int $perPage = 10;

    public bool $showDispositionModal = false;

    public bool $showDetailModal = false;

    public ?int $selectedLetterId = null;

    public ?int $recipientId = null;

    public string $instruction = '';

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function incomingLetters()
    {
        return Letter::query()
            ->with('classification')
            ->where('type', 'Masuk')
            ->when($this->statusFilter !== 'Semua', fn ($query) => $query->where('status', $this->statusFilter))
            ->latest('letter_date')
            ->latest()
            ->paginate($this->perPage);
    }

    public function dispositionRecipients()
    {
        return DispositionRecipient::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query
                    ->where('name', 'like', '%Kepala Bagian%')
                    ->orWhere('position', 'like', '%Kepala Bagian%');
            })
            ->orderBy('name')
            ->get();
    }

    public function selectedLetter(): ?Letter
    {
        return Letter::with('classification')->find($this->selectedLetterId);
    }

    public function openLetterDetailModal(int $letterId): void
    {
        $this->selectedLetterId = $letterId;
        $this->showDetailModal = true;
    }

    public function openDispositionModal(int $letterId): void
    {
        abort_unless($this->canDisposeLetters(), 403);

        $this->resetValidation();
        $this->selectedLetterId = $letterId;
        $this->recipientId = $this->dispositionRecipients()->first()?->id;
        $this->instruction = '';
        $this->showDetailModal = false;
        $this->showDispositionModal = true;
    }

    public function saveDisposition(): void
    {
        abort_unless($this->canDisposeLetters(), 403);

        $validated = $this->validate([
            'selectedLetterId' => ['required', 'exists:letters,id'],
            'recipientId' => ['required', Rule::exists('disposition_recipients', 'id')->where('is_active', true)],
            'instruction' => ['required', 'string', 'max:1000'],
        ]);

        $letter = Letter::findOrFail($validated['selectedLetterId']);
        $recipient = DispositionRecipient::findOrFail($validated['recipientId']);

        $disposition = $letter->dispositions()->create([
            'sender_name' => auth()->user()->name,
            'recipient_name' => $recipient->name,
            'disposition_recipient_id' => $recipient->id,
            'instruction' => $validated['instruction'],
            'status' => 'Belum Dibaca',
        ]);

        $letter->update(['status' => 'Disposisi']);

        ActivityLog::record(
            'disposition.created',
            'Pimpinan mengirim disposisi ke '.$recipient->name.' untuk surat '.$letter->number,
            $disposition,
            ['letter_id' => $letter->id],
        );

        $this->showDispositionModal = false;
        $this->dispatch('notify', message: 'Disposisi pimpinan dikirim ke kepala bagian.');
    }

    public function canDisposeLetters(): bool
    {
        $user = auth()->user();

        return $user?->isAdmin() || $user?->isLeader();
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
};
?>

<div x-data="{ toast: '', showToast: false }"
     x-on:notify.window="toast = $event.detail.message; showToast = true; setTimeout(() => showToast = false, 2600)"
     class="min-h-screen lg:grid lg:grid-cols-[280px_minmax(0,1fr)]">
    @php
        $letters = $this->incomingLetters();
        $selectedLetter = $this->selectedLetter();
        $recipients = $this->dispositionRecipients();
        $currentUser = auth()->user();
        $agencyProfile = AppSetting::agency();
        $taskCount = TaskInbox::countFor($currentUser);
        $newIncomingCount = \App\Models\Letter::where('type', 'Masuk')->where('status', 'Baru')->count();
        $dispositionCount = \App\Models\Letter::where('type', 'Masuk')->where('status', 'Disposisi')->count();
        $processedCount = \App\Models\Letter::where('type', 'Masuk')->whereIn('status', ['Diproses', 'Selesai'])->count();
        $latestDispositions = \App\Models\Disposition::query()->with('letter')->latest()->limit(5)->get();
    @endphp

    <aside class="bg-slate-900 px-5 py-5 text-slate-100 lg:sticky lg:top-0 lg:z-10 lg:h-screen lg:overflow-y-auto">
        <div class="flex items-center gap-3">
            <div class="grid h-11 w-11 place-items-center rounded-lg bg-teal-100 font-bold text-teal-800">{{ $agencyProfile['short_name'] }}</div>
            <div>
                <div class="font-semibold">{{ $agencyProfile['app_name'] }}</div>
                <div class="text-sm text-slate-400">Area {{ $agencyProfile['leader_title'] }}</div>
            </div>
        </div>

        <nav class="mt-8 grid gap-2">
            <a href="{{ route('dashboard') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5">Dasbor Umum</a>
            <a href="{{ route('my-tasks') }}" class="flex items-center justify-between rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5">
                <span>Tugas Saya</span>
                @if ($taskCount > 0)
                    <span class="rounded-full bg-rose-500 px-2 py-0.5 text-xs font-bold text-white">{{ $taskCount }}</span>
                @endif
            </a>
            <a href="{{ route('leadership') }}" class="rounded-lg bg-white/10 px-3 py-2 text-sm font-semibold text-white">Pimpinan</a>
            @if ($currentUser?->isAdmin() || $currentUser?->isDepartmentHead())
                <a href="{{ route('department-head') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5">Kepala Bagian</a>
            @endif
            @if ($currentUser?->isAdmin())
                <a href="{{ route('settings') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5">Setting</a>
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
                <p class="text-xs font-bold uppercase text-teal-700">Pimpinan</p>
                <h1 class="mt-1 text-3xl font-bold tracking-normal text-slate-950">Pemantauan Surat Masuk</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-600">Ringkasan surat masuk {{ $agencyProfile['name'] }} yang perlu dibaca, didisposisikan, dan dipantau tindak lanjutnya.</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-800 shadow-sm hover:border-rose-500">
                    Logout
                </button>
            </form>
        </header>

        <section class="mt-6 grid gap-3 md:grid-cols-3">
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-5 shadow-sm">
                <div class="text-sm text-rose-700">Menunggu Disposisi</div>
                <div class="mt-2 text-3xl font-bold">{{ $newIncomingCount }}</div>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <div class="text-sm text-amber-700">Sudah Didisposisi</div>
                <div class="mt-2 text-3xl font-bold">{{ $dispositionCount }}</div>
            </div>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                <div class="text-sm text-emerald-700">Diproses atau Selesai</div>
                <div class="mt-2 text-3xl font-bold">{{ $processedCount }}</div>
            </div>
        </section>

        <section class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1.5fr)_minmax(320px,0.8fr)]">
            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-200 p-5 xl:flex-row xl:items-center xl:justify-between">
                    <div>
                        <h2 class="text-lg font-bold">Surat Masuk untuk Pimpinan</h2>
                        <p class="text-sm text-slate-500">{{ $letters->total() }} surat dalam daftar kerja pimpinan.</p>
                    </div>
                    <div class="inline-flex rounded-lg bg-slate-100 p-1">
                        @foreach (['Semua', 'Baru', 'Disposisi', 'Diproses', 'Selesai'] as $status)
                            <button type="button"
                                    wire:click="setStatusFilter('{{ $status }}')"
                                    class="rounded-md px-3 py-1.5 text-sm font-bold {{ $statusFilter === $status ? 'bg-white text-teal-700 shadow-sm' : 'text-slate-600' }}">
                                {{ $status }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] border-collapse text-left text-sm">
                        <thead class="text-xs uppercase text-slate-500">
                            <tr>
                                <th class="border-b border-slate-200 px-5 py-3">Nomor</th>
                                <th class="border-b border-slate-200 px-5 py-3">Kode Arsip</th>
                                <th class="border-b border-slate-200 px-5 py-3">Perihal</th>
                                <th class="border-b border-slate-200 px-5 py-3">Pengirim</th>
                                <th class="border-b border-slate-200 px-5 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($letters as $letter)
                                <tr wire:key="leadership-letter-{{ $letter->id }}"
                                    wire:click="openLetterDetailModal({{ $letter->id }})"
                                    wire:keydown.enter="openLetterDetailModal({{ $letter->id }})"
                                    role="button"
                                    tabindex="0"
                                    class="cursor-pointer transition {{ $selectedLetterId === $letter->id ? 'bg-teal-50' : 'hover:bg-slate-50' }}">
                                    <td class="border-b border-slate-100 px-5 py-4">
                                        <div class="font-bold">{{ $letter->number }}</div>
                                        <div class="text-xs text-slate-500">{{ $letter->letter_date->translatedFormat('d F Y') }}</div>
                                    </td>
                                    <td class="border-b border-slate-100 px-5 py-4">{{ $letter->classification_code ?: '-' }}</td>
                                    <td class="border-b border-slate-100 px-5 py-4">{{ $letter->subject }}</td>
                                    <td class="border-b border-slate-100 px-5 py-4">{{ $letter->external_party }}</td>
                                    <td class="border-b border-slate-100 px-5 py-4">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $this->statusClass($letter->status) }}">{{ $letter->status }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-16 text-center text-slate-500">Tidak ada surat pada filter ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($letters->hasPages())
                    <div class="border-t border-slate-200 p-5">{{ $letters->links() }}</div>
                @endif
            </div>

            <aside class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold">Disposisi Terbaru</h2>
                <div class="mt-4 space-y-4">
                    @forelse ($latestDispositions as $disposition)
                        <div class="border-l-4 border-teal-700 pl-3">
                            <div class="font-bold">{{ $disposition->recipient_name }}</div>
                            <div class="text-sm text-slate-500">{{ $disposition->letter?->number }}</div>
                            <p class="mt-1 text-sm text-slate-600">{{ $disposition->instruction }}</p>
                            <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $this->statusClass($disposition->status) }}">{{ $disposition->status }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Belum ada disposisi.</p>
                    @endforelse
                </div>
            </aside>
        </section>

        @if ($showDetailModal && $selectedLetter)
            <div class="fixed inset-0 z-20 grid place-items-center bg-slate-950/50 p-4">
                <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white shadow-xl">
                    <div class="flex items-start justify-between gap-4 border-b border-slate-200 p-6">
                        <div>
                            <p class="text-xs font-bold uppercase text-teal-700">{{ $selectedLetter->unit_code }} | {{ $selectedLetter->type }}</p>
                            <h2 class="mt-1 text-xl font-bold">Detail Surat Masuk</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $selectedLetter->number }}</p>
                        </div>
                        <button type="button" wire:click="$set('showDetailModal', false)" class="grid h-10 w-10 place-items-center rounded-lg bg-slate-100 text-xl font-bold">&times;</button>
                    </div>

                    <div class="space-y-5 p-6">
                        <div class="flex flex-wrap gap-2">
                            <span class="rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $this->statusClass($selectedLetter->status) }}">{{ $selectedLetter->status }}</span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700 ring-1 ring-slate-200">{{ $selectedLetter->unit_code }}</span>
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <div class="text-sm font-bold text-slate-700">Aksi Pimpinan</div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @if ($this->canDisposeLetters())
                                    <button type="button" wire:click="openDispositionModal({{ $selectedLetter->id }})" class="min-h-10 rounded-lg bg-teal-700 px-4 text-sm font-bold text-white hover:bg-teal-800">Disposisi</button>
                                @endif
                                @if ($selectedLetter->file_path)
                                    <a href="{{ route('letters.document.review', $selectedLetter) }}" target="_blank" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold hover:border-teal-600">Review Dokumen</a>
                                    <a href="{{ route('letters.document.download', $selectedLetter) }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold hover:border-teal-600">Download</a>
                                @endif
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <div class="text-xs font-bold uppercase text-slate-500">Kode Klasifikasi Arsip</div>
                                <div class="font-semibold">{{ $selectedLetter->classification_code ? $selectedLetter->classification_code.' - '.$selectedLetter->classification?->name : '-' }}</div>
                            </div>
                            <div>
                                <div class="text-xs font-bold uppercase text-slate-500">Tanggal Surat</div>
                                <div class="font-semibold">{{ $selectedLetter->letter_date->translatedFormat('d F Y') }}</div>
                            </div>
                            <div>
                                <div class="text-xs font-bold uppercase text-slate-500">Pengirim</div>
                                <div class="font-semibold">{{ $selectedLetter->external_party }}</div>
                            </div>
                            <div>
                                <div class="text-xs font-bold uppercase text-slate-500">Nomor Agenda</div>
                                <div class="font-semibold">{{ $selectedLetter->agenda_number ?: '-' }}</div>
                            </div>
                            <div class="sm:col-span-2">
                                <div class="text-xs font-bold uppercase text-slate-500">Perihal</div>
                                <div class="font-semibold">{{ $selectedLetter->subject }}</div>
                            </div>
                            <div class="sm:col-span-2">
                                <div class="text-xs font-bold uppercase text-slate-500">Sifat dan Prioritas</div>
                                <div class="font-semibold">{{ $selectedLetter->nature }} | {{ $selectedLetter->urgency }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($showDispositionModal)
            <div class="fixed inset-0 z-20 grid place-items-center bg-slate-950/50 p-4">
                <form wire:submit="saveDisposition" class="w-full max-w-xl rounded-lg bg-white p-6 shadow-xl">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase text-teal-700">Disposisi Pimpinan</p>
                            <h2 class="mt-1 text-xl font-bold">Kirim ke Kepala Bagian</h2>
                        </div>
                        <button type="button" wire:click="$set('showDispositionModal', false)" class="grid h-10 w-10 place-items-center rounded-lg bg-slate-100 text-xl font-bold">&times;</button>
                    </div>

                    <div class="mt-5 grid gap-4">
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Kepala Bagian Penerima
                            <select wire:model="recipientId" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                                <option value="">Pilih kepala bagian</option>
                                @foreach ($recipients as $recipient)
                                    <option value="{{ $recipient->id }}">{{ $recipient->name }}{{ $recipient->unit ? ' - '.$recipient->unit : '' }}</option>
                                @endforeach
                            </select>
                            @error('recipientId') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Instruksi Pimpinan
                            <textarea wire:model="instruction" rows="4" class="rounded-lg border border-slate-200 px-3 py-2 text-slate-950" placeholder="Tuliskan arahan tindak lanjut..."></textarea>
                            @error('instruction') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" wire:click="$set('showDispositionModal', false)" class="min-h-11 rounded-lg border border-slate-200 px-4 text-sm font-bold">Batal</button>
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
