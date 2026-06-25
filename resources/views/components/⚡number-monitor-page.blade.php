<?php

use App\Models\AppSetting;
use App\Support\LetterNumbering;
use App\Support\TaskInbox;
use Illuminate\Support\Carbon;
use Livewire\Component;

new class extends Component
{
    public string $unitCode = 'SET-MRP';

    public int $year;

    public string $checkSequence = '';

    public function mount(): void
    {
        $this->unitCode = AppSetting::defaultLetterUnitCode();
        $this->year = (int) now()->format('Y');
    }

    public function units(): array
    {
        return AppSetting::letterUnits();
    }

    public function monitor(): array
    {
        return LetterNumbering::monitor(
            $this->unitCode,
            $this->year,
            $this->checkSequence !== '' ? (int) $this->checkSequence : null,
        );
    }

    public function formatDate(?string $date): string
    {
        return $date ? Carbon::parse($date)->translatedFormat('d M Y') : '-';
    }

    public function formatSequence(int|string|null $sequence): string
    {
        return str_pad((string) ($sequence ?: 0), 3, '0', STR_PAD_LEFT);
    }
};
?>

<div class="min-h-screen lg:grid lg:grid-cols-[280px_minmax(0,1fr)]">
    @php
        $currentUser = auth()->user();
        $agencyProfile = AppSetting::agency();
        $taskCount = TaskInbox::countFor($currentUser);
        $units = $this->units();
        $numberMonitor = $this->monitor();
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
            <a href="{{ route('number-monitor') }}" class="inline-flex items-center gap-2 rounded-lg bg-white/10 px-3 py-2 text-sm font-semibold text-white"><x-icon name="list-numbered" class="h-4 w-4" />Monitoring Nomor</a>
            @if ($currentUser?->isAdmin() || $currentUser?->isLeader() || $currentUser?->isPersonalSecretary())
                <a href="{{ route('leadership') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5"><x-icon name="send" class="h-4 w-4" />Halaman Pimpinan</a>
            @endif
            @if ($currentUser?->isAdmin() || $currentUser?->isDepartmentHead())
                <a href="{{ route('department-head') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5"><x-icon name="users" class="h-4 w-4" />Halaman Kepala Bagian</a>
            @endif
            @if ($currentUser?->isAdmin())
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
                <p class="text-xs font-bold uppercase text-teal-700">Monitoring</p>
                <h1 class="mt-1 text-3xl font-bold tracking-normal text-slate-950">Monitoring Nomor Surat</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-600">Pantau nomor surat keluar yang sudah terpakai, nomor kosong, dan rekomendasi nomor yang bisa dipakai.</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <a href="{{ route('dashboard', ['create' => 'Keluar', 'unit' => $numberMonitor['unit_code']]) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-teal-700 px-4 text-sm font-bold text-white shadow-sm hover:bg-teal-800">
                    <x-icon name="outbox" class="mr-2 h-4 w-4" />
                    Buat Surat Keluar
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-800 shadow-sm hover:border-rose-500">
                        <x-icon name="logout" class="mr-2 h-4 w-4" />
                        Logout
                    </button>
                </form>
            </div>
        </header>

        <section class="mt-6 grid gap-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-3">
            <label class="grid gap-1 text-sm font-bold text-slate-600">
                Unit Surat
                <select wire:model.live="unitCode" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                    @foreach ($units as $unit)
                        <option value="{{ $unit['code'] }}">{{ $unit['code'] }} - {{ $unit['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="grid gap-1 text-sm font-bold text-slate-600">
                Tahun Surat
                <input wire:model.live="year" type="number" min="2000" max="2100" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
            </label>
            <label class="grid gap-1 text-sm font-bold text-slate-600">
                Cek Nomor Urut
                <input wire:model.live.debounce.300ms="checkSequence" type="number" min="1" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950" placeholder="Contoh: 25">
            </label>
        </section>

        <section class="mt-5 grid gap-3 md:grid-cols-4">
            <div class="rounded-lg border border-teal-200 bg-teal-50 p-5 shadow-sm">
                <div class="text-sm font-bold text-teal-700">Nomor Berikutnya</div>
                <div class="mt-2 text-3xl font-bold">{{ $this->formatSequence($numberMonitor['next_sequence']) }}</div>
                <div class="mt-1 break-all text-xs font-semibold text-teal-800">{{ $numberMonitor['next_outgoing_number'] }}</div>
            </div>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                <div class="text-sm font-bold text-emerald-700">Nomor Terpakai</div>
                <div class="mt-2 text-3xl font-bold">{{ $numberMonitor['used_count'] }}</div>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <div class="text-sm font-bold text-amber-700">Nomor Kosong</div>
                <div class="mt-2 text-3xl font-bold">{{ $numberMonitor['missing_count'] }}</div>
            </div>
            <div class="rounded-lg border {{ $numberMonitor['check_sequence'] ? ($numberMonitor['check_is_available'] ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50') : 'border-slate-200 bg-slate-50' }} p-5 shadow-sm">
                <div class="text-sm font-bold {{ $numberMonitor['check_sequence'] ? ($numberMonitor['check_is_available'] ? 'text-emerald-700' : 'text-rose-700') : 'text-slate-600' }}">Hasil Cek</div>
                <div class="mt-2 text-sm font-semibold">
                    @if ($numberMonitor['check_sequence'])
                        Nomor {{ $this->formatSequence($numberMonitor['check_sequence']) }}
                        {{ $numberMonitor['check_is_available'] ? 'masih kosong' : 'sudah dipakai' }}
                        @if ($numberMonitor['check_is_available'] && $numberMonitor['check_recommendation'])
                            <div class="mt-2 rounded-lg bg-white/70 px-3 py-2 text-xs text-slate-700 ring-1 ring-black/5">
                                <span class="font-bold">Rekomendasi tanggal lama:</span>
                                {{ $this->formatDate($numberMonitor['check_recommendation']['date']) }}
                                <div class="mt-1 font-normal">{{ $numberMonitor['check_recommendation']['note'] }}</div>
                            </div>
                        @endif
                    @else
                        Masukkan nomor urut untuk dicek.
                    @endif
                </div>
            </div>
        </section>

        <section class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-lg font-bold">Nomor Kosong yang Bisa Dipakai</h2>
                        <p class="text-sm text-slate-500">Daftar maksimal 40 nomor kosong pertama pada unit dan tahun terpilih.</p>
                    </div>
                    @if ($numberMonitor['missing_ranges'])
                        <div class="flex flex-wrap gap-2 sm:justify-end">
                            @foreach ($numberMonitor['missing_ranges'] as [$start, $end])
                                <span class="rounded-lg bg-slate-100 px-3 py-1.5 text-sm font-bold text-slate-700">
                                    {{ $this->formatSequence($start) }}{{ $start !== $end ? ' - '.$this->formatSequence($end) : '' }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if ($numberMonitor['missing_items'])
                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        @foreach ($numberMonitor['missing_items'] as $item)
                            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <div class="text-lg font-bold text-amber-900">{{ $this->formatSequence($item['sequence']) }}</div>
                                        <div class="mt-1 text-xs font-semibold text-amber-800">Rekomendasi tanggal lama: {{ $this->formatDate($item['recommended_date']) }}</div>
                                        <div class="mt-1 text-xs text-amber-700">{{ $item['recommendation_note'] }}</div>
                                    </div>
                                    <a href="{{ route('dashboard', ['create' => 'Keluar', 'unit' => $numberMonitor['unit_code'], 'number' => $item['suggested_number'], 'letter_date' => $item['recommended_date']]) }}"
                                       class="shrink-0 rounded-lg bg-teal-700 px-3 py-1.5 text-xs font-bold text-white hover:bg-teal-800">
                                        Pakai
                                    </a>
                                </div>
                                <div class="mt-3 break-all rounded-lg bg-white/70 px-3 py-2 text-xs font-semibold text-slate-700 ring-1 ring-black/5">{{ $item['suggested_number'] }}</div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="mt-4 text-sm text-slate-500">Belum ada celah nomor pada rentang yang terbaca.</p>
                @endif
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-bold">Histori Nomor Terpakai</h2>
                <p class="mt-1 text-sm text-slate-500">Nomor keluar terbaru dari filter saat ini.</p>
                <div class="mt-4 space-y-3">
                    @forelse ($numberMonitor['recent_used'] as $item)
                        <div class="border-l-4 border-teal-700 pl-3">
                            <div class="text-sm font-bold">{{ $this->formatSequence($item['sequence']) }} | {{ $item['number'] }}</div>
                            <div class="mt-1 text-xs font-semibold text-slate-500">Tanggal surat: {{ $this->formatDate($item['letter_date']) }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $item['subject'] }}</div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Belum ada nomor surat keluar untuk filter ini.</p>
                    @endforelse
                </div>
            </div>
        </section>
    </main>
</div>
