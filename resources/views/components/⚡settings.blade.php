<?php

use App\Models\AppSetting;
use Livewire\Component;

new class extends Component
{
    public array $agency = [];
    public array $numbering = [];
    public array $workflow = [];
    public array $roles = [];

    public string $newRoleName = '';
    public string $newRoleDescription = '';

    public function mount(): void
    {
        $this->agency = AppSetting::getValue('agency', [
            'name' => '',
            'unit' => '',
            'address' => '',
            'email' => '',
            'phone' => '',
        ]);

        $this->numbering = AppSetting::getValue('letter_numbering', [
            'prefix' => '800',
            'unit_code' => 'SET-MRP',
            'separator' => '/',
            'include_month' => true,
            'include_year' => true,
            'next_sequence' => 1,
        ]);

        $this->workflow = AppSetting::getValue('workflow', [
            'max_upload_mb' => 5,
            'allowed_files' => 'PDF, JPG, JPEG, PNG',
            'auto_archive_done' => true,
            'notify_disposition' => true,
        ]);

        $this->roles = AppSetting::getValue('roles', []);
    }

    public function saveAgency(): void
    {
        $this->validate([
            'agency.name' => ['required', 'string', 'max:255'],
            'agency.unit' => ['required', 'string', 'max:255'],
            'agency.address' => ['nullable', 'string', 'max:500'],
            'agency.email' => ['required', 'email', 'max:255'],
            'agency.phone' => ['nullable', 'string', 'max:50'],
        ]);

        AppSetting::putValue('agency', $this->agency);
        $this->dispatch('notify', message: 'Profil instansi berhasil disimpan.');
    }

    public function saveNumbering(): void
    {
        $this->validate([
            'numbering.prefix' => ['required', 'string', 'max:20'],
            'numbering.unit_code' => ['required', 'in:SET-MRP,MRP'],
            'numbering.separator' => ['required', 'string', 'max:3'],
            'numbering.next_sequence' => ['required', 'integer', 'min:1'],
        ]);

        $this->numbering['include_month'] = (bool) ($this->numbering['include_month'] ?? false);
        $this->numbering['include_year'] = (bool) ($this->numbering['include_year'] ?? false);

        AppSetting::putValue('letter_numbering', $this->numbering);
        $this->dispatch('notify', message: 'Format nomor surat berhasil disimpan.');
    }

    public function saveWorkflow(): void
    {
        $this->validate([
            'workflow.max_upload_mb' => ['required', 'integer', 'min:1', 'max:50'],
            'workflow.allowed_files' => ['required', 'string', 'max:100'],
        ]);

        $this->workflow['auto_archive_done'] = (bool) ($this->workflow['auto_archive_done'] ?? false);
        $this->workflow['notify_disposition'] = (bool) ($this->workflow['notify_disposition'] ?? false);

        AppSetting::putValue('workflow', $this->workflow);
        $this->dispatch('notify', message: 'Pengaturan alur kerja berhasil disimpan.');
    }

    public function addRole(): void
    {
        $this->validate([
            'newRoleName' => ['required', 'string', 'max:100'],
            'newRoleDescription' => ['required', 'string', 'max:255'],
        ]);

        $this->roles[] = [
            'name' => $this->newRoleName,
            'description' => $this->newRoleDescription,
        ];

        $this->newRoleName = '';
        $this->newRoleDescription = '';

        AppSetting::putValue('roles', $this->roles);
        $this->dispatch('notify', message: 'Peran pengguna berhasil ditambahkan.');
    }

    public function removeRole(int $index): void
    {
        unset($this->roles[$index]);
        $this->roles = array_values($this->roles);

        AppSetting::putValue('roles', $this->roles);
        $this->dispatch('notify', message: 'Peran pengguna dihapus.');
    }

    public function sampleNumber(): string
    {
        $separator = $this->numbering['separator'] ?? '/';
        $parts = [
            $this->numbering['prefix'] ?? '800',
            str_pad((string) ($this->numbering['next_sequence'] ?? 1), 3, '0', STR_PAD_LEFT),
            $this->numbering['unit_code'] ?? 'SET-MRP',
        ];

        if ($this->numbering['include_month'] ?? true) {
            $parts[] = now()->format('m');
        }

        if ($this->numbering['include_year'] ?? true) {
            $parts[] = now()->format('Y');
        }

        return implode($separator, $parts);
    }
};
?>

