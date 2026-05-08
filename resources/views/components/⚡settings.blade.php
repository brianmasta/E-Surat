<?php

use App\Models\AppSetting;
use App\Models\ActivityLog;
use App\Models\DispositionRecipient;
use App\Models\Letter;
use App\Models\User;
use App\Support\TaskInbox;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public array $agency = [];

    public array $numbering = [];

    public array $workflow = [];

    public array $units = [];

    public array $roles = [];

    public string $newRoleName = '';

    public string $newRoleDescription = '';

    public ?int $editingRecipientId = null;

    public string $recipientName = '';

    public string $recipientPosition = '';

    public string $recipientUnit = '';

    public bool $recipientIsActive = true;

    public ?int $editingUserId = null;

    public string $userName = '';

    public string $userEmail = '';

    public string $userRole = 'Staf Sekretariat';

    public string $userPassword = '';

    public bool $userIsActive = true;

    public string $activeSettingsTab = 'profil';

    public string $numberMonitorUnit = 'SET-MRP';

    public int $numberMonitorYear;

    public string $numberMonitorCheck = '';

    public ?int $editingUnitIndex = null;

    public string $unitCode = '';

    public string $unitName = '';

    public string $unitDescription = '';

    public bool $unitIsDefault = false;

    public function mount(): void
    {
        $this->numberMonitorYear = (int) now()->format('Y');
        $this->numberMonitorUnit = AppSetting::defaultLetterUnitCode();

        $this->agency = AppSetting::agency();

        $this->numbering = AppSetting::getValue('letter_numbering', [
            'prefix' => '800',
            'unit_code' => AppSetting::defaultLetterUnitCode(),
            'separator' => '/',
            'include_month' => true,
            'include_year' => true,
            'next_sequence' => 1,
        ]);

        $this->units = AppSetting::letterUnits();

        $this->workflow = AppSetting::getValue('workflow', [
            'max_upload_mb' => 5,
            'allowed_files' => 'PDF, JPG, JPEG, PNG',
            'auto_archive_done' => true,
            'notify_disposition' => true,
        ]);

        $this->roles = AppSetting::getValue('roles', []);
    }

    public function settingsTabs(): array
    {
        return [
            'profil' => 'Profil Instansi',
            'nomor' => 'Nomor & Alur',
            'unit' => 'Unit Surat',
            'monitoring-nomor' => 'Monitoring Nomor',
            'pengguna' => 'Pengguna',
            'disposisi' => 'Disposisi',
            'audit' => 'Audit & Peran',
        ];
    }

    public function setSettingsTab(string $tab): void
    {
        if (! array_key_exists($tab, $this->settingsTabs())) {
            return;
        }

        $this->activeSettingsTab = $tab;
    }

    public function saveAgency(): void
    {
        $this->validate([
            'agency.name' => ['required', 'string', 'max:255'],
            'agency.app_name' => ['required', 'string', 'max:255'],
            'agency.short_name' => ['required', 'string', 'max:20'],
            'agency.unit' => ['required', 'string', 'max:255'],
            'agency.leader_title' => ['required', 'string', 'max:255'],
            'agency.city' => ['required', 'string', 'max:100'],
            'agency.address' => ['nullable', 'string', 'max:500'],
            'agency.email' => ['required', 'email', 'max:255'],
            'agency.phone' => ['nullable', 'string', 'max:50'],
        ]);

        AppSetting::putValue('agency', $this->agency);
        ActivityLog::record('setting.updated', 'Profil instansi diperbarui.');
        $this->dispatch('notify', message: 'Profil instansi berhasil disimpan.');
    }

    public function saveNumbering(): void
    {
        $this->validate([
            'numbering.prefix' => ['required', 'string', 'max:20'],
            'numbering.unit_code' => ['required', Rule::in($this->unitCodes())],
            'numbering.separator' => ['required', 'string', 'max:3'],
            'numbering.next_sequence' => ['required', 'integer', 'min:1'],
        ]);

        $this->numbering['include_month'] = (bool) ($this->numbering['include_month'] ?? false);
        $this->numbering['include_year'] = (bool) ($this->numbering['include_year'] ?? false);

        AppSetting::putValue('letter_numbering', $this->numbering);
        ActivityLog::record('setting.updated', 'Format nomor surat keluar diperbarui.');
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
        ActivityLog::record('setting.updated', 'Pengaturan alur kerja diperbarui.');
        $this->dispatch('notify', message: 'Pengaturan alur kerja berhasil disimpan.');
    }

    public function unitCodes(): array
    {
        return collect($this->units)->pluck('code')->filter()->values()->all();
    }

    public function saveUnit(): void
    {
        $this->unitCode = strtoupper(trim($this->unitCode));
        $oldCode = $this->editingUnitIndex !== null ? ($this->units[$this->editingUnitIndex]['code'] ?? null) : null;

        $this->validate([
            'unitCode' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9-]+$/'],
            'unitName' => ['required', 'string', 'max:100'],
            'unitDescription' => ['nullable', 'string', 'max:255'],
            'unitIsDefault' => ['boolean'],
        ]);

        $duplicate = collect($this->units)
            ->filter(fn (array $unit, int $index) => $index !== $this->editingUnitIndex)
            ->contains(fn (array $unit) => strtoupper($unit['code']) === $this->unitCode);

        if ($duplicate) {
            $this->addError('unitCode', 'Kode unit sudah digunakan.');

            return;
        }

        if ($oldCode && $oldCode !== $this->unitCode && Letter::where('unit_code', $oldCode)->exists()) {
            $this->addError('unitCode', 'Kode unit sudah dipakai surat sehingga tidak bisa diganti.');

            return;
        }

        $unit = [
            'code' => $this->unitCode,
            'name' => $this->unitName,
            'description' => $this->unitDescription,
            'is_default' => $this->unitIsDefault,
        ];

        if ($this->unitIsDefault) {
            $this->units = collect($this->units)
                ->map(fn (array $item) => [...$item, 'is_default' => false])
                ->values()
                ->all();
            $this->numbering['unit_code'] = $this->unitCode;
            $this->numberMonitorUnit = $this->unitCode;
        }

        if ($this->editingUnitIndex !== null) {
            $this->units[$this->editingUnitIndex] = $unit;
        } else {
            $this->units[] = $unit;
        }

        if (! collect($this->units)->contains('is_default', true)) {
            $this->units[0]['is_default'] = true;
        }

        AppSetting::putValue('letter_units', array_values($this->units));
        AppSetting::putValue('letter_numbering', $this->numbering);
        ActivityLog::record($this->editingUnitIndex !== null ? 'unit.updated' : 'unit.created', 'Unit surat disimpan: '.$this->unitCode);
        $this->resetUnitForm();
        $this->dispatch('notify', message: 'Unit surat berhasil disimpan.');
    }

    public function editUnit(int $index): void
    {
        if (! isset($this->units[$index])) {
            return;
        }

        $unit = $this->units[$index];
        $this->editingUnitIndex = $index;
        $this->unitCode = $unit['code'];
        $this->unitName = $unit['name'];
        $this->unitDescription = $unit['description'] ?? '';
        $this->unitIsDefault = (bool) ($unit['is_default'] ?? false);
    }

    public function deleteUnit(int $index): void
    {
        if (! isset($this->units[$index])) {
            return;
        }

        $unit = $this->units[$index];

        if (Letter::where('unit_code', $unit['code'])->exists()) {
            $this->dispatch('notify', message: 'Unit tidak bisa dihapus karena sudah dipakai surat.');

            return;
        }

        unset($this->units[$index]);
        $this->units = array_values($this->units);

        if ($this->units === []) {
            $this->units = AppSetting::defaultLetterUnits();
        }

        if (! collect($this->units)->contains('is_default', true)) {
            $this->units[0]['is_default'] = true;
        }

        $defaultCode = collect($this->units)->firstWhere('is_default', true)['code'];
        if (! in_array($this->numbering['unit_code'] ?? '', $this->unitCodes(), true)) {
            $this->numbering['unit_code'] = $defaultCode;
        }
        if (! in_array($this->numberMonitorUnit, $this->unitCodes(), true)) {
            $this->numberMonitorUnit = $defaultCode;
        }

        AppSetting::putValue('letter_units', $this->units);
        AppSetting::putValue('letter_numbering', $this->numbering);

        if ($this->editingUnitIndex === $index) {
            $this->resetUnitForm();
        }

        ActivityLog::record('unit.deleted', 'Unit surat dihapus: '.$unit['code']);
        $this->dispatch('notify', message: 'Unit surat dihapus.');
    }

    public function resetUnitForm(): void
    {
        $this->editingUnitIndex = null;
        $this->unitCode = '';
        $this->unitName = '';
        $this->unitDescription = '';
        $this->unitIsDefault = false;
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
        ActivityLog::record('role.created', 'Peran pengguna '.$this->roles[array_key_last($this->roles)]['name'].' ditambahkan.');
        $this->dispatch('notify', message: 'Peran pengguna berhasil ditambahkan.');
    }

    public function removeRole(int $index): void
    {
        unset($this->roles[$index]);
        $this->roles = array_values($this->roles);

        AppSetting::putValue('roles', $this->roles);
        ActivityLog::record('role.deleted', 'Peran pengguna dihapus.');
        $this->dispatch('notify', message: 'Peran pengguna dihapus.');
    }

    public function userRoles(): array
    {
        return collect([
            'Admin Sekretariat',
            'Pimpinan MRP',
            'Kepala Bagian',
            'Staf Sekretariat',
        ])
            ->merge(collect($this->roles)->pluck('name'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function users()
    {
        return User::query()
            ->orderByDesc('is_active')
            ->orderBy('role')
            ->orderBy('name')
            ->get();
    }

    public function recentActivityLogs()
    {
        return ActivityLog::query()
            ->with('user')
            ->latest()
            ->limit(15)
            ->get();
    }

    public function saveUser(): void
    {
        $rules = [
            'userName' => ['required', 'string', 'max:255'],
            'userEmail' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingUserId)],
            'userRole' => ['required', 'string', 'max:100'],
            'userPassword' => [$this->editingUserId ? 'nullable' : 'required', 'string', 'min:8', 'max:255'],
            'userIsActive' => ['boolean'],
        ];

        $validated = $this->validate($rules);

        $data = [
            'name' => $validated['userName'],
            'email' => $validated['userEmail'],
            'role' => $validated['userRole'],
            'is_active' => $validated['userIsActive'],
        ];

        if ($validated['userPassword'] !== '') {
            $data['password'] = Hash::make($validated['userPassword']);
        }

        $user = $this->editingUserId
            ? tap(User::findOrFail($this->editingUserId))->update($data)
            : User::create($data);

        ActivityLog::record(
            $this->editingUserId ? 'user.updated' : 'user.created',
            ($this->editingUserId ? 'Pengguna diperbarui: ' : 'Pengguna dibuat: ').$user->email,
            $user,
            ['role' => $user->role, 'is_active' => $user->is_active],
        );

        $this->resetUserForm();
        $this->dispatch('notify', message: 'Pengguna berhasil disimpan.');
    }

    public function editUser(int $userId): void
    {
        $user = User::findOrFail($userId);

        $this->editingUserId = $user->id;
        $this->userName = $user->name;
        $this->userEmail = $user->email;
        $this->userRole = $user->role;
        $this->userPassword = '';
        $this->userIsActive = $user->is_active;
    }

    public function toggleUser(int $userId): void
    {
        if (auth()->id() === $userId) {
            $this->dispatch('notify', message: 'Akun yang sedang dipakai tidak bisa dinonaktifkan.');

            return;
        }

        $user = User::findOrFail($userId);
        $user->update(['is_active' => ! $user->is_active]);

        ActivityLog::record(
            'user.status_updated',
            'Status pengguna '.$user->email.' diperbarui.',
            $user,
            ['is_active' => $user->is_active],
        );

        $this->dispatch('notify', message: 'Status pengguna diperbarui.');
    }

    public function deleteUser(int $userId): void
    {
        if (auth()->id() === $userId) {
            $this->dispatch('notify', message: 'Akun yang sedang dipakai tidak bisa dihapus.');

            return;
        }

        $user = User::findOrFail($userId);
        $email = $user->email;
        $user->delete();

        if ($this->editingUserId === $userId) {
            $this->resetUserForm();
        }

        ActivityLog::record('user.deleted', 'Pengguna dihapus: '.$email);
        $this->dispatch('notify', message: 'Pengguna dihapus.');
    }

    public function resetUserForm(): void
    {
        $this->editingUserId = null;
        $this->userName = '';
        $this->userEmail = '';
        $this->userRole = 'Staf Sekretariat';
        $this->userPassword = '';
        $this->userIsActive = true;
    }

    public function dispositionRecipients()
    {
        return DispositionRecipient::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
    }

    public function saveRecipient(): void
    {
        $this->validate([
            'recipientName' => ['required', 'string', 'max:255'],
            'recipientPosition' => ['nullable', 'string', 'max:255'],
            'recipientUnit' => ['nullable', 'string', 'max:255'],
            'recipientIsActive' => ['boolean'],
        ]);

        $data = [
            'name' => $this->recipientName,
            'position' => $this->recipientPosition ?: null,
            'unit' => $this->recipientUnit ?: null,
            'is_active' => $this->recipientIsActive,
        ];

        if ($this->editingRecipientId) {
            $recipient = DispositionRecipient::findOrFail($this->editingRecipientId);
            $recipient->update($data);
        } else {
            $recipient = DispositionRecipient::create($data);
        }

        ActivityLog::record(
            $this->editingRecipientId ? 'recipient.updated' : 'recipient.created',
            'Penerima disposisi disimpan: '.$recipient->name,
            $recipient,
        );

        $this->resetRecipientForm();
        $this->dispatch('notify', message: 'Penerima disposisi berhasil disimpan.');
    }

    public function editRecipient(int $recipientId): void
    {
        $recipient = DispositionRecipient::findOrFail($recipientId);

        $this->editingRecipientId = $recipient->id;
        $this->recipientName = $recipient->name;
        $this->recipientPosition = $recipient->position ?? '';
        $this->recipientUnit = $recipient->unit ?? '';
        $this->recipientIsActive = $recipient->is_active;
    }

    public function deleteRecipient(int $recipientId): void
    {
        DispositionRecipient::whereKey($recipientId)->delete();

        if ($this->editingRecipientId === $recipientId) {
            $this->resetRecipientForm();
        }

        ActivityLog::record('recipient.deleted', 'Penerima disposisi dihapus.');
        $this->dispatch('notify', message: 'Penerima disposisi dihapus.');
    }

    public function toggleRecipient(int $recipientId): void
    {
        $recipient = DispositionRecipient::findOrFail($recipientId);
        $recipient->update(['is_active' => ! $recipient->is_active]);

        ActivityLog::record(
            'recipient.status_updated',
            'Status penerima disposisi '.$recipient->name.' diperbarui.',
            $recipient,
            ['is_active' => $recipient->is_active],
        );

        $this->dispatch('notify', message: 'Status penerima disposisi diperbarui.');
    }

    public function resetRecipientForm(): void
    {
        $this->editingRecipientId = null;
        $this->recipientName = '';
        $this->recipientPosition = '';
        $this->recipientUnit = '';
        $this->recipientIsActive = true;
    }

    public function sampleNumber(): string
    {
        $separator = $this->numbering['separator'] ?? '/';
        $parts = [
            $this->numbering['prefix'] ?? '800',
            str_pad((string) ($this->numbering['next_sequence'] ?? 1), 3, '0', STR_PAD_LEFT),
            $this->numbering['unit_code'] ?? AppSetting::defaultLetterUnitCode(),
        ];

        if ($this->numbering['include_month'] ?? true) {
            $parts[] = now()->format('m');
        }

        if ($this->numbering['include_year'] ?? true) {
            $parts[] = now()->format('Y');
        }

        return implode($separator, $parts);
    }

    public function numberMonitor(): array
    {
        $nextSequence = max(1, (int) ($this->numbering['next_sequence'] ?? 1));
        $used = Letter::query()
            ->where('type', 'Keluar')
            ->where('unit_code', $this->numberMonitorUnit)
            ->whereYear('letter_date', $this->numberMonitorYear)
            ->get(['id', 'number', 'subject', 'letter_date'])
            ->map(fn (Letter $letter) => [
                'sequence' => $this->extractSequenceFromNumber($letter->number),
                'number' => $letter->number,
                'subject' => $letter->subject,
                'letter_date' => $letter->letter_date,
            ])
            ->filter(fn (array $item) => $item['sequence'] !== null)
            ->sortBy('sequence')
            ->values();

        $usedSequences = $used->pluck('sequence')->unique()->sort()->values()->all();
        $maxSequence = max($nextSequence - 1, $usedSequences ? max($usedSequences) : 0);
        $missing = collect(range(1, max(1, $maxSequence)))
            ->reject(fn (int $sequence) => in_array($sequence, $usedSequences, true))
            ->values()
            ->all();

        $check = $this->numberMonitorCheck !== ''
            ? max(1, (int) $this->numberMonitorCheck)
            : null;

        return [
            'used_count' => count($usedSequences),
            'missing_count' => count($missing),
            'next_sequence' => $nextSequence,
            'missing_sequences' => array_slice($missing, 0, 40),
            'missing_ranges' => $this->sequenceRanges($missing),
            'recent_used' => $used->reverse()->take(8)->values()->all(),
            'check_sequence' => $check,
            'check_is_available' => $check ? ! in_array($check, $usedSequences, true) : null,
        ];
    }

    public function extractSequenceFromNumber(?string $number): ?int
    {
        if (! $number) {
            return null;
        }

        $separator = (string) ($this->numbering['separator'] ?? '/');
        $parts = $separator !== '' ? explode($separator, $number) : [];

        return isset($parts[1]) && ctype_digit($parts[1]) ? (int) $parts[1] : null;
    }

    public function sequenceRanges(array $sequences): array
    {
        $ranges = [];
        $start = null;
        $previous = null;

        foreach ($sequences as $sequence) {
            if ($start === null) {
                $start = $previous = $sequence;

                continue;
            }

            if ($sequence === $previous + 1) {
                $previous = $sequence;

                continue;
            }

            $ranges[] = [$start, $previous];
            $start = $previous = $sequence;
        }

        if ($start !== null) {
            $ranges[] = [$start, $previous];
        }

        return array_slice($ranges, 0, 12);
    }
};
?>

