<?php

use App\Models\AppSetting;
use App\Support\TaskInbox;
use App\Support\WorkspaceNavigation;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    #[Url(as: 'view', history: true, except: 'surat')]
    public string $activePage = 'surat';

    #[Url(as: 'tab', history: true, except: 'profil')]
    public string $settingsTab = 'profil';

    #[Locked]
    public string $initialAction = '';

    public function mount(): void
    {
        $this->authorizePage();
    }

    public function rendering(): void
    {
        $this->authorizePage();
    }

    public function updatedActivePage(string $page): void
    {
        WorkspaceNavigation::authorize($page);
        $this->initialAction = '';
    }

    public function updatedSettingsTab(string $tab): void
    {
        if ($this->activePage === 'pengaturan') {
            WorkspaceNavigation::authorize('pengaturan');
            abort_unless(array_key_exists($tab, WorkspaceNavigation::settingsTabs()), 404);
            $this->dispatch('workspace-settings-tab', tab: $tab)->to('settings');
        }
    }

    protected function authorizePage(): void
    {
        WorkspaceNavigation::authorize($this->activePage);
        if ($this->activePage === 'pengaturan') {
            abort_unless(array_key_exists($this->settingsTab, WorkspaceNavigation::settingsTabs()), 404);
        }
    }

    public function navigate(string $page): void
    {
        WorkspaceNavigation::authorize($page);
        $this->activePage = $page;
        $this->initialAction = '';
        if ($page !== 'pengaturan') {
            $this->settingsTab = 'profil';
        }
    }

    public function openSettings(string $tab): void
    {
        WorkspaceNavigation::authorize('pengaturan');
        abort_unless(array_key_exists($tab, WorkspaceNavigation::settingsTabs()), 404);
        $alreadyOpen = $this->activePage === 'pengaturan';
        $this->settingsTab = $tab;
        $this->navigate('pengaturan');
        if ($alreadyOpen) {
            $this->dispatch('workspace-settings-tab', tab: $tab)->to('settings');
        }
    }

    public function createLetter(string $type): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
        abort_unless(in_array($type, ['Masuk', 'Keluar'], true), 422);
        $alreadyOpen = $this->activePage === 'surat';
        $this->navigate('surat');
        if ($alreadyOpen) {
            $this->dispatch('workspace-create-letter', type: $type)->to('dashboard');
        } else {
            $this->initialAction = $type;
        }
    }

    public function createDisposition(): void
    {
        WorkspaceNavigation::authorize('disposisi');
        $alreadyOpen = $this->activePage === 'disposisi';
        $this->navigate('disposisi');
        if ($alreadyOpen) {
            $this->dispatch('workspace-create-disposition')->to('leadership-page');
        } else {
            $this->initialAction = 'disposisi';
        }
    }

    public function createDecisionNumber(): void
    {
        WorkspaceNavigation::authorize('sk');
        $alreadyOpen = $this->activePage === 'sk';
        $this->navigate('sk');
        if ($alreadyOpen) {
            $this->dispatch('workspace-create-sk')->to('sk-numbering-page');
        } else {
            $this->initialAction = 'sk';
        }
    }

    public function exportLetters()
    {
        WorkspaceNavigation::authorize('surat');
        if ($this->activePage === 'surat') {
            $this->dispatch('workspace-export-letters')->to('dashboard');

            return;
        }

        return redirect()->route('letters.export');
    }
};
?>

@php
    $currentUser = auth()->user();
    $agencyProfile = AppSetting::agency();
    $pages = WorkspaceNavigation::pages($currentUser);
    $taskCount = TaskInbox::countFor($currentUser);
    $titles = WorkspaceNavigation::titles();
@endphp

<div class="app-shell workspace-shell min-h-screen lg:grid lg:grid-cols-[280px_minmax(0,1fr)]">
    <x-workspace-sidebar :user="$currentUser" :agency="$agencyProfile" :pages="$pages" :active-page="$activePage" :settings-tab="$settingsTab" :task-count="$taskCount" />
    <main class="workspace-main min-w-0 px-4 sm:px-6 lg:px-8">
        <header class="workspace-header sticky top-0 z-10 -mx-4 border-b border-slate-200 bg-white px-4 py-4 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-teal-700">{{ now()->translatedFormat('l, j F Y') }} · {{ $currentUser->role }}</p>
                    <h1 class="mt-1 text-2xl font-bold text-slate-950">{{ $titles[$activePage] }}{{ $activePage === 'surat' ? ' '.$agencyProfile['short_name'] : '' }}</h1>
                    <p class="mt-1 text-xs text-slate-500">{{ $agencyProfile['name'] }}{{ $activePage === 'pengaturan' ? ' · '.WorkspaceNavigation::settingsTabs()[$settingsTab] : '' }}</p>
                </div>
                <span class="workspace-role-badge"><x-icon name="user" class="h-4 w-4" />{{ $currentUser->name }}</span>
            </div>
        </header>

        @switch($activePage)
            @case('surat')
                <livewire:dashboard :embedded="true" :initial-action="$initialAction" :key="'workspace-surat'" />
                @break
            @case('tugas')
                <livewire:my-tasks-page :embedded="true" :key="'workspace-tugas'" />
                @break
            @case('pelacakan')
                <livewire:letter-tracking-page :embedded="true" :key="'workspace-pelacakan'" />
                @break
            @case('disposisi')
                <livewire:leadership-page :embedded="true" :initial-action="$initialAction" :key="'workspace-disposisi'" />
                @break
            @case('tindak-lanjut')
                <livewire:department-head-page :embedded="true" :key="'workspace-tindak-lanjut'" />
                @break
            @case('monitoring')
                <livewire:number-monitor-page :embedded="true" :key="'workspace-monitoring'" />
                @break
            @case('sk')
                <livewire:sk-numbering-page :embedded="true" :initial-action="$initialAction" :key="'workspace-sk'" />
                @break
            @case('pengaturan')
                <livewire:settings :embedded="true" :initial-tab="$settingsTab" :key="'workspace-pengaturan'" />
                @break
        @endswitch
    </main>
</div>