<div x-data="{ toast: '', showToast: false }"
     x-on:notify.window="toast = $event.detail.message; showToast = true; setTimeout(() => showToast = false, 2600)"
     class="min-h-screen lg:grid lg:grid-cols-[280px_minmax(0,1fr)]">
    @php
        $currentUser = auth()->user();
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
            <a href="{{ route('dashboard') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5">Dasbor</a>

            <div class="min-w-40 lg:min-w-0">
                <button type="button"
                        x-on:click="incomingOpen = ! incomingOpen"
                        class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm font-semibold text-slate-300 transition hover:bg-white/5">
                    <span>Surat Masuk</span>
                    <span x-text="incomingOpen ? '−' : '+'" class="text-slate-400"></span>
                </button>
                <div x-show="incomingOpen" class="mt-1 grid gap-1 pl-3">
                    <a href="{{ route('dashboard', ['filter' => 'Masuk', 'unit' => 'SET-MRP']) }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-400 transition hover:bg-white/5 hover:text-slate-200">SET-MRP</a>
                    <a href="{{ route('dashboard', ['filter' => 'Masuk', 'unit' => 'MRP']) }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-400 transition hover:bg-white/5 hover:text-slate-200">MRP</a>
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
                    <a href="{{ route('dashboard', ['filter' => 'Keluar', 'unit' => 'SET-MRP']) }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-400 transition hover:bg-white/5 hover:text-slate-200">SET-MRP</a>
                    <a href="{{ route('dashboard', ['filter' => 'Keluar', 'unit' => 'MRP']) }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-400 transition hover:bg-white/5 hover:text-slate-200">MRP</a>
                </div>
            </div>

            <a href="{{ route('dashboard') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5">Disposisi</a>
            <a href="{{ route('settings') }}" class="rounded-lg bg-white/10 px-3 py-2 text-sm font-semibold text-white">Setting</a>
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
                <p class="text-xs font-bold uppercase text-teal-700">Konfigurasi Aplikasi</p>
                <h1 class="mt-1 text-3xl font-bold tracking-normal text-slate-950">Setting E-Surat MRP</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-600">Atur identitas Sekretariat MRP, format nomor surat, batas dokumen, notifikasi, dan peran pengguna MRP Provinsi Papua Tengah.</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <a href="{{ route('dashboard') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-800 shadow-sm hover:border-teal-600">
                    Kembali ke Dasbor
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-800 shadow-sm hover:border-rose-500">
                        Logout
                    </button>
                </form>
            </div>
        </header>

        <section class="mt-6 grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="space-y-5">
                <form wire:submit="saveAgency" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="border-b border-slate-200 pb-4">
                        <h2 class="text-lg font-bold">Profil Instansi</h2>
                        <p class="text-sm text-slate-500">Data ini menjadi identitas utama pada sistem persuratan.</p>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Nama Instansi
                            <input wire:model="agency.name" type="text" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                            @error('agency.name') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Unit Kerja
                            <input wire:model="agency.unit" type="text" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                            @error('agency.unit') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Email
                            <input wire:model="agency.email" type="email" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                            @error('agency.email') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Telepon
                            <input wire:model="agency.phone" type="text" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                            @error('agency.phone') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600 md:col-span-2">
                            Alamat
                            <textarea wire:model="agency.address" rows="3" class="rounded-lg border border-slate-200 px-3 py-2 text-slate-950"></textarea>
                            @error('agency.address') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                    </div>

                    <div class="mt-5 flex justify-end">
                        <button type="submit" class="min-h-11 rounded-lg bg-teal-700 px-4 text-sm font-bold text-white hover:bg-teal-800">Simpan Profil</button>
                    </div>
                </form>

                <form wire:submit="saveNumbering" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="border-b border-slate-200 pb-4">
                        <h2 class="text-lg font-bold">Format Nomor Surat Keluar</h2>
                        <p class="text-sm text-slate-500">Digunakan saat sistem membuat nomor surat otomatis.</p>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-3">
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Kode Awal
                            <input wire:model.live="numbering.prefix" type="text" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                            @error('numbering.prefix') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Kode Unit
                            <select wire:model.live="numbering.unit_code" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                                <option value="SET-MRP">SET-MRP</option>
                                <option value="MRP">MRP</option>
                            </select>
                            @error('numbering.unit_code') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Pemisah
                            <input wire:model.live="numbering.separator" type="text" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                            @error('numbering.separator') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Nomor Berikutnya
                            <input wire:model.live="numbering.next_sequence" type="number" min="1" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                            @error('numbering.next_sequence') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="flex min-h-11 items-center gap-3 rounded-lg border border-slate-200 px-3 text-sm font-bold text-slate-600">
                            <input wire:model.live="numbering.include_month" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-teal-700">
                            Pakai Bulan
                        </label>
                        <label class="flex min-h-11 items-center gap-3 rounded-lg border border-slate-200 px-3 text-sm font-bold text-slate-600">
                            <input wire:model.live="numbering.include_year" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-teal-700">
                            Pakai Tahun
                        </label>
                    </div>

                    <div class="mt-5 flex flex-col gap-3 rounded-lg bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="text-xs font-bold uppercase text-slate-500">Preview Nomor</div>
                            <div class="mt-1 font-bold text-slate-950">{{ $this->sampleNumber() }}</div>
                        </div>
                        <button type="submit" class="min-h-11 rounded-lg bg-teal-700 px-4 text-sm font-bold text-white hover:bg-teal-800">Simpan Format</button>
                    </div>
                </form>

                <form wire:submit="saveWorkflow" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="border-b border-slate-200 pb-4">
                        <h2 class="text-lg font-bold">Dokumen dan Alur Kerja</h2>
                        <p class="text-sm text-slate-500">Kontrol batas upload, jenis file, arsip otomatis, dan notifikasi disposisi.</p>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Maksimal Upload (MB)
                            <input wire:model="workflow.max_upload_mb" type="number" min="1" max="50" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                            @error('workflow.max_upload_mb') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Jenis File Diizinkan
                            <input wire:model="workflow.allowed_files" type="text" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                            @error('workflow.allowed_files') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="flex min-h-12 items-center gap-3 rounded-lg border border-slate-200 px-3 text-sm font-bold text-slate-600">
                            <input wire:model="workflow.auto_archive_done" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-teal-700">
                            Arsipkan otomatis saat status selesai
                        </label>
                        <label class="flex min-h-12 items-center gap-3 rounded-lg border border-slate-200 px-3 text-sm font-bold text-slate-600">
                            <input wire:model="workflow.notify_disposition" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-teal-700">
                            Kirim notifikasi disposisi
                        </label>
                    </div>

                    <div class="mt-5 flex justify-end">
                        <button type="submit" class="min-h-11 rounded-lg bg-teal-700 px-4 text-sm font-bold text-white hover:bg-teal-800">Simpan Alur Kerja</button>
                    </div>
                </form>
            </div>

            <aside class="space-y-5">
                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-bold">Ringkasan</h2>
                    <div class="mt-4 space-y-4 text-sm">
                        <div>
                            <div class="font-bold text-slate-500">Instansi</div>
                            <div class="font-semibold">{{ $agency['name'] ?? '-' }}</div>
                            <div class="text-slate-500">{{ $agency['unit'] ?? '-' }}</div>
                        </div>
                        <div>
                            <div class="font-bold text-slate-500">Format Nomor</div>
                            <div class="font-semibold">{{ $this->sampleNumber() }}</div>
                        </div>
                        <div>
                            <div class="font-bold text-slate-500">Upload</div>
                            <div class="font-semibold">{{ $workflow['max_upload_mb'] ?? 5 }} MB</div>
                            <div class="text-slate-500">{{ $workflow['allowed_files'] ?? '-' }}</div>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-bold">Peran Pengguna</h2>
                    <div class="mt-4 space-y-3">
                        @foreach ($roles as $index => $role)
                            <div wire:key="role-{{ $index }}" class="rounded-lg border border-slate-200 p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="font-bold">{{ $role['name'] }}</div>
                                        <p class="mt-1 text-sm text-slate-500">{{ $role['description'] }}</p>
                                    </div>
                                    <button type="button" wire:click="removeRole({{ $index }})" class="rounded-lg border border-rose-200 px-2 py-1 text-xs font-bold text-rose-700 hover:bg-rose-50">Hapus</button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <form wire:submit="addRole" class="mt-4 space-y-3 rounded-lg bg-slate-50 p-3">
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Nama Peran
                            <input wire:model="newRoleName" type="text" class="min-h-10 rounded-lg border border-slate-200 px-3 text-slate-950">
                            @error('newRoleName') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Deskripsi
                            <textarea wire:model="newRoleDescription" rows="3" class="rounded-lg border border-slate-200 px-3 py-2 text-slate-950"></textarea>
                            @error('newRoleDescription') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <button type="submit" class="min-h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-bold hover:border-teal-600">Tambah Peran</button>
                    </form>
                </section>
            </aside>
        </section>
    </main>

    <div x-show="showToast"
         x-transition
         x-cloak
         class="fixed bottom-6 right-6 z-30 max-w-sm rounded-lg bg-slate-900 px-4 py-3 text-sm font-semibold text-white shadow-lg"
         x-text="toast"></div>
</div>