<div x-data="{ toast: '', showToast: false }"
     x-on:notify.window="toast = $event.detail.message; showToast = true; setTimeout(() => showToast = false, 2600)"
     class="min-h-screen lg:grid lg:grid-cols-[280px_minmax(0,1fr)]">
    @php
        $currentUser = auth()->user();
        $dispositionRecipients = $this->dispositionRecipients();
        $users = $this->users();
        $userRoles = $this->userRoles();
        $activityLogs = $this->recentActivityLogs();
        $settingsTabs = $this->settingsTabs();
        $numberMonitor = $this->numberMonitor();
        $agencyProfile = AppSetting::agency();
        $taskCount = TaskInbox::countFor($currentUser);
    @endphp

    <aside class="bg-slate-900 px-5 py-5 text-slate-100 lg:sticky lg:top-0 lg:z-10 lg:h-screen lg:overflow-y-auto">
        <div class="flex items-center gap-3">
            <div class="grid h-11 w-11 place-items-center rounded-lg bg-teal-100 font-bold text-teal-800">{{ $agencyProfile['short_name'] }}</div>
            <div>
                <div class="font-semibold">{{ $agencyProfile['app_name'] }}</div>
                <div class="text-sm text-slate-400">{{ $agencyProfile['name'] }}</div>
            </div>
        </div>

        <nav class="mt-8 flex gap-2 overflow-x-auto lg:grid lg:overflow-visible">
            <a href="{{ route('dashboard') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5">Dasbor</a>
            <a href="{{ route('my-tasks') }}" class="flex items-center justify-between rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5">
                <span>Tugas Saya</span>
                @if ($taskCount > 0)
                    <span class="rounded-full bg-rose-500 px-2 py-0.5 text-xs font-bold text-white">{{ $taskCount }}</span>
                @endif
            </a>
            @if ($currentUser?->isAdmin() || $currentUser?->isLeader())
                <a href="{{ route('leadership') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5">Halaman Pimpinan</a>
            @endif
            @if ($currentUser?->isAdmin() || $currentUser?->isDepartmentHead())
                <a href="{{ route('department-head') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5">Halaman Kepala Bagian</a>
            @endif

            @if ($currentUser?->isAdmin() || $currentUser?->isLeader())
                <a href="{{ route('dashboard') }}" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 transition hover:bg-white/5">Disposisi</a>
            @endif
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
        <header class="sticky top-0 z-10 -mx-4 flex flex-col gap-4 border-b border-slate-200 bg-slate-100/95 px-4 py-4 backdrop-blur sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <p class="text-xs font-bold uppercase text-teal-700">Konfigurasi Aplikasi</p>
                <h1 class="mt-1 text-3xl font-bold tracking-normal text-slate-950">Setting {{ $agencyProfile['app_name'] }}</h1>
                <p class="mt-2 max-w-2xl text-sm text-slate-600">Atur identitas instansi, format nomor surat, batas dokumen, notifikasi, dan peran pengguna {{ $agencyProfile['name'] }}.</p>
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

        <nav class="mt-6 overflow-x-auto rounded-lg border border-slate-200 bg-white p-1 shadow-sm">
            <div class="flex min-w-max gap-1">
                @foreach ($settingsTabs as $tabKey => $tabLabel)
                    <button type="button"
                            wire:click="setSettingsTab('{{ $tabKey }}')"
                            class="rounded-md px-4 py-2 text-sm font-bold transition {{ $activeSettingsTab === $tabKey ? 'bg-teal-700 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950' }}">
                        {{ $tabLabel }}
                    </button>
                @endforeach
            </div>
        </nav>

        <section class="mt-5 grid items-start gap-5 {{ in_array($activeSettingsTab, ['profil', 'nomor'], true) ? 'xl:grid-cols-[minmax(0,1fr)_360px]' : '' }}">
            <div class="min-w-0 space-y-5">
                @if ($activeSettingsTab === 'profil')
                <form wire:submit="saveAgency" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="border-b border-slate-200 pb-4">
                        <h2 class="text-lg font-bold">Profil Instansi</h2>
                        <p class="text-sm text-slate-500">Data ini menjadi identitas utama pada sistem persuratan.</p>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Nama Aplikasi
                            <input wire:model="agency.app_name" type="text" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950" placeholder="Contoh: E-Surat Setda">
                            @error('agency.app_name') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Nama Singkat / Inisial
                            <input wire:model="agency.short_name" type="text" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950" placeholder="Contoh: SETDA">
                            @error('agency.short_name') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
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
                            Jabatan Pimpinan / Penandatangan
                            <input wire:model="agency.leader_title" type="text" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950" placeholder="Contoh: Sekretaris Daerah">
                            @error('agency.leader_title') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Tempat Surat
                            <input wire:model="agency.city" type="text" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950" placeholder="Contoh: Nabire">
                            @error('agency.city') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
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
                @endif

                @if ($activeSettingsTab === 'nomor')
                <form wire:submit="saveNumbering" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-4 border-b border-slate-200 pb-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <h2 class="text-lg font-bold">Format Nomor Surat Keluar</h2>
                            <p class="text-sm text-slate-500">Atur pola nomor otomatis dan nomor urut berikutnya.</p>
                        </div>
                        <div class="rounded-lg border border-teal-100 bg-teal-50 px-4 py-3">
                            <div class="text-xs font-bold uppercase text-teal-700">Preview Nomor</div>
                            <div class="mt-1 break-all text-lg font-bold text-slate-950">{{ $this->sampleNumber() }}</div>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-5 2xl:grid-cols-[minmax(0,1fr)_280px]">
                        <div class="grid min-w-0 gap-4 xl:grid-cols-2">
                            <label class="grid gap-1 text-sm font-bold text-slate-600">
                                Kode Awal
                                <input wire:model.live="numbering.prefix" type="text" class="min-h-11 min-w-0 rounded-lg border border-slate-200 px-3 text-slate-950">
                                <span class="text-xs font-normal text-slate-500">Contoh: 800 atau kode klasifikasi bawaan.</span>
                                @error('numbering.prefix') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>
                            <label class="grid gap-1 text-sm font-bold text-slate-600">
                                Kode Unit
                                <select wire:model.live="numbering.unit_code" class="min-h-11 min-w-0 rounded-lg border border-slate-200 px-3 text-slate-950">
                                    @foreach ($units as $unit)
                                        <option value="{{ $unit['code'] }}">{{ $unit['code'] }} - {{ $unit['name'] }}</option>
                                    @endforeach
                                </select>
                                <span class="text-xs font-normal text-slate-500">Unit default saat membuat nomor.</span>
                                @error('numbering.unit_code') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>
                            <label class="grid gap-1 text-sm font-bold text-slate-600">
                                Pemisah
                                <input wire:model.live="numbering.separator" type="text" class="min-h-11 min-w-0 rounded-lg border border-slate-200 px-3 text-slate-950">
                                <span class="text-xs font-normal text-slate-500">Umumnya memakai garis miring (/).</span>
                                @error('numbering.separator') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>
                            <label class="grid gap-1 text-sm font-bold text-slate-600">
                                Nomor Berikutnya
                                <input wire:model.live="numbering.next_sequence" type="number" min="1" class="min-h-11 min-w-0 rounded-lg border border-slate-200 px-3 text-slate-950">
                                <span class="text-xs font-normal text-slate-500">Bisa diubah jika perlu lompat nomor.</span>
                                @error('numbering.next_sequence') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>
                        </div>

                        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <div class="text-sm font-bold text-slate-700">Komponen Nomor</div>
                            <div class="mt-3 grid gap-3">
                                <label class="flex min-h-11 items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 text-sm font-bold text-slate-600">
                                    <span>Pakai Bulan</span>
                                    <input wire:model.live="numbering.include_month" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-teal-700">
                                </label>
                                <label class="flex min-h-11 items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 text-sm font-bold text-slate-600">
                                    <span>Pakai Tahun</span>
                                    <input wire:model.live="numbering.include_year" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-teal-700">
                                </label>
                            </div>
                            <button type="submit" class="mt-4 min-h-11 w-full rounded-lg bg-teal-700 px-4 text-sm font-bold text-white hover:bg-teal-800">Simpan Format</button>
                        </div>
                    </div>
                </form>

                @endif

                @if ($activeSettingsTab === 'unit')
                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="border-b border-slate-200 pb-4">
                        <h2 class="text-lg font-bold">Unit Surat</h2>
                        <p class="text-sm text-slate-500">Kelola unit yang muncul pada filter, input surat, format nomor, dan monitoring nomor surat.</p>
                    </div>

                    <form wire:submit="saveUnit" class="mt-5 grid gap-4 md:grid-cols-2">
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Kode Unit
                            <input wire:model="unitCode" type="text" class="min-h-11 rounded-lg border border-slate-200 px-3 uppercase text-slate-950" placeholder="Contoh: SETDA">
                            <span class="text-xs font-normal text-slate-500">Gunakan huruf, angka, dan tanda hubung. Kode yang sudah dipakai surat tidak bisa diganti.</span>
                            @error('unitCode') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Nama Unit
                            <input wire:model="unitName" type="text" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950" placeholder="Contoh: Bagian Umum">
                            @error('unitName') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600 md:col-span-2">
                            Deskripsi
                            <textarea wire:model="unitDescription" rows="3" class="rounded-lg border border-slate-200 px-3 py-2 text-slate-950" placeholder="Keterangan singkat unit surat"></textarea>
                            @error('unitDescription') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="flex min-h-11 items-center gap-3 rounded-lg border border-slate-200 px-3 text-sm font-bold text-slate-600">
                            <input wire:model="unitIsDefault" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-teal-700">
                            Jadikan unit default
                        </label>
                        <div class="flex flex-col gap-2 md:col-span-2 sm:flex-row sm:justify-end">
                            @if ($editingUnitIndex !== null)
                                <button type="button" wire:click="resetUnitForm" class="min-h-11 rounded-lg border border-slate-200 px-4 text-sm font-bold">Batal Edit</button>
                            @endif
                            <button type="submit" class="min-h-11 rounded-lg bg-teal-700 px-4 text-sm font-bold text-white hover:bg-teal-800">
                                {{ $editingUnitIndex !== null ? 'Simpan Perubahan' : 'Tambah Unit' }}
                            </button>
                        </div>
                    </form>

                    <div class="mt-5 overflow-x-auto rounded-lg border border-slate-200">
                        <table class="w-full min-w-[760px] border-collapse text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="border-b border-slate-200 px-4 py-3">Kode</th>
                                    <th class="border-b border-slate-200 px-4 py-3">Nama Unit</th>
                                    <th class="border-b border-slate-200 px-4 py-3">Deskripsi</th>
                                    <th class="border-b border-slate-200 px-4 py-3">Default</th>
                                    <th class="border-b border-slate-200 px-4 py-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($units as $index => $unit)
                                    <tr wire:key="letter-unit-{{ $unit['code'] }}">
                                        <td class="border-b border-slate-100 px-4 py-3 font-bold">{{ $unit['code'] }}</td>
                                        <td class="border-b border-slate-100 px-4 py-3">{{ $unit['name'] }}</td>
                                        <td class="border-b border-slate-100 px-4 py-3">{{ $unit['description'] ?: '-' }}</td>
                                        <td class="border-b border-slate-100 px-4 py-3">
                                            @if ($unit['is_default'])
                                                <span class="rounded-full bg-teal-100 px-2.5 py-1 text-xs font-bold text-teal-700 ring-1 ring-teal-200">Default</span>
                                            @else
                                                <span class="text-xs text-slate-400">-</span>
                                            @endif
                                        </td>
                                        <td class="border-b border-slate-100 px-4 py-3">
                                            <div class="flex flex-wrap gap-2">
                                                <button type="button" wire:click="editUnit({{ $index }})" class="whitespace-nowrap rounded-lg border border-slate-200 px-3 py-1.5 font-bold hover:border-teal-600">Edit</button>
                                                <button type="button" wire:click="deleteUnit({{ $index }})" wire:confirm="Hapus unit surat ini?" class="whitespace-nowrap rounded-lg border border-rose-200 px-3 py-1.5 font-bold text-rose-700 hover:bg-rose-50">Hapus</button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-10 text-center text-slate-500">Belum ada unit surat.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
                @endif

                @if ($activeSettingsTab === 'monitoring-nomor')
                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-4 border-b border-slate-200 pb-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <h2 class="text-lg font-bold">Monitoring Nomor Surat Keluar</h2>
                            <p class="text-sm text-slate-500">Cari nomor urut yang sudah dipakai dan nomor kosong yang masih bisa digunakan.</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                            <div class="font-bold text-slate-500">Nomor Berikutnya</div>
                            <div class="mt-1 text-xl font-bold text-slate-950">{{ str_pad((string) $numberMonitor['next_sequence'], 3, '0', STR_PAD_LEFT) }}</div>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-4 md:grid-cols-3">
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Unit Surat
                            <select wire:model.live="numberMonitorUnit" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                                @foreach ($units as $unit)
                                    <option value="{{ $unit['code'] }}">{{ $unit['code'] }} - {{ $unit['name'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Tahun Surat
                            <input wire:model.live="numberMonitorYear" type="number" min="2000" max="2100" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Cek Nomor Urut
                            <input wire:model.live.debounce.300ms="numberMonitorCheck" type="number" min="1" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950" placeholder="Contoh: 25">
                        </label>
                    </div>

                    <div class="mt-5 grid gap-3 md:grid-cols-3">
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                            <div class="text-sm font-bold text-emerald-700">Nomor Terpakai</div>
                            <div class="mt-2 text-3xl font-bold">{{ $numberMonitor['used_count'] }}</div>
                        </div>
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                            <div class="text-sm font-bold text-amber-700">Nomor Kosong</div>
                            <div class="mt-2 text-3xl font-bold">{{ $numberMonitor['missing_count'] }}</div>
                        </div>
                        <div class="rounded-lg border {{ $numberMonitor['check_sequence'] ? ($numberMonitor['check_is_available'] ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50') : 'border-slate-200 bg-slate-50' }} p-4">
                            <div class="text-sm font-bold {{ $numberMonitor['check_sequence'] ? ($numberMonitor['check_is_available'] ? 'text-emerald-700' : 'text-rose-700') : 'text-slate-600' }}">Hasil Cek</div>
                            <div class="mt-2 text-sm font-semibold">
                                @if ($numberMonitor['check_sequence'])
                                    Nomor {{ str_pad((string) $numberMonitor['check_sequence'], 3, '0', STR_PAD_LEFT) }}
                                    {{ $numberMonitor['check_is_available'] ? 'masih kosong' : 'sudah dipakai' }}
                                @else
                                    Masukkan nomor urut untuk dicek.
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                        <div class="rounded-lg border border-slate-200 p-4">
                            <div class="font-bold">Nomor Kosong yang Bisa Dipakai</div>
                            @if ($numberMonitor['missing_sequences'])
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($numberMonitor['missing_sequences'] as $sequence)
                                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800 ring-1 ring-amber-200">{{ str_pad((string) $sequence, 3, '0', STR_PAD_LEFT) }}</span>
                                    @endforeach
                                </div>
                            @else
                                <p class="mt-3 text-sm text-slate-500">Belum ada celah nomor pada rentang yang terbaca.</p>
                            @endif

                            @if ($numberMonitor['missing_ranges'])
                                <div class="mt-4 border-t border-slate-200 pt-4">
                                    <div class="text-sm font-bold text-slate-600">Ringkasan Rentang Kosong</div>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($numberMonitor['missing_ranges'] as [$start, $end])
                                            <span class="rounded-lg bg-slate-100 px-3 py-1.5 text-sm font-bold text-slate-700">
                                                {{ str_pad((string) $start, 3, '0', STR_PAD_LEFT) }}{{ $start !== $end ? ' - '.str_pad((string) $end, 3, '0', STR_PAD_LEFT) : '' }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="rounded-lg border border-slate-200 p-4">
                            <div class="font-bold">Nomor Terpakai Terakhir</div>
                            <div class="mt-3 space-y-3">
                                @forelse ($numberMonitor['recent_used'] as $item)
                                    <div class="border-l-4 border-teal-700 pl-3">
                                        <div class="text-sm font-bold">{{ str_pad((string) $item['sequence'], 3, '0', STR_PAD_LEFT) }} | {{ $item['number'] }}</div>
                                        <div class="mt-1 text-xs text-slate-500">{{ $item['subject'] }}</div>
                                    </div>
                                @empty
                                    <p class="text-sm text-slate-500">Belum ada nomor surat keluar untuk filter ini.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </section>
                @endif

                @if ($activeSettingsTab === 'nomor')
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
                @endif

                @if ($activeSettingsTab === 'pengguna')
                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="border-b border-slate-200 pb-4">
                        <h2 class="text-lg font-bold">Pengguna Aplikasi</h2>
                        <p class="text-sm text-slate-500">Kelola akun, role, status aktif, dan reset password pengguna.</p>
                    </div>

                    <form wire:submit="saveUser" class="mt-5 grid gap-4 md:grid-cols-2">
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Nama Pengguna
                            <input wire:model="userName" type="text" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                            @error('userName') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Email
                            <input wire:model="userEmail" type="email" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                            @error('userEmail') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Role
                            <select wire:model="userRole" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                                @foreach ($userRoles as $roleName)
                                    <option value="{{ $roleName }}">{{ $roleName }}</option>
                                @endforeach
                            </select>
                            @error('userRole') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Password
                            <input wire:model="userPassword" type="password" autocomplete="new-password" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950" placeholder="{{ $editingUserId ? 'Kosongkan jika tidak diganti' : 'Minimal 8 karakter' }}">
                            @error('userPassword') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="flex min-h-11 items-center gap-3 rounded-lg border border-slate-200 px-3 text-sm font-bold text-slate-600">
                            <input wire:model="userIsActive" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-teal-700">
                            Aktif
                        </label>
                        <div class="flex flex-col gap-2 md:col-span-2 sm:flex-row sm:justify-end">
                            @if ($editingUserId)
                                <button type="button" wire:click="resetUserForm" class="min-h-11 rounded-lg border border-slate-200 px-4 text-sm font-bold">Batal Edit</button>
                            @endif
                            <button type="submit" class="min-h-11 rounded-lg bg-teal-700 px-4 text-sm font-bold text-white hover:bg-teal-800">
                                {{ $editingUserId ? 'Simpan Perubahan' : 'Tambah Pengguna' }}
                            </button>
                        </div>
                    </form>

                    <div class="mt-5 overflow-x-auto rounded-lg border border-slate-200">
                        <table class="w-full min-w-[820px] border-collapse text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="border-b border-slate-200 px-4 py-3">Nama</th>
                                    <th class="border-b border-slate-200 px-4 py-3">Email</th>
                                    <th class="border-b border-slate-200 px-4 py-3">Role</th>
                                    <th class="border-b border-slate-200 px-4 py-3">Status</th>
                                    <th class="border-b border-slate-200 px-4 py-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($users as $user)
                                    <tr wire:key="user-{{ $user->id }}">
                                        <td class="border-b border-slate-100 px-4 py-3 font-bold">{{ $user->name }}</td>
                                        <td class="border-b border-slate-100 px-4 py-3">{{ $user->email }}</td>
                                        <td class="border-b border-slate-100 px-4 py-3">{{ $user->role }}</td>
                                        <td class="border-b border-slate-100 px-4 py-3">
                                            <span class="rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $user->is_active ? 'bg-emerald-100 text-emerald-700 ring-emerald-200' : 'bg-slate-100 text-slate-600 ring-slate-200' }}">
                                                {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                                            </span>
                                        </td>
                                        <td class="border-b border-slate-100 px-4 py-3">
                                            <div class="flex flex-wrap gap-2">
                                                <button type="button" wire:click="editUser({{ $user->id }})" class="whitespace-nowrap rounded-lg border border-slate-200 px-3 py-1.5 font-bold hover:border-teal-600">Edit</button>
                                                <button type="button" wire:click="toggleUser({{ $user->id }})" class="whitespace-nowrap rounded-lg border border-slate-200 px-3 py-1.5 font-bold hover:border-teal-600">{{ $user->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                                <button type="button" wire:click="deleteUser({{ $user->id }})" wire:confirm="Hapus pengguna ini?" class="whitespace-nowrap rounded-lg border border-rose-200 px-3 py-1.5 font-bold text-rose-700 hover:bg-rose-50">Hapus</button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-10 text-center text-slate-500">Belum ada pengguna.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
                @endif

                @if ($activeSettingsTab === 'disposisi')
                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="border-b border-slate-200 pb-4">
                        <h2 class="text-lg font-bold">Penerima Disposisi</h2>
                        <p class="text-sm text-slate-500">Kelola daftar staf atau unit yang bisa dipilih saat pimpinan mengirim disposisi.</p>
                    </div>

                    <form wire:submit="saveRecipient" class="mt-5 grid gap-4 md:grid-cols-2">
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Nama Penerima
                            <input wire:model="recipientName" type="text" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                            @error('recipientName') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Jabatan
                            <input wire:model="recipientPosition" type="text" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                            @error('recipientPosition') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Unit
                            <input wire:model="recipientUnit" type="text" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                            @error('recipientUnit') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="flex min-h-11 items-center gap-3 rounded-lg border border-slate-200 px-3 text-sm font-bold text-slate-600">
                            <input wire:model="recipientIsActive" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-teal-700">
                            Aktif
                        </label>
                        <div class="flex flex-col gap-2 md:col-span-2 sm:flex-row sm:justify-end">
                            @if ($editingRecipientId)
                                <button type="button" wire:click="resetRecipientForm" class="min-h-11 rounded-lg border border-slate-200 px-4 text-sm font-bold">Batal Edit</button>
                            @endif
                            <button type="submit" class="min-h-11 rounded-lg bg-teal-700 px-4 text-sm font-bold text-white hover:bg-teal-800">
                                {{ $editingRecipientId ? 'Simpan Perubahan' : 'Tambah Penerima' }}
                            </button>
                        </div>
                    </form>

                    <div class="mt-5 overflow-x-auto rounded-lg border border-slate-200">
                        <table class="w-full min-w-[720px] border-collapse text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="border-b border-slate-200 px-4 py-3">Nama</th>
                                    <th class="border-b border-slate-200 px-4 py-3">Jabatan</th>
                                    <th class="border-b border-slate-200 px-4 py-3">Unit</th>
                                    <th class="border-b border-slate-200 px-4 py-3">Status</th>
                                    <th class="border-b border-slate-200 px-4 py-3">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($dispositionRecipients as $recipient)
                                    <tr wire:key="recipient-{{ $recipient->id }}">
                                        <td class="border-b border-slate-100 px-4 py-3 font-bold">{{ $recipient->name }}</td>
                                        <td class="border-b border-slate-100 px-4 py-3">{{ $recipient->position ?? '-' }}</td>
                                        <td class="border-b border-slate-100 px-4 py-3">{{ $recipient->unit ?? '-' }}</td>
                                        <td class="border-b border-slate-100 px-4 py-3">
                                            <span class="rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $recipient->is_active ? 'bg-emerald-100 text-emerald-700 ring-emerald-200' : 'bg-slate-100 text-slate-600 ring-slate-200' }}">
                                                {{ $recipient->is_active ? 'Aktif' : 'Nonaktif' }}
                                            </span>
                                        </td>
                                        <td class="border-b border-slate-100 px-4 py-3">
                                            <div class="flex flex-wrap gap-2">
                                                <button type="button" wire:click="editRecipient({{ $recipient->id }})" class="whitespace-nowrap rounded-lg border border-slate-200 px-3 py-1.5 font-bold hover:border-teal-600">Edit</button>
                                                <button type="button" wire:click="toggleRecipient({{ $recipient->id }})" class="whitespace-nowrap rounded-lg border border-slate-200 px-3 py-1.5 font-bold hover:border-teal-600">{{ $recipient->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                                <button type="button" wire:click="deleteRecipient({{ $recipient->id }})" wire:confirm="Hapus penerima disposisi ini?" class="whitespace-nowrap rounded-lg border border-rose-200 px-3 py-1.5 font-bold text-rose-700 hover:bg-rose-50">Hapus</button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-10 text-center text-slate-500">Belum ada penerima disposisi.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
                @endif

                @if ($activeSettingsTab === 'audit')
                    <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-lg font-bold">Audit Trail</h2>
                        <div class="mt-4 space-y-3">
                            @forelse ($activityLogs as $log)
                                <div wire:key="main-activity-log-{{ $log->id }}" class="border-l-4 border-teal-700 pl-3">
                                    <div class="text-sm font-bold">{{ $log->description }}</div>
                                    <div class="mt-1 text-xs text-slate-500">
                                        {{ $log->user?->name ?? 'Sistem' }} | {{ $log->created_at->translatedFormat('d M Y H:i') }}
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm text-slate-500">Belum ada aktivitas tercatat.</p>
                            @endforelse
                        </div>
                    </section>

                    <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 class="text-lg font-bold">Peran Pengguna</h2>
                        <div class="mt-4 space-y-3">
                            @foreach ($roles as $index => $role)
                                <div wire:key="main-role-{{ $index }}" class="rounded-lg border border-slate-200 p-3">
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
                @endif
            </div>

            @if (in_array($activeSettingsTab, ['profil', 'nomor'], true))
            <aside class="min-w-0 space-y-5">
                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-bold">Ringkasan</h2>
                    <div class="mt-4 space-y-4 text-sm">
                        <div>
                            <div class="font-bold text-slate-500">Instansi</div>
                            <div class="text-slate-500">{{ $agency['app_name'] ?? '-' }} | {{ $agency['short_name'] ?? '-' }}</div>
                            <div class="font-semibold">{{ $agency['name'] ?? '-' }}</div>
                            <div class="text-slate-500">{{ $agency['unit'] ?? '-' }}</div>
                            <div class="text-slate-500">{{ $agency['leader_title'] ?? '-' }} | {{ $agency['city'] ?? '-' }}</div>
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
            </aside>
            @endif
        </section>
    </main>

    <div x-show="showToast"
         x-transition
         x-cloak
         class="fixed bottom-6 right-6 z-30 max-w-sm rounded-lg bg-slate-900 px-4 py-3 text-sm font-semibold text-white shadow-lg"
         x-text="toast"></div>
</div>
