<?php

use App\Models\AppSetting;
use App\Support\TaskInbox;
use App\Livewire\Concerns\WorkspacePanel;
use Livewire\Component;

new class extends Component
{
    use WorkspacePanel;

    protected function workspacePage(): string
    {
        return 'tugas';
    }

    public function taskGroups(): array
    {
        return TaskInbox::for(auth()->user());
    }

    public function taskCount(): int
    {
        return TaskInbox::countFor(auth()->user());
    }

};
?>

<div class="{{ $embedded ? 'workspace-panel' : 'app-shell min-h-screen lg:grid lg:grid-cols-[280px_minmax(0,1fr)]' }}">
    @php
        $currentUser = auth()->user();
        $agencyProfile = AppSetting::agency();
        $taskGroups = $this->taskGroups();
        $taskCount = $this->taskCount();
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
            <a data-page-link href="{{ route('dashboard', ['view' => 'tugas']) }}" class="flex items-center justify-between rounded-lg bg-white/10 px-3 py-2 text-sm font-semibold text-white">
                <span class="inline-flex items-center gap-2"><x-icon name="task" class="h-4 w-4" />Tugas Saya</span>
                @if ($taskCount > 0)
                    <span class="rounded-full bg-rose-500 px-2 py-0.5 text-xs font-bold text-white">{{ $taskCount }}</span>
                @endif
            </a>
            @if ($currentUser?->isAdmin() || $currentUser?->isLeader() || $currentUser?->isPersonalSecretary())
                <a data-page-link href="{{ route('dashboard', ['view' => 'disposisi']) }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5"><x-icon name="send" class="h-4 w-4" />Halaman Pimpinan</a>
            @endif
            <a data-page-link href="{{ route('dashboard', ['view' => 'pelacakan']) }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5"><x-icon name="route" class="h-4 w-4" />Pelacakan Surat</a>
            @if ($currentUser?->isAdmin() || $currentUser?->isDepartmentHead())
                <a data-page-link href="{{ route('dashboard', ['view' => 'tindak-lanjut']) }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5"><x-icon name="users" class="h-4 w-4" />Halaman Kepala Bagian</a>
            @endif
            @if ($currentUser?->isAdmin())
                <a data-page-link href="{{ route('dashboard', ['view' => 'monitoring']) }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5"><x-icon name="list-numbered" class="h-4 w-4" />Monitoring Nomor</a>
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
                <p class="text-xs font-bold uppercase text-teal-700">{{ $currentUser->role }}</p>
                <h1 class="mt-1 text-3xl font-bold tracking-normal text-slate-950">Inbox / Tugas Saya</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-600">Daftar pekerjaan yang perlu ditindaklanjuti sesuai role dan nama pengguna.</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-outline-secondary inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-800 shadow-sm hover:border-rose-500"><span class="button-label"><x-icon name="logout" class="mr-2 h-4 w-4" />
                    Logout</span><span class="button-progress" style="display: none;"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>Memproses…</span></span></button>
            </form>
        </header>
        @endunless

        <section class="mt-6 grid gap-3 md:grid-cols-4">
            <div class="rounded-lg border border-teal-200 bg-teal-50 p-5 shadow-sm">
                <div class="text-sm font-bold text-teal-700">Total Tugas</div>
                <div class="mt-2 text-3xl font-bold">{{ $taskCount }}</div>
            </div>
            <div class="rounded-lg border border-rose-200 bg-rose-50 p-5 shadow-sm">
                <div class="text-sm font-bold text-rose-700">Menunggu Disposisi</div>
                <div class="mt-2 text-3xl font-bold">{{ $taskGroups['incoming']->count() }}</div>
            </div>
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-5 shadow-sm">
                <div class="text-sm font-bold text-amber-700">Disposisi Saya</div>
                <div class="mt-2 text-3xl font-bold">{{ $taskGroups['dispositions']->count() }}</div>
            </div>
            <div class="rounded-lg border border-sky-200 bg-sky-50 p-5 shadow-sm">
                <div class="text-sm font-bold text-sky-700">Persetujuan</div>
                <div class="mt-2 text-3xl font-bold">{{ $taskGroups['approvals']->count() }}</div>
            </div>
        </section>

        <section class="mt-5 grid gap-5 xl:grid-cols-2">
            @if (in_array('disposisi', \App\Support\WorkspaceNavigation::pages($currentUser), true))
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
                    <div>
                        <h2 class="text-lg font-bold">Surat Menunggu Disposisi</h2>
                        <p class="text-sm text-slate-500">Surat masuk baru yang perlu diputuskan pimpinan.</p>
                    </div>
                    <a data-page-link href="{{ route('dashboard', ['view' => 'disposisi']) }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-bold hover:border-teal-600">Buka</a>
                </div>
                <div class="mt-4 space-y-3">
                    @forelse ($taskGroups['incoming'] as $letter)
                        <div class="rounded-lg border border-slate-200 p-3">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <div class="font-bold">{{ $letter->number }}</div>
                                    <div class="mt-1 text-sm text-slate-600">{{ $letter->subject }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $letter->external_party }} | {{ $letter->received_date?->translatedFormat('d M Y') ?: $letter->letter_date?->translatedFormat('d M Y') }}</div>
                                </div>
                                <span class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-bold text-rose-700 ring-1 ring-rose-200">{{ $letter->status }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Tidak ada surat yang menunggu disposisi.</p>
                    @endforelse
                </div>
            </div>

            @endif

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
                    <div>
                        <h2 class="text-lg font-bold">Disposisi untuk Saya</h2>
                        <p class="text-sm text-slate-500">Instruksi yang ditujukan ke nama pengguna saat ini.</p>
                    </div>
                    <a data-page-link href="{{ in_array('tindak-lanjut', \App\Support\WorkspaceNavigation::pages($currentUser), true) ? route('dashboard', ['view' => 'tindak-lanjut']) : route('dashboard') }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-bold hover:border-teal-600">Buka</a>
                </div>
                <div class="mt-4 space-y-3">
                    @forelse ($taskGroups['dispositions'] as $disposition)
                        <div class="rounded-lg border border-slate-200 p-3">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <div class="font-bold">{{ $disposition->letter?->number ?: 'Disposisi Mandiri' }}</div>
                                    <div class="mt-1 text-sm text-slate-600">{{ $disposition->instruction }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $disposition->sender_name }} | {{ $disposition->created_at->translatedFormat('d M Y H:i') }}</div>
                                </div>
                                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-700 ring-1 ring-amber-200">{{ $disposition->status }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Tidak ada disposisi aktif untuk Anda.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
                    <div>
                        <h2 class="text-lg font-bold">Paraf / Persetujuan</h2>
                        <p class="text-sm text-slate-500">Tahapan tanda tangan elektronik yang menunggu role Anda.</p>
                    </div>
                    <a data-page-link href="{{ route('dashboard') }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-bold hover:border-teal-600">Buka</a>
                </div>
                <div class="mt-4 space-y-3">
                    @forelse ($taskGroups['approvals'] as $approval)
                        <div class="rounded-lg border border-slate-200 p-3">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <div class="font-bold">{{ $approval->step }}</div>
                                    <div class="mt-1 text-sm text-slate-600">{{ $approval->letter?->number }} | {{ $approval->letter?->subject }}</div>
                                    <div class="mt-1 text-xs text-slate-500">Target: {{ $approval->target_role }}</div>
                                </div>
                                <span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-bold text-sky-700 ring-1 ring-sky-200">{{ $approval->status }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Tidak ada paraf atau persetujuan yang menunggu.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
                    <div>
                        <h2 class="text-lg font-bold">Tenggat Tindak Lanjut</h2>
                        <p class="text-sm text-slate-500">Surat dengan batas waktu dekat atau sudah lewat.</p>
                    </div>
                    <a data-page-link href="{{ route('dashboard') }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-bold hover:border-teal-600">Buka</a>
                </div>
                <div class="mt-4 space-y-3">
                    @forelse ($taskGroups['deadlines'] as $letter)
                        <div class="rounded-lg border border-slate-200 p-3">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <div class="font-bold">{{ $letter->number }}</div>
                                    <div class="mt-1 text-sm text-slate-600">{{ $letter->subject }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $letter->external_party }}</div>
                                </div>
                                <span class="rounded-full {{ $letter->due_date?->isPast() ? 'bg-rose-100 text-rose-700 ring-rose-200' : 'bg-amber-100 text-amber-700 ring-amber-200' }} px-2.5 py-1 text-xs font-bold ring-1">
                                    {{ $letter->due_date?->translatedFormat('d M Y') }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Tidak ada tenggat dekat untuk role Anda.</p>
                    @endforelse
                </div>
            </div>
        </section>
    </section>
</div>
