<?php

use App\Models\AppSetting;
use App\Support\LetterNumbering;
use App\Support\TaskInbox;
use Illuminate\Support\Carbon;
use App\Livewire\Concerns\WorkspacePanel;
use Livewire\Component;

new class extends Component
{
    use WorkspacePanel;

    protected function workspacePage(): string
    {
        return 'monitoring';
    }

    public string $unitCode = 'Semua';

    public int $year;

    public string $checkSequence = '';

    public function mount(): void
    {
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

    public function monitorRows(): array
    {
        return collect($this->units())
            ->filter(fn (array $unit) => $this->unitCode === 'Semua' || $unit['code'] === $this->unitCode)
            ->map(fn (array $unit) => [
                'unit_name' => $unit['name'],
                ...LetterNumbering::monitor($unit['code'], $this->year, $this->checkSequence !== '' ? (int) $this->checkSequence : null),
            ])->values()->all();
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

<div class="{{ $embedded ? 'workspace-panel' : 'app-shell min-h-screen lg:grid lg:grid-cols-[280px_minmax(0,1fr)]' }}">
    @php
        $currentUser = auth()->user();
        $agencyProfile = AppSetting::agency();
        $taskCount = TaskInbox::countFor($currentUser);
        $units = $this->units();
        $monitorRows = $this->monitorRows();
        $createUnit = $unitCode === 'Semua' ? AppSetting::defaultLetterUnitCode() : $unitCode;
    @endphp

    @unless ($embedded)
    <aside class="bg-slate-900 px-5 py-5 text-slate-100 lg:sticky lg:top-0 lg:z-10 lg:h-screen lg:overflow-y-auto">
        <div class="flex items-center gap-3">
            <x-app-logo class="h-11 w-11" />
            <div>
                <div class="font-semibold">{{ $agencyProfile['app_name'] }}</div>
                <div class="text-sm text-slate-400">{{ $agencyProfile['name'] }}</div>
            </div>
        </div>

        <nav class="mt-8 grid gap-2">
            <a data-page-link href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5"><x-icon name="dashboard" class="h-4 w-4" />Dasbor</a>
            <a data-page-link href="{{ route('dashboard', ['view' => 'pelacakan']) }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5"><x-icon name="route" class="h-4 w-4" />Pelacakan Surat</a>
            <a data-page-link href="{{ route('dashboard', ['view' => 'tugas']) }}" class="flex items-center justify-between rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5">
                <span class="inline-flex items-center gap-2"><x-icon name="task" class="h-4 w-4" />Tugas Saya</span>
                @if ($taskCount > 0)
                    <span class="rounded-full bg-rose-500 px-2 py-0.5 text-xs font-bold text-white">{{ $taskCount }}</span>
                @endif
            </a>
            <a data-page-link href="{{ route('dashboard', ['view' => 'monitoring']) }}" class="inline-flex items-center gap-2 rounded-lg bg-white/10 px-3 py-2 text-sm font-semibold text-white"><x-icon name="list-numbered" class="h-4 w-4" />Monitoring Nomor</a>
            @if ($currentUser?->isAdmin() || $currentUser?->isLeader() || $currentUser?->isPersonalSecretary())
                <a data-page-link href="{{ route('dashboard', ['view' => 'disposisi']) }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5"><x-icon name="send" class="h-4 w-4" />Halaman Pimpinan</a>
            @endif
            @if ($currentUser?->isAdmin() || $currentUser?->isDepartmentHead())
                <a data-page-link href="{{ route('dashboard', ['view' => 'tindak-lanjut']) }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5"><x-icon name="users" class="h-4 w-4" />Halaman Kepala Bagian</a>
            @endif
            @if ($currentUser?->isAdmin())
                <a data-page-link href="{{ route('dashboard', ['view' => 'sk']) }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5"><x-icon name="gavel" class="h-4 w-4" />Penomoran SK</a>
                <a data-page-link href="{{ route('dashboard', ['view' => 'pengaturan']) }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5"><x-icon name="settings" class="h-4 w-4" />Setting</a>
            @endif
        </nav>

        <div class="mt-10 hidden border-t border-white/10 pt-5 lg:block">
            <div class="text-sm font-semibold">{{ $currentUser->name }}</div>
            <div class="text-xs text-slate-400">{{ $currentUser->role }}</div>
        </div>
    </aside>
    @endunless

    <section class="panel-main {{ $embedded ? 'workspace-panel-main' : 'min-w-0 px-4 py-6 sm:px-6 lg:px-8' }}">
        @unless ($embedded)
        <header class="sticky top-0 z-10 -mx-4 flex flex-col gap-4 border-b border-slate-200 bg-slate-100/95 px-4 py-4 backdrop-blur sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <p class="text-xs font-bold uppercase text-teal-700">Monitoring</p>
                <h1 class="mt-1 text-3xl font-bold tracking-normal text-slate-950">Monitoring Nomor Surat</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-600">Pantau nomor surat keluar yang sudah terpakai, nomor kosong, dan rekomendasi nomor yang bisa dipakai.</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <a data-page-link href="{{ route('dashboard', ['create' => 'Keluar', 'unit' => $createUnit]) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-teal-700 px-4 text-sm font-bold text-white shadow-sm hover:bg-teal-800">
                    <x-icon name="outbox" class="mr-2 h-4 w-4" />
                    Buat Surat Keluar
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-800 shadow-sm hover:border-rose-500"><span class="button-label"><x-icon name="logout" class="mr-2 h-4 w-4" />
                        Logout</span><span class="button-progress" style="display: none;"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>Memproses…</span></span></button>
                </form>
            </div>
        </header>
        @endunless

        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm" aria-label="Filter monitoring nomor">
            <x-number-monitor-filters :units="$units" :year="$year" unit-model="unitCode" year-model="year" check-model="checkSequence" />
        </section>

        <section class="mt-5" aria-label="Tabel monitoring nomor surat">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-base font-bold">Ringkasan per Unit Surat</h2>
                <p class="text-xs text-slate-500">Buka dropdown detail untuk melihat nomor kosong dan histori.</p>
            </div>
            <x-number-monitor-table :rows="$monitorRows" />
        </section>
    </section>
</div>
