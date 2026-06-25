<?php

use App\Models\ActivityLog;
use App\Models\AppSetting;
use App\Models\Disposition;
use App\Models\DispositionRecipient;
use App\Support\TaskInbox;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $statusFilter = 'Semua';

    public int $perPage = 10;

    public bool $showForwardModal = false;

    public bool $showDetailModal = false;

    public ?int $selectedDispositionId = null;

    public ?int $forwardRecipientId = null;

    public string $forwardInstruction = '';

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->resetPage();
    }

    public function dispositions()
    {
        $user = auth()->user();

        return Disposition::query()
            ->with('letter.classification')
            ->when($user?->isDepartmentHead(), fn ($query) => $query->where('recipient_name', $user->name))
            ->when($this->statusFilter !== 'Semua', fn ($query) => $query->where('status', $this->statusFilter))
            ->latest()
            ->paginate($this->perPage);
    }

    public function forwardRecipients()
    {
        return DispositionRecipient::query()
            ->where('is_active', true)
            ->where('name', '!=', auth()->user()?->name)
            ->orderBy('name')
            ->get();
    }

    public function selectedDisposition(): ?Disposition
    {
        return Disposition::with('letter.classification', 'children')->find($this->selectedDispositionId);
    }

    public function openDispositionDetailModal(int $dispositionId): void
    {
        $disposition = Disposition::findOrFail($dispositionId);

        abort_unless($this->canActOnDisposition($disposition), 403);

        $this->selectedDispositionId = $disposition->id;
        $this->showDetailModal = true;
    }

    public function openForwardModal(int $dispositionId): void
    {
        $disposition = Disposition::findOrFail($dispositionId);

        abort_unless($this->canActOnDisposition($disposition), 403);

        $this->resetValidation();
        $this->selectedDispositionId = $dispositionId;
        $this->forwardRecipientId = $this->forwardRecipients()->first()?->id;
        $this->forwardInstruction = '';
        $this->showDetailModal = false;
        $this->showForwardModal = true;
    }

    public function forwardDisposition(): void
    {
        $validated = $this->validate([
            'selectedDispositionId' => ['required', 'exists:dispositions,id'],
            'forwardRecipientId' => ['required', Rule::exists('disposition_recipients', 'id')->where('is_active', true)],
            'forwardInstruction' => ['required', 'string', 'max:1000'],
        ]);

        $user = auth()->user();
        $parent = Disposition::findOrFail($validated['selectedDispositionId']);

        abort_unless($this->canActOnDisposition($parent), 403);

        $recipient = DispositionRecipient::findOrFail($validated['forwardRecipientId']);

        $forwarded = Disposition::create([
            'letter_id' => $parent->letter_id,
            'parent_id' => $parent->id,
            'sender_name' => $user->name,
            'recipient_name' => $recipient->name,
            'disposition_recipient_id' => $recipient->id,
            'instruction' => $validated['forwardInstruction'],
            'status' => 'Belum Dibaca',
        ]);

        $parent->update(['status' => 'Diproses']);
        $parent->letter?->syncDispositionStatus();

        ActivityLog::record(
            'disposition.forwarded',
            'Disposisi diteruskan ke '.$recipient->name.' oleh '.$user->name,
            $forwarded,
            ['parent_id' => $parent->id, 'letter_id' => $parent->letter_id],
        );

        $this->showForwardModal = false;
        $this->dispatch('notify', message: 'Disposisi diteruskan ke pelaksana.');
    }

    public function updateDispositionStatus(int $dispositionId, string $status): void
    {
        abort_unless(in_array($status, ['Belum Dibaca', 'Diproses', 'Selesai'], true), 422);

        $user = auth()->user();
        $disposition = Disposition::findOrFail($dispositionId);

        abort_unless($this->canActOnDisposition($disposition), 403);

        $disposition->update(['status' => $status]);
        $disposition->letter?->syncDispositionStatus();

        ActivityLog::record(
            'disposition.status_updated',
            'Status disposisi untuk '.$disposition->recipient_name.' diperbarui menjadi '.$status.'.',
            $disposition,
            ['letter_id' => $disposition->letter_id],
        );

        $this->dispatch('notify', message: 'Status disposisi diperbarui.');
    }

    public function canActOnDisposition(?Disposition $disposition): bool
    {
        if (! $disposition) {
            return false;
        }

        $user = auth()->user();

        return $user?->isAdmin() || $user?->isLeader() || ($user?->isDepartmentHead() && $disposition->recipient_name === $user->name);
    }

    public function statusClass(string $status): string
    {
        return match ($status) {
            'Baru' => 'bg-rose-100 text-rose-700 ring-rose-200',
            'Belum Dibaca', 'Disposisi' => 'bg-amber-100 text-amber-700 ring-amber-200',
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
        $currentUser = auth()->user();
        $agencyProfile = AppSetting::agency();
        $taskCount = TaskInbox::countFor($currentUser);
        $dispositions = $this->dispositions();
        $selectedDisposition = $this->selectedDisposition();
        $forwardRecipients = $this->forwardRecipients();
        $pendingCount = Disposition::query()
            ->when($currentUser?->isDepartmentHead(), fn ($query) => $query->where('recipient_name', $currentUser->name))
            ->where('status', 'Belum Dibaca')
            ->count();
        $processingCount = Disposition::query()
            ->when($currentUser?->isDepartmentHead(), fn ($query) => $query->where('recipient_name', $currentUser->name))
            ->where('status', 'Diproses')
            ->count();
        $doneCount = Disposition::query()
            ->when($currentUser?->isDepartmentHead(), fn ($query) => $query->where('recipient_name', $currentUser->name))
            ->where('status', 'Selesai')
            ->count();
    @endphp

    <aside class="bg-slate-900 px-5 py-5 text-slate-100 lg:sticky lg:top-0 lg:z-10 lg:h-screen lg:overflow-y-auto">
        <div class="flex items-center gap-3">
            <x-app-logo class="h-11 w-11" />
            <div>
                <div class="font-semibold">{{ $agencyProfile['app_name'] }}</div>
                <div class="text-sm text-slate-400">Area Kepala Bagian</div>
            </div>
        </div>

        <nav class="mt-8 grid gap-2">
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5"><x-icon name="dashboard" class="h-4 w-4" />Dasbor Umum</a>
            <a href="{{ route('my-tasks') }}" class="flex items-center justify-between rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5">
                <span class="inline-flex items-center gap-2"><x-icon name="task" class="h-4 w-4" />Tugas Saya</span>
                @if ($taskCount > 0)
                    <span class="rounded-full bg-rose-500 px-2 py-0.5 text-xs font-bold text-white">{{ $taskCount }}</span>
                @endif
            </a>
            @if ($currentUser?->isAdmin() || $currentUser?->isLeader() || $currentUser?->isPersonalSecretary())
                <a href="{{ route('leadership') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5"><x-icon name="send" class="h-4 w-4" />Pimpinan</a>
            @endif
            <a href="{{ route('tracking') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5"><x-icon name="route" class="h-4 w-4" />Pelacakan Surat</a>
            <a href="{{ route('department-head') }}" class="inline-flex items-center gap-2 rounded-lg bg-white/10 px-3 py-2 text-sm font-semibold text-white"><x-icon name="users" class="h-4 w-4" />Kepala Bagian</a>
            @if ($currentUser?->isAdmin())
                <a href="{{ route('number-monitor') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5"><x-icon name="list-numbered" class="h-4 w-4" />Monitoring Nomor</a>
                <a href="{{ route('sk-numbering') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5"><x-icon name="gavel" class="h-4 w-4" />Penomoran SK</a>
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
                <p class="text-xs font-bold uppercase text-teal-700">Kepala Bagian</p>
                <h1 class="mt-1 text-3xl font-bold tracking-normal text-slate-950">Tindak Lanjut Disposisi</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-600">Daftar disposisi {{ $agencyProfile['name'] }} yang diterima kepala bagian beserta status tindak lanjutnya.</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-800 shadow-sm hover:border-rose-500">
                    <x-icon name="logout" class="mr-2 h-4 w-4" />
                    Logout
                </button>
            </form>
        </header>

        <section class="mt-6 grid gap-3 md:grid-cols-3">
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <div class="text-sm text-amber-700">Belum Dibaca</div>
                <div class="mt-2 text-3xl font-bold">{{ $pendingCount }}</div>
            </div>
            <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-5 shadow-sm">
                <div class="text-sm text-indigo-700">Diproses</div>
                <div class="mt-2 text-3xl font-bold">{{ $processingCount }}</div>
            </div>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                <div class="text-sm text-emerald-700">Selesai</div>
                <div class="mt-2 text-3xl font-bold">{{ $doneCount }}</div>
            </div>
        </section>

        <section class="mt-5 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-5 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <h2 class="text-lg font-bold">Daftar Disposisi</h2>
                    <p class="text-sm text-slate-500">{{ $dispositions->total() }} disposisi ditampilkan.</p>
                </div>
                <div class="inline-flex rounded-lg bg-slate-100 p-1">
                    @foreach (['Semua', 'Belum Dibaca', 'Diproses', 'Selesai'] as $status)
                        <button type="button"
                                wire:click="setStatusFilter('{{ $status }}')"
                                class="rounded-md px-3 py-1.5 text-sm font-bold {{ $statusFilter === $status ? 'bg-white text-teal-700 shadow-sm' : 'text-slate-600' }}">
                            {{ $status }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[780px] border-collapse text-left text-sm">
                    <thead class="text-xs uppercase text-slate-500">
                        <tr>
                            <th class="border-b border-slate-200 px-5 py-3">Surat</th>
                            <th class="border-b border-slate-200 px-5 py-3">Perihal</th>
                            <th class="border-b border-slate-200 px-5 py-3">Instruksi</th>
                            <th class="border-b border-slate-200 px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($dispositions as $disposition)
                            <tr wire:key="head-disposition-{{ $disposition->id }}"
                                wire:click="openDispositionDetailModal({{ $disposition->id }})"
                                wire:keydown.enter="openDispositionDetailModal({{ $disposition->id }})"
                                role="button"
                                tabindex="0"
                                class="cursor-pointer transition {{ $selectedDispositionId === $disposition->id ? 'bg-teal-50' : 'hover:bg-slate-50' }}">
                                <td class="border-b border-slate-100 px-5 py-4">
                                    <div class="font-bold">{{ $disposition->letter?->number ?: 'Disposisi Mandiri' }}</div>
                                    <div class="text-xs text-slate-500">{{ $disposition->letter?->external_party ?: $disposition->sender_name }}</div>
                                </td>
                                <td class="border-b border-slate-100 px-5 py-4">{{ $disposition->letter?->subject ?: 'Arahan langsung / tanpa surat masuk' }}</td>
                                <td class="border-b border-slate-100 px-5 py-4">{{ $disposition->instruction }}</td>
                                <td class="border-b border-slate-100 px-5 py-4">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $this->statusClass($disposition->status) }}">{{ $disposition->status }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-16 text-center text-slate-500">Belum ada disposisi untuk halaman ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($dispositions->hasPages())
                <div class="border-t border-slate-200 p-5">{{ $dispositions->links() }}</div>
            @endif
        </section>

        @if ($showDetailModal && $selectedDisposition)
            <div wire:click.self="$set('showDetailModal', false)" class="fixed inset-0 z-20 grid place-items-center bg-slate-950/50 p-4">
                <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white shadow-xl">
                    <div class="flex items-start justify-between gap-4 border-b border-slate-200 p-6">
                        <div>
                            <p class="text-xs font-bold uppercase text-teal-700">Disposisi Kepala Bagian</p>
                            <h2 class="mt-1 text-xl font-bold">Detail Disposisi</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $selectedDisposition->letter?->number ?: 'Disposisi Mandiri' }}</p>
                        </div>
                        <button type="button" wire:click="$set('showDetailModal', false)" class="grid h-10 w-10 place-items-center rounded-lg bg-slate-100 text-xl font-bold">&times;</button>
                    </div>

                    <div class="space-y-5 p-6">
                        <div class="flex flex-wrap gap-2">
                            <span class="rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $this->statusClass($selectedDisposition->status) }}">{{ $selectedDisposition->status }}</span>
                            @if ($selectedDisposition->letter?->status)
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700 ring-1 ring-slate-200">Surat: {{ $selectedDisposition->letter->status }}</span>
                            @endif
                        </div>

                        @if ($this->canActOnDisposition($selectedDisposition))
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <div class="text-sm font-bold text-slate-700">Aksi Kepala Bagian</div>
                                <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                                    <select wire:change="updateDispositionStatus({{ $selectedDisposition->id }}, $event.target.value)" class="min-h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold">
                                        @foreach (['Belum Dibaca', 'Diproses', 'Selesai'] as $status)
                                            <option value="{{ $status }}" @selected($selectedDisposition->status === $status)>{{ $status }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" wire:click="openForwardModal({{ $selectedDisposition->id }})" class="min-h-10 rounded-lg bg-teal-700 px-4 text-sm font-bold text-white hover:bg-teal-800">Teruskan</button>
                                    @if ($selectedDisposition->letter?->file_path)
                                        <a href="{{ route('letters.document.review', $selectedDisposition->letter) }}" target="_blank" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold hover:border-teal-600">Review Dokumen</a>
                                        <a href="{{ route('letters.document.download', $selectedDisposition->letter) }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold hover:border-teal-600">Download</a>
                                    @endif
                                    @if ($selectedDisposition->scan_path)
                                        <a href="{{ route('dispositions.scan.review', $selectedDisposition) }}" target="_blank" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-amber-200 bg-white px-4 text-sm font-bold text-amber-700 hover:border-amber-500">Buka Scan Disposisi</a>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <div class="text-xs font-bold uppercase text-slate-500">Dari</div>
                                <div class="font-semibold">{{ $selectedDisposition->sender_name }}</div>
                            </div>
                            <div>
                                <div class="text-xs font-bold uppercase text-slate-500">Kepada</div>
                                <div class="font-semibold">{{ $selectedDisposition->recipient_name }}</div>
                            </div>
                            <div class="sm:col-span-2">
                                <div class="text-xs font-bold uppercase text-slate-500">Instruksi</div>
                                <div class="mt-1 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">{{ $selectedDisposition->instruction }}</div>
                            </div>
                            @if ($selectedDisposition->scan_path)
                                <div class="sm:col-span-2">
                                    <div class="text-xs font-bold uppercase text-slate-500">Scan Disposisi Pimpinan</div>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <a href="{{ route('dispositions.scan.review', $selectedDisposition) }}" target="_blank" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-bold hover:border-teal-600">Buka Scan</a>
                                        <a href="{{ route('dispositions.scan.download', $selectedDisposition) }}" class="rounded-lg bg-teal-700 px-3 py-1.5 text-sm font-bold text-white hover:bg-teal-800">Download Scan</a>
                                    </div>
                                    @if ($selectedDisposition->input_by_name)
                                        <div class="mt-2 text-xs text-slate-500">Diinput oleh {{ $selectedDisposition->input_by_name }} ({{ $selectedDisposition->input_by_role }})</div>
                                    @endif
                                </div>
                            @endif
                            <div>
                                <div class="text-xs font-bold uppercase text-slate-500">Kode Klasifikasi Arsip</div>
                                <div class="font-semibold">{{ $selectedDisposition->letter?->classification_code ?: '-' }}</div>
                            </div>
                            <div>
                                <div class="text-xs font-bold uppercase text-slate-500">Pengirim Surat</div>
                                <div class="font-semibold">{{ $selectedDisposition->letter?->external_party ?: $selectedDisposition->sender_name }}</div>
                            </div>
                            <div class="sm:col-span-2">
                                <div class="text-xs font-bold uppercase text-slate-500">Perihal Surat</div>
                                <div class="font-semibold">{{ $selectedDisposition->letter?->subject ?: 'Arahan langsung / tanpa surat masuk' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($showForwardModal)
            <div wire:click.self="$set('showForwardModal', false)" class="fixed inset-0 z-20 grid place-items-center bg-slate-950/50 p-4">
                <form wire:submit="forwardDisposition" class="w-full max-w-xl rounded-lg bg-white p-6 shadow-xl">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase text-teal-700">Tindak Lanjut Kepala Bagian</p>
                            <h2 class="mt-1 text-xl font-bold">Teruskan Disposisi</h2>
                        </div>
                        <button type="button" wire:click="$set('showForwardModal', false)" class="grid h-10 w-10 place-items-center rounded-lg bg-slate-100 text-xl font-bold">&times;</button>
                    </div>

                    <div class="mt-5 grid gap-4">
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Penerima Lanjutan
                            <select wire:model="forwardRecipientId" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                                <option value="">Pilih penerima</option>
                                @foreach ($forwardRecipients as $recipient)
                                    <option value="{{ $recipient->id }}">{{ $recipient->name }}{{ $recipient->position ? ' - '.$recipient->position : '' }}</option>
                                @endforeach
                            </select>
                            @error('forwardRecipientId') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Instruksi Lanjutan
                            <textarea wire:model="forwardInstruction" rows="4" class="rounded-lg border border-slate-200 px-3 py-2 text-slate-950" placeholder="Tuliskan instruksi untuk staf/unit pelaksana..."></textarea>
                            @error('forwardInstruction') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" wire:click="$set('showForwardModal', false)" class="min-h-11 rounded-lg border border-slate-200 px-4 text-sm font-bold">Batal</button>
                        <button type="submit" class="min-h-11 rounded-lg bg-teal-700 px-4 text-sm font-bold text-white hover:bg-teal-800">Teruskan</button>
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
