@props(['user', 'agency', 'pages', 'activePage', 'settingsTab', 'taskCount'])

<aside class="workspace-sidebar bg-slate-900 text-slate-100 lg:sticky lg:top-0 lg:h-screen" x-data="{ mobileOpen: false }">
    <div class="sidebar-brand">
        <x-app-logo class="h-11 w-11" />
        <div class="min-w-0 flex-1"><div class="font-semibold">{{ $agency['app_name'] }}</div><div class="text-sm text-slate-400">{{ $agency['name'] }}</div></div>
        <button type="button" class="sidebar-mobile-toggle lg:hidden" x-on:click="mobileOpen = !mobileOpen" x-bind:aria-expanded="mobileOpen" aria-controls="workspace-navigation" aria-label="Buka menu navigasi"><x-icon name="list-numbered" class="h-5 w-5" /></button>
    </div>

    <div id="workspace-navigation" class="sidebar-navigation" x-bind:class="{ 'mobile-open': mobileOpen }" x-on:click="if ($event.target.closest('.sidebar-link')) mobileOpen = false">
        <nav aria-label="Menu utama">
            <details class="sidebar-group" open>
                <summary><x-icon name="mail" class="h-4 w-4" /><span>Persuratan</span><span class="sidebar-chevron">⌄</span></summary>
                <div class="sidebar-group-items">
                    <x-workspace-menu-item label="Daftar Surat" icon="dashboard" action="navigate('surat')" :active="$activePage === 'surat'" />
                    <x-workspace-menu-item label="Pelacakan Surat" icon="route" action="navigate('pelacakan')" :active="$activePage === 'pelacakan'" />
                    @if ($user->isAdmin())
                        <x-workspace-menu-item label="Input Surat Masuk" icon="inbox" action="createLetter('Masuk')" />
                        <x-workspace-menu-item label="Input Surat Keluar" icon="outbox" action="createLetter('Keluar')" />
                    @endif
                    <x-workspace-menu-item label="Export CSV" icon="download" action="exportLetters" />
                </div>
            </details>

            <details class="sidebar-group" open>
                <summary><x-icon name="task" class="h-4 w-4" /><span>Tugas & Disposisi</span><span class="sidebar-chevron">⌄</span></summary>
                <div class="sidebar-group-items">
                    <x-workspace-menu-item label="Tugas Saya" icon="task" action="navigate('tugas')" :active="$activePage === 'tugas'" :badge="$taskCount" />
                    @if (in_array('disposisi', $pages, true))
                        <x-workspace-menu-item label="Disposisi Pimpinan" icon="send" action="navigate('disposisi')" :active="$activePage === 'disposisi'" />
                        <x-workspace-menu-item label="Disposisi Mandiri" icon="plus" action="createDisposition" />
                    @endif
                    @if (in_array('tindak-lanjut', $pages, true))
                        <x-workspace-menu-item label="Tindak Lanjut Disposisi" icon="users" action="navigate('tindak-lanjut')" :active="$activePage === 'tindak-lanjut'" />
                    @endif
                </div>
            </details>

            @if ($user->isAdmin())
                <details class="sidebar-group" @if (in_array($activePage, ['monitoring', 'sk'], true)) open @endif>
                    <summary><x-icon name="list-numbered" class="h-4 w-4" /><span>Penomoran Surat</span><span class="sidebar-chevron">⌄</span></summary>
                    <div class="sidebar-group-items">
                        <x-workspace-menu-item label="Monitoring Nomor" icon="list-numbered" action="navigate('monitoring')" :active="$activePage === 'monitoring'" />
                        <x-workspace-menu-item label="Penomoran SK" icon="gavel" action="navigate('sk')" :active="$activePage === 'sk'" />
                        <x-workspace-menu-item label="Tambah Nomor SK" icon="plus" action="createDecisionNumber" />
                    </div>
                </details>
                <details class="sidebar-group" @if ($activePage === 'pengaturan') open @endif>
                    <summary><x-icon name="settings" class="h-4 w-4" /><span>Pengaturan</span><span class="sidebar-chevron">⌄</span></summary>
                    <div class="sidebar-group-items">
                        @foreach (\App\Support\WorkspaceNavigation::settingsTabs() as $tab => $label)
                            <x-workspace-menu-item :label="$label" icon="settings" :action="'openSettings(\''.$tab.'\')'" :active="$activePage === 'pengaturan' && $settingsTab === $tab" />
                        @endforeach
                    </div>
                </details>
            @endif
        </nav>

        <div class="sidebar-account">
            <div class="flex items-center gap-3">
                <span class="sidebar-avatar">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                <div class="min-w-0"><div class="truncate text-xs font-semibold">{{ $user->name }}</div><div class="mt-1 text-[11px] text-slate-400">{{ $user->role }}</div></div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button type="submit" class="sidebar-link sidebar-logout"><x-icon name="logout" class="h-4 w-4" /><span class="button-label">Keluar Aplikasi</span><span class="button-progress" style="display:none"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Memproses…</span></button>
            </form>
        </div>
    </div>
</aside>
