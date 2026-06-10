<?php

use App\Models\AppSetting;
use App\Models\ActivityLog;
use App\Models\ArchiveClassification;
use App\Models\DispositionRecipient;
use App\Models\Letter;
use App\Models\User;
use App\Support\TaskInbox;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new class extends Component
{
    use WithFileUploads;
    use WithPagination;

    public array $agency = [];

    public array $numbering = [];

    public array $workflow = [];

    public array $mobileAndroid = [];

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

    public ?int $editingClassificationId = null;

    public string $classificationCode = '';

    public string $classificationName = '';

    public string $classificationParentCode = '';

    public string $classificationDescription = '';

    public $classificationImport = null;

    public array $classificationImportPreview = [];

    public string $classificationImportFileName = '';

    public int $classificationPreviewPage = 1;

    public int $classificationPreviewPerPage = 10;

    public int $classificationPerPage = 10;

    public string $classificationSearch = '';

    public string $classificationParentFilter = 'Semua';

    public string $classificationUsageFilter = 'Semua';

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

        $this->mobileAndroid = AppSetting::getValue('mobile_versions', \App\Http\Controllers\Api\Mobile\AppVersionController::defaults())['android']
            ?? \App\Http\Controllers\Api\Mobile\AppVersionController::defaults()['android'];

        $this->roles = AppSetting::getValue('roles', []);

        $requestedTab = request('tab');
        if (is_string($requestedTab) && array_key_exists($requestedTab, $this->settingsTabs())) {
            $this->activeSettingsTab = $requestedTab;
        }
    }

    public function settingsTabs(): array
    {
        return [
            'profil' => 'Profil Instansi',
            'nomor' => 'Nomor & Alur',
            'unit' => 'Unit Surat',
            'kode-arsip' => 'Kode Arsip',
            'monitoring-nomor' => 'Monitoring Nomor',
            'pengguna' => 'Pengguna',
            'disposisi' => 'Disposisi',
            'android' => 'Android',
            'audit' => 'Audit & Peran',
        ];
    }

    public function setSettingsTab(string $tab): void
    {
        if (! array_key_exists($tab, $this->settingsTabs())) {
            return;
        }

        if ($this->activeSettingsTab !== $tab) {
            $this->resetPage('classificationPage');
        }

        $this->activeSettingsTab = $tab;
    }

    public function updatedClassificationPerPage(): void
    {
        $this->resetPage('classificationPage');
    }

    public function updatedClassificationSearch(): void
    {
        $this->resetPage('classificationPage');
    }

    public function updatedClassificationParentFilter(): void
    {
        $this->resetPage('classificationPage');
    }

    public function updatedClassificationUsageFilter(): void
    {
        $this->resetPage('classificationPage');
    }

    public function updatedClassificationPreviewPerPage(): void
    {
        $this->classificationPreviewPage = 1;
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

    public function saveMobileAndroid(): void
    {
        $this->validate([
            'mobileAndroid.current_version_name' => ['required', 'string', 'max:30'],
            'mobileAndroid.current_version_code' => ['required', 'integer', 'min:1'],
            'mobileAndroid.minimum_version_code' => ['required', 'integer', 'min:1'],
            'mobileAndroid.download_url' => ['required', 'url', 'max:500'],
            'mobileAndroid.release_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $versions = AppSetting::getValue('mobile_versions', \App\Http\Controllers\Api\Mobile\AppVersionController::defaults());
        $versions['android'] = [
            'current_version_name' => (string) $this->mobileAndroid['current_version_name'],
            'current_version_code' => (int) $this->mobileAndroid['current_version_code'],
            'minimum_version_code' => (int) $this->mobileAndroid['minimum_version_code'],
            'download_url' => (string) $this->mobileAndroid['download_url'],
            'release_notes' => (string) ($this->mobileAndroid['release_notes'] ?? ''),
        ];

        AppSetting::putValue('mobile_versions', $versions);
        ActivityLog::record('setting.updated', 'Pengaturan aplikasi Android diperbarui.');
        $this->dispatch('notify', message: 'Pengaturan aplikasi Android berhasil disimpan.');
    }

    public function unitCodes(): array
    {
        return collect($this->units)->pluck('code')->filter()->values()->all();
    }

    public function saveUnit(): void
    {
        $this->unitCode = $this->normalizeUnitInput($this->unitCode);
        $oldCode = $this->editingUnitIndex !== null ? ($this->units[$this->editingUnitIndex]['code'] ?? null) : null;

        $this->validate([
            'unitCode' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9 -]+$/'],
            'unitName' => ['required', 'string', 'max:100'],
            'unitDescription' => ['nullable', 'string', 'max:255'],
            'unitIsDefault' => ['boolean'],
        ]);

        $duplicate = collect($this->units)
            ->filter(fn (array $unit, int $index) => $index !== $this->editingUnitIndex)
            ->contains(fn (array $unit) => $this->normalizeUnitInput($unit['code']) === $this->unitCode);

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

    public function normalizeUnitInput(?string $code): string
    {
        return preg_replace('/\s+/', ' ', strtoupper(trim((string) $code))) ?? '';
    }

    public function resetUnitForm(): void
    {
        $this->editingUnitIndex = null;
        $this->unitCode = '';
        $this->unitName = '';
        $this->unitDescription = '';
        $this->unitIsDefault = false;
    }

    public function archiveClassifications()
    {
        $search = trim($this->classificationSearch);

        return ArchiveClassification::query()
            ->withCount('letters')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query
                        ->where('code', 'like', '%'.$search.'%')
                        ->orWhere('name', 'like', '%'.$search.'%')
                        ->orWhere('parent_code', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->when($this->classificationParentFilter === 'Induk Utama', fn ($query) => $query->whereNull('parent_code'))
            ->when($this->classificationParentFilter === 'Turunan', fn ($query) => $query->whereNotNull('parent_code'))
            ->when($this->classificationUsageFilter === 'Dipakai', fn ($query) => $query->has('letters'))
            ->when($this->classificationUsageFilter === 'Belum Dipakai', fn ($query) => $query->doesntHave('letters'))
            ->orderBy('code')
            ->paginate($this->classificationPerPage, ['*'], 'classificationPage');
    }

    public function resetClassificationFilters(): void
    {
        $this->classificationSearch = '';
        $this->classificationParentFilter = 'Semua';
        $this->classificationUsageFilter = 'Semua';
        $this->resetPage('classificationPage');
    }

    public function saveClassification(): void
    {
        $this->classificationCode = strtoupper(trim($this->classificationCode));
        $this->classificationParentCode = strtoupper(trim($this->classificationParentCode));

        $validated = $this->validate([
            'classificationCode' => ['required', 'string', 'max:40', Rule::unique('archive_classifications', 'code')->ignore($this->editingClassificationId)],
            'classificationName' => ['required', 'string', 'max:255'],
            'classificationParentCode' => ['nullable', 'string', 'max:40', 'different:classificationCode'],
            'classificationDescription' => ['nullable', 'string', 'max:1000'],
        ]);

        $classification = $this->editingClassificationId
            ? ArchiveClassification::findOrFail($this->editingClassificationId)
            : new ArchiveClassification();

        if ($this->editingClassificationId && $classification->code !== $validated['classificationCode'] && Letter::where('classification_code', $classification->code)->exists()) {
            $this->addError('classificationCode', 'Kode arsip sudah dipakai surat sehingga tidak bisa diganti.');

            return;
        }

        $classification->fill([
            'code' => $validated['classificationCode'],
            'name' => $validated['classificationName'],
            'parent_code' => $validated['classificationParentCode'] ?: null,
            'description' => $validated['classificationDescription'] ?: null,
        ])->save();

        ActivityLog::record(
            $this->editingClassificationId ? 'classification.updated' : 'classification.created',
            ($this->editingClassificationId ? 'Kode arsip diperbarui: ' : 'Kode arsip ditambahkan: ').$classification->code,
            $classification,
        );

        $this->resetClassificationForm();
        $this->resetPage('classificationPage');
        $this->dispatch('notify', message: 'Kode klasifikasi arsip berhasil disimpan.');
    }

    public function editClassification(int $classificationId): void
    {
        $classification = ArchiveClassification::findOrFail($classificationId);

        $this->editingClassificationId = $classification->id;
        $this->classificationCode = $classification->code;
        $this->classificationName = $classification->name;
        $this->classificationParentCode = $classification->parent_code ?? '';
        $this->classificationDescription = $classification->description ?? '';
    }

    public function deleteClassification(int $classificationId): void
    {
        $classification = ArchiveClassification::withCount('letters')->findOrFail($classificationId);

        if ($classification->letters_count > 0) {
            $this->dispatch('notify', message: 'Kode arsip tidak bisa dihapus karena sudah dipakai surat.');

            return;
        }

        if (ArchiveClassification::where('parent_code', $classification->code)->exists()) {
            $this->dispatch('notify', message: 'Kode arsip tidak bisa dihapus karena masih memiliki turunan.');

            return;
        }

        $code = $classification->code;
        $classification->delete();

        if ($this->editingClassificationId === $classificationId) {
            $this->resetClassificationForm();
        }

        ActivityLog::record('classification.deleted', 'Kode arsip dihapus: '.$code);
        $this->resetPage('classificationPage');
        $this->dispatch('notify', message: 'Kode klasifikasi arsip berhasil dihapus.');
    }

    public function importClassifications(): void
    {
        $this->previewClassificationImport();
    }

    public function previewClassificationImport(): void
    {
        $this->validate([
            'classificationImport' => ['required', 'file', 'max:10240'],
        ]);

        $extension = strtolower($this->classificationImport->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'csv', 'txt'], true)) {
            $this->addError('classificationImport', 'Gunakan file Excel .xlsx atau CSV.');

            return;
        }

        $rows = $extension === 'xlsx'
            ? $this->rowsFromXlsx($this->classificationImport->getRealPath())
            : $this->rowsFromCsv($this->classificationImport->getRealPath());

        $this->classificationImportPreview = $this->classificationPreviewRows($rows);
        $this->classificationImportFileName = $this->classificationImport->getClientOriginalName();
        $this->classificationPreviewPage = 1;
        $this->classificationImport = null;

        $valid = collect($this->classificationImportPreview)->where('status', 'valid')->count();
        $duplicate = collect($this->classificationImportPreview)->where('status', 'duplicate')->count();
        $this->dispatch('notify', message: 'Preview import siap direview. '.$valid.' data valid, '.$duplicate.' data duplikat.');
    }

    public function confirmClassificationImport(): void
    {
        $result = $this->storeClassificationPreview($this->classificationImportPreview);

        $this->classificationImportPreview = [];
        $this->classificationImportFileName = '';
        $this->classificationPreviewPage = 1;
        $this->resetPage('classificationPage');

        ActivityLog::record('classification.imported', $result['imported'].' kode arsip diimport dari Excel/CSV. '.$result['skipped'].' data duplikat dilewati.');
        $this->dispatch('notify', message: $result['imported'].' kode klasifikasi arsip berhasil disimpan. '.$result['skipped'].' data duplikat dilewati.');
    }

    public function cancelClassificationImport(): void
    {
        $this->classificationImportPreview = [];
        $this->classificationImportFileName = '';
        $this->classificationPreviewPage = 1;
        $this->classificationImport = null;
    }

    public function classificationPreviewRowsForPage(): array
    {
        return array_slice(
            $this->classificationImportPreview,
            ($this->classificationPreviewPage - 1) * $this->classificationPreviewPerPage,
            $this->classificationPreviewPerPage,
        );
    }

    public function classificationPreviewTotalPages(): int
    {
        return max(1, (int) ceil(count($this->classificationImportPreview) / $this->classificationPreviewPerPage));
    }

    public function setClassificationPreviewPage(int $page): void
    {
        $this->classificationPreviewPage = min(max(1, $page), $this->classificationPreviewTotalPages());
    }

    public function storeClassificationRows(array $rows): array
    {
        return $this->storeClassificationPreview($this->classificationPreviewRows($rows));
    }

    public function classificationPreviewRows(array $rows): array
    {
        $rows = collect($rows)
            ->map(fn (array $row) => array_values(array_map(fn ($value) => trim((string) $value), $row)))
            ->filter(fn (array $row) => collect($row)->filter()->isNotEmpty())
            ->values();

        if ($rows->isEmpty()) {
            return [];
        }

        $firstRow = $rows->first();
        $headers = collect($firstRow)->map(fn (string $value) => strtolower(str_replace([' ', '-', '.'], '_', $value)))->all();
        $hasHeader = collect($headers)->contains(fn (string $header) => in_array($header, ['kode', 'code', 'kode_arsip', 'kode_klasifikasi', 'classification_code'], true));
        $dataRows = $hasHeader ? $rows->slice(1)->values() : $rows;

        $codeIndex = $hasHeader ? $this->headerIndex($headers, ['kode', 'code', 'kode_arsip', 'kode_klasifikasi', 'classification_code']) : 0;
        $nameIndex = $hasHeader ? $this->headerIndex($headers, ['nama', 'name', 'nama_arsip', 'uraian', 'klasifikasi', 'classification_name']) : 1;
        $parentIndex = $hasHeader ? $this->headerIndex($headers, ['parent', 'parent_code', 'kode_induk', 'induk']) : 2;
        $descriptionIndex = $hasHeader ? $this->headerIndex($headers, ['deskripsi', 'description', 'keterangan']) : 3;

        $existingCodes = ArchiveClassification::query()
            ->pluck('code')
            ->map(fn (string $code) => strtoupper(trim($code)))
            ->all();
        $seenCodes = [];
        $preview = [];

        foreach ($dataRows as $row) {
            $code = strtoupper(trim((string) ($row[$codeIndex] ?? '')));
            $name = trim((string) ($row[$nameIndex] ?? ''));
            $parentCode = strtoupper(trim((string) ($row[$parentIndex] ?? '')));
            $description = trim((string) ($row[$descriptionIndex] ?? ''));
            $status = 'valid';
            $reason = 'Siap disimpan';

            if ($code === '' || $name === '') {
                $status = 'duplicate';
                $reason = 'Kode atau nama kosong';
            } elseif (in_array($code, $existingCodes, true)) {
                $status = 'duplicate';
                $reason = 'Kode sudah ada di database';
            } elseif (in_array($code, $seenCodes, true)) {
                $status = 'duplicate';
                $reason = 'Duplikat di file import';
            } else {
                $seenCodes[] = $code;
            }

            $preview[] = [
                'code' => $code,
                'name' => $name,
                'parent_code' => $parentCode,
                'description' => $description,
                'status' => $status,
                'reason' => $reason,
            ];
        }

        return $preview;
    }

    public function storeClassificationPreview(array $preview): array
    {
        $imported = 0;
        $skipped = 0;

        foreach ($preview as $item) {
            if (($item['status'] ?? '') !== 'valid') {
                $skipped++;

                continue;
            }

            ArchiveClassification::create([
                'code' => $item['code'],
                'name' => $item['name'],
                'parent_code' => ($item['parent_code'] ?? '') ?: null,
                'description' => ($item['description'] ?? '') ?: null,
            ]);
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    public function headerIndex(array $headers, array $candidates): int
    {
        foreach ($candidates as $candidate) {
            $index = array_search($candidate, $headers, true);
            if ($index !== false) {
                return (int) $index;
            }
        }

        return 0;
    }

    public function rowsFromCsv(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        if ($lines === []) {
            return [];
        }

        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';

        return collect($lines)
            ->map(fn (string $line) => str_getcsv($line, $delimiter))
            ->all();
    }

    public function rowsFromXlsx(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        $sharedStrings = $this->xlsxSharedStrings($zip);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml') ?: '';
        $zip->close();

        if ($sheetXml === '') {
            return [];
        }

        $sheet = simplexml_load_string($sheetXml);
        if (! $sheet) {
            return [];
        }

        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            $values = [];
            foreach ($row->c as $cell) {
                $reference = (string) $cell['r'];
                $column = preg_replace('/\d+/', '', $reference);
                $index = $this->excelColumnIndex((string) $column);
                $type = (string) $cell['t'];
                $value = (string) ($cell->v ?? '');

                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = (string) ($cell->is->t ?? '');
                }

                $values[$index] = $value;
            }

            if ($values !== []) {
                ksort($values);
                $rows[] = array_values($values);
            }
        }

        return $rows;
    }

    public function xlsxSharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml') ?: '';
        if ($xml === '') {
            return [];
        }

        $shared = simplexml_load_string($xml);
        if (! $shared) {
            return [];
        }

        $strings = [];
        foreach ($shared->si as $item) {
            $textParts = [];
            foreach ($item->xpath('.//*[local-name()="t"]') ?: [] as $text) {
                $textParts[] = (string) $text;
            }
            $strings[] = implode('', $textParts);
        }

        return $strings;
    }

    public function excelColumnIndex(string $column): int
    {
        $index = 0;
        foreach (str_split($column) as $letter) {
            $index = $index * 26 + (ord(strtoupper($letter)) - 64);
        }

        return max(0, $index - 1);
    }

    public function resetClassificationForm(): void
    {
        $this->editingClassificationId = null;
        $this->classificationCode = '';
        $this->classificationName = '';
        $this->classificationParentCode = '';
        $this->classificationDescription = '';
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
        $missingItems = collect($missing)
            ->take(40)
            ->map(function (int $sequence) use ($used) {
                $recommendation = $this->recommendedDateForSequence($sequence, $used);

                return [
                    'sequence' => $sequence,
                    'recommended_date' => $recommendation['date'],
                    'recommendation_note' => $recommendation['note'],
                    'suggested_number' => $this->numberForSequence($sequence, $recommendation['date']),
                ];
            })
            ->values()
            ->all();

        $check = $this->numberMonitorCheck !== ''
            ? max(1, (int) $this->numberMonitorCheck)
            : null;
        $checkRecommendation = $check && ! in_array($check, $usedSequences, true)
            ? $this->recommendedDateForSequence($check, $used)
            : null;

        return [
            'used_count' => count($usedSequences),
            'missing_count' => count($missing),
            'next_sequence' => $nextSequence,
            'missing_sequences' => array_slice($missing, 0, 40),
            'missing_items' => $missingItems,
            'missing_ranges' => $this->sequenceRanges($missing),
            'recent_used' => $used->reverse()->take(8)->values()->all(),
            'check_sequence' => $check,
            'check_is_available' => $check ? ! in_array($check, $usedSequences, true) : null,
            'check_recommendation' => $checkRecommendation,
        ];
    }

    public function recommendedDateForSequence(int $sequence, \Illuminate\Support\Collection $used): array
    {
        $previous = $used
            ->filter(fn (array $item) => $item['sequence'] < $sequence && $item['letter_date'])
            ->sortByDesc('sequence')
            ->first();
        $next = $used
            ->filter(fn (array $item) => $item['sequence'] > $sequence && $item['letter_date'])
            ->sortBy('sequence')
            ->first();

        if ($previous && $next && $previous['letter_date']->isSameDay($next['letter_date'])) {
            return [
                'date' => $previous['letter_date'],
                'note' => 'Mengikuti tanggal nomor '.str_pad((string) $previous['sequence'], 3, '0', STR_PAD_LEFT).' dan '.str_pad((string) $next['sequence'], 3, '0', STR_PAD_LEFT),
            ];
        }

        if ($previous) {
            return [
                'date' => $previous['letter_date'],
                'note' => 'Disarankan mengikuti tanggal nomor sebelumnya '.str_pad((string) $previous['sequence'], 3, '0', STR_PAD_LEFT),
            ];
        }

        if ($next) {
            return [
                'date' => $next['letter_date'],
                'note' => 'Disarankan mengikuti tanggal nomor berikutnya '.str_pad((string) $next['sequence'], 3, '0', STR_PAD_LEFT),
            ];
        }

        return [
            'date' => null,
            'note' => 'Belum ada histori pembanding',
        ];
    }

    public function numberForSequence(int $sequence, mixed $recommendedDate = null): string
    {
        $separator = (string) ($this->numbering['separator'] ?? '/');
        $date = $recommendedDate ?: now()->setYear($this->numberMonitorYear);
        $parts = [
            $this->numbering['prefix'] ?? '800',
            str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
            $this->numberMonitorUnit,
        ];

        if ($this->numbering['include_month'] ?? true) {
            $parts[] = $date->format('m');
        }

        if ($this->numbering['include_year'] ?? true) {
            $parts[] = $date->format('Y');
        }

        return implode($separator, $parts);
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
        $archiveClassifications = $this->archiveClassifications();
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
                            <input wire:model="unitCode" type="text" class="min-h-11 rounded-lg border border-slate-200 px-3 uppercase text-slate-950" placeholder="Contoh: BAG UMUM">
                            <span class="text-xs font-normal text-slate-500">Gunakan huruf, angka, spasi, dan tanda hubung. Kode yang sudah dipakai surat tidak bisa diganti.</span>
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

                @if ($activeSettingsTab === 'kode-arsip')
                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-4 border-b border-slate-200 pb-4 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <h2 class="text-lg font-bold">Kode Klasifikasi Arsip</h2>
                            <p class="text-sm text-slate-500">Kelola kode klasifikasi arsip Permendagri 83/2022 yang dipakai pada surat masuk dan keluar.</p>
                        </div>
                        <form wire:submit="previewClassificationImport" class="grid gap-2 rounded-lg border border-dashed border-slate-300 bg-slate-50 p-3 text-sm lg:w-[360px]">
                            <label class="grid gap-1 font-bold text-slate-600">
                                Import Excel / CSV
                                <input wire:model="classificationImport" type="file" accept=".xlsx,.csv,.txt" class="rounded-lg border border-slate-200 bg-white p-2 text-slate-950">
                                <span class="text-xs font-normal text-slate-500">Template .xlsx sudah dipisah menjadi kolom kode, nama, kode induk, dan deskripsi.</span>
                                @error('classificationImport') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                            </label>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <a href="{{ route('archive-classifications.import-template') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-700 hover:border-teal-600">
                                    Download Template
                                </a>
                                <button type="submit" class="min-h-10 rounded-lg bg-teal-700 px-4 text-sm font-bold text-white hover:bg-teal-800">Preview Data</button>
                            </div>
                        </form>
                    </div>

                    @if ($classificationImportPreview)
                        @php
                            $previewValidCount = collect($classificationImportPreview)->where('status', 'valid')->count();
                            $previewDuplicateCount = collect($classificationImportPreview)->where('status', 'duplicate')->count();
                            $previewRows = $this->classificationPreviewRowsForPage();
                            $previewTotal = count($classificationImportPreview);
                            $previewFirst = $previewTotal > 0 ? (($classificationPreviewPage - 1) * $classificationPreviewPerPage) + 1 : 0;
                            $previewLast = min($classificationPreviewPage * $classificationPreviewPerPage, $previewTotal);
                            $previewTotalPages = $this->classificationPreviewTotalPages();
                        @endphp
                        <div class="mt-5 rounded-lg border border-slate-200 bg-white shadow-sm">
                            <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 p-4 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <h3 class="font-bold">Preview Data Import</h3>
                                    <p class="text-sm text-slate-500">
                                        {{ $classificationImportFileName }} | {{ $previewValidCount }} data valid, {{ $previewDuplicateCount }} data duplikat/tidak valid.
                                    </p>
                                </div>
                                <div class="flex flex-col gap-2 sm:flex-row">
                                    <button type="button" wire:click="cancelClassificationImport" class="min-h-10 rounded-lg border border-slate-200 bg-white px-4 text-sm font-bold text-slate-700 hover:border-rose-500">
                                        Batalkan
                                    </button>
                                    <button type="button" wire:click="confirmClassificationImport" @disabled($previewValidCount === 0) class="min-h-10 rounded-lg bg-teal-700 px-4 text-sm font-bold text-white hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-50">
                                        Simpan Data Valid
                                    </button>
                                </div>
                            </div>
                            <div class="flex flex-col gap-3 border-b border-slate-200 bg-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="text-sm font-semibold text-slate-600">
                                    Menampilkan {{ $previewFirst }}-{{ $previewLast }} dari {{ $previewTotal }} data preview
                                </div>
                                <label class="flex items-center gap-2 text-sm font-bold text-slate-600">
                                    Per halaman
                                    <select wire:model.live="classificationPreviewPerPage" class="min-h-9 rounded-lg border border-slate-200 bg-white px-2 text-sm text-slate-950">
                                        @foreach ([10, 25, 50] as $pageSize)
                                            <option value="{{ $pageSize }}">{{ $pageSize }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[920px] border-collapse text-left text-sm">
                                    <thead class="bg-white text-xs uppercase text-slate-500">
                                        <tr>
                                            <th class="border-b border-slate-200 px-4 py-3">Status</th>
                                            <th class="border-b border-slate-200 px-4 py-3">Kode</th>
                                            <th class="border-b border-slate-200 px-4 py-3">Nama</th>
                                            <th class="border-b border-slate-200 px-4 py-3">Kode Induk</th>
                                            <th class="border-b border-slate-200 px-4 py-3">Deskripsi</th>
                                            <th class="border-b border-slate-200 px-4 py-3">Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($previewRows as $index => $item)
                                            <tr wire:key="classification-preview-{{ $index }}" class="{{ $item['status'] === 'duplicate' ? 'bg-rose-50 text-rose-900' : 'bg-white' }}">
                                                <td class="border-b border-slate-100 px-4 py-3">
                                                    <span class="rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $item['status'] === 'duplicate' ? 'bg-rose-100 text-rose-700 ring-rose-200' : 'bg-emerald-100 text-emerald-700 ring-emerald-200' }}">
                                                        {{ $item['status'] === 'duplicate' ? 'Duplikat' : 'Valid' }}
                                                    </span>
                                                </td>
                                                <td class="border-b border-slate-100 px-4 py-3 font-bold">{{ $item['code'] ?: '-' }}</td>
                                                <td class="border-b border-slate-100 px-4 py-3">{{ $item['name'] ?: '-' }}</td>
                                                <td class="border-b border-slate-100 px-4 py-3">{{ $item['parent_code'] ?: '-' }}</td>
                                                <td class="border-b border-slate-100 px-4 py-3">{{ $item['description'] ?: '-' }}</td>
                                                <td class="border-b border-slate-100 px-4 py-3">{{ $item['reason'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @if ($previewTotalPages > 1)
                                <div class="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="text-sm font-semibold text-slate-600">Halaman {{ $classificationPreviewPage }} dari {{ $previewTotalPages }}</div>
                                    <div class="flex gap-2">
                                        <button type="button" wire:click="setClassificationPreviewPage({{ $classificationPreviewPage - 1 }})" @disabled($classificationPreviewPage <= 1) class="min-h-10 rounded-lg border border-slate-200 px-4 text-sm font-bold text-slate-700 hover:border-teal-600 disabled:cursor-not-allowed disabled:opacity-50">
                                            Sebelumnya
                                        </button>
                                        <button type="button" wire:click="setClassificationPreviewPage({{ $classificationPreviewPage + 1 }})" @disabled($classificationPreviewPage >= $previewTotalPages) class="min-h-10 rounded-lg border border-slate-200 px-4 text-sm font-bold text-slate-700 hover:border-teal-600 disabled:cursor-not-allowed disabled:opacity-50">
                                            Berikutnya
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    <form wire:submit="saveClassification" class="mt-5 grid gap-4 md:grid-cols-2">
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Kode Arsip
                            <input wire:model="classificationCode" type="text" class="min-h-11 rounded-lg border border-slate-200 px-3 uppercase text-slate-950" placeholder="Contoh: 000.1.2">
                            @error('classificationCode') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Kode Induk
                            <input wire:model="classificationParentCode" type="text" class="min-h-11 rounded-lg border border-slate-200 px-3 uppercase text-slate-950" placeholder="Contoh: 000.1">
                            @error('classificationParentCode') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600 md:col-span-2">
                            Nama Klasifikasi
                            <input wire:model="classificationName" type="text" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950" placeholder="Contoh: Perjalanan Dinas Dalam Negeri">
                            @error('classificationName') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600 md:col-span-2">
                            Deskripsi
                            <textarea wire:model="classificationDescription" rows="3" class="rounded-lg border border-slate-200 px-3 py-2 text-slate-950" placeholder="Keterangan singkat kode klasifikasi arsip"></textarea>
                            @error('classificationDescription') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <div class="flex flex-col gap-2 md:col-span-2 sm:flex-row sm:justify-end">
                            @if ($editingClassificationId)
                                <button type="button" wire:click="resetClassificationForm" class="min-h-11 rounded-lg border border-slate-200 px-4 text-sm font-bold">Batal Edit</button>
                            @endif
                            <button type="submit" class="min-h-11 rounded-lg bg-teal-700 px-4 text-sm font-bold text-white hover:bg-teal-800">
                                {{ $editingClassificationId ? 'Simpan Perubahan' : 'Tambah Kode Arsip' }}
                            </button>
                        </div>
                    </form>

                    <div class="mt-5 rounded-lg border border-slate-200">
                        <div class="grid gap-3 border-b border-slate-200 bg-white p-4 md:grid-cols-[minmax(0,1fr)_220px_220px_auto] md:items-end">
                            <label class="grid gap-1 text-sm font-bold text-slate-600">
                                Pencarian
                                <input wire:model.live.debounce.300ms="classificationSearch" type="search" class="min-h-10 rounded-lg border border-slate-200 px-3 text-sm text-slate-950" placeholder="Cari kode, nama, induk, atau deskripsi">
                            </label>
                            <label class="grid gap-1 text-sm font-bold text-slate-600">
                                Jenis Kode
                                <select wire:model.live="classificationParentFilter" class="min-h-10 rounded-lg border border-slate-200 px-3 text-sm text-slate-950">
                                    @foreach (['Semua', 'Induk Utama', 'Turunan'] as $parentFilter)
                                        <option value="{{ $parentFilter }}">{{ $parentFilter }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="grid gap-1 text-sm font-bold text-slate-600">
                                Status Pemakaian
                                <select wire:model.live="classificationUsageFilter" class="min-h-10 rounded-lg border border-slate-200 px-3 text-sm text-slate-950">
                                    @foreach (['Semua', 'Dipakai', 'Belum Dipakai'] as $usageFilter)
                                        <option value="{{ $usageFilter }}">{{ $usageFilter }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <button type="button" wire:click="resetClassificationFilters" class="min-h-10 rounded-lg border border-slate-200 px-4 text-sm font-bold text-slate-700 hover:border-teal-600">
                                Reset
                            </button>
                        </div>
                        <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="text-sm font-semibold text-slate-600">
                                @if ($archiveClassifications->total() > 0)
                                    Menampilkan {{ $archiveClassifications->firstItem() }}-{{ $archiveClassifications->lastItem() }} dari {{ $archiveClassifications->total() }} kode arsip
                                @else
                                    Belum ada kode klasifikasi arsip
                                @endif
                            </div>
                            <label class="flex items-center gap-2 text-sm font-bold text-slate-600">
                                Per halaman
                                <select wire:model.live="classificationPerPage" class="min-h-9 rounded-lg border border-slate-200 bg-white px-2 text-sm text-slate-950">
                                    @foreach ([10, 25, 50] as $pageSize)
                                        <option value="{{ $pageSize }}">{{ $pageSize }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[920px] border-collapse text-left text-sm">
                                <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                                    <tr>
                                        <th class="border-b border-slate-200 px-4 py-3">Kode</th>
                                        <th class="border-b border-slate-200 px-4 py-3">Nama Klasifikasi</th>
                                        <th class="border-b border-slate-200 px-4 py-3">Induk</th>
                                        <th class="border-b border-slate-200 px-4 py-3">Dipakai Surat</th>
                                        <th class="border-b border-slate-200 px-4 py-3">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($archiveClassifications as $classification)
                                        <tr wire:key="archive-classification-{{ $classification->id }}">
                                            <td class="border-b border-slate-100 px-4 py-3 font-bold">{{ $classification->code }}</td>
                                            <td class="border-b border-slate-100 px-4 py-3">
                                                <div class="font-semibold">{{ $classification->name }}</div>
                                                @if ($classification->description)
                                                    <div class="mt-1 text-xs text-slate-500">{{ $classification->description }}</div>
                                                @endif
                                            </td>
                                            <td class="border-b border-slate-100 px-4 py-3">{{ $classification->parent_code ?? '-' }}</td>
                                            <td class="border-b border-slate-100 px-4 py-3">{{ $classification->letters_count }}</td>
                                            <td class="border-b border-slate-100 px-4 py-3">
                                                <div class="flex flex-wrap gap-2">
                                                    <button type="button" wire:click="editClassification({{ $classification->id }})" class="whitespace-nowrap rounded-lg border border-slate-200 px-3 py-1.5 font-bold hover:border-teal-600">Edit</button>
                                                    <button type="button" wire:click="deleteClassification({{ $classification->id }})" wire:confirm="Hapus kode klasifikasi arsip ini?" class="whitespace-nowrap rounded-lg border border-rose-200 px-3 py-1.5 font-bold text-rose-700 hover:bg-rose-50">Hapus</button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-4 py-10 text-center text-slate-500">Belum ada kode klasifikasi arsip.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($archiveClassifications->hasPages())
                            <div class="border-t border-slate-200 px-4 py-3">
                                {{ $archiveClassifications->links() }}
                            </div>
                        @endif
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
                                    @if ($numberMonitor['check_is_available'] && $numberMonitor['check_recommendation'])
                                        <div class="mt-2 rounded-lg bg-white/70 px-3 py-2 text-xs text-slate-700 ring-1 ring-black/5">
                                            <span class="font-bold">Rekomendasi tanggal lama:</span>
                                            {{ $numberMonitor['check_recommendation']['date']?->translatedFormat('d M Y') ?: '-' }}
                                            <div class="mt-1 font-normal">{{ $numberMonitor['check_recommendation']['note'] }}</div>
                                        </div>
                                    @endif
                                @else
                                    Masukkan nomor urut untuk dicek.
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
                        <div class="rounded-lg border border-slate-200 p-4">
                            <div class="font-bold">Nomor Kosong yang Bisa Dipakai</div>
                            @if ($numberMonitor['missing_items'])
                                <div class="mt-3 grid gap-2 md:grid-cols-2">
                                    @foreach ($numberMonitor['missing_items'] as $item)
                                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                                            <div class="flex items-start justify-between gap-2">
                                                <div>
                                                    <div class="text-sm font-bold text-amber-900">{{ str_pad((string) $item['sequence'], 3, '0', STR_PAD_LEFT) }}</div>
                                                    <div class="mt-1 text-xs font-semibold text-amber-800">Rekomendasi tanggal lama: {{ $item['recommended_date']?->translatedFormat('d M Y') ?: '-' }}</div>
                                                    <div class="mt-1 text-xs text-amber-700">{{ $item['recommendation_note'] }}</div>
                                                </div>
                                                <a href="{{ route('dashboard', ['create' => 'Keluar', 'unit' => $numberMonitorUnit, 'number' => $item['suggested_number'], 'letter_date' => $item['recommended_date']?->toDateString()]) }}"
                                                   class="shrink-0 rounded-lg bg-teal-700 px-3 py-1.5 text-xs font-bold text-white hover:bg-teal-800">
                                                    Pakai
                                                </a>
                                            </div>
                                            <div class="mt-2 break-all text-xs font-semibold text-slate-600">{{ $item['suggested_number'] }}</div>
                                        </div>
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
                            <div class="font-bold">Histori Tanggal Nomor Terpakai</div>
                            <div class="mt-3 space-y-3">
                                @forelse ($numberMonitor['recent_used'] as $item)
                                    <div class="border-l-4 border-teal-700 pl-3">
                                        <div class="text-sm font-bold">{{ str_pad((string) $item['sequence'], 3, '0', STR_PAD_LEFT) }} | {{ $item['number'] }}</div>
                                        <div class="mt-1 text-xs font-semibold text-slate-500">Tanggal surat: {{ $item['letter_date']?->translatedFormat('d M Y') ?: '-' }}</div>
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

                @if ($activeSettingsTab === 'android')
                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="border-b border-slate-200 pb-4">
                        <h2 class="text-lg font-bold">Aplikasi Android</h2>
                        <p class="text-sm text-slate-500">Atur versi aplikasi, update wajib, dan link unduhan APK yang tampil di halaman login serta dipakai aplikasi Android untuk cek update.</p>
                    </div>

                    <form wire:submit="saveMobileAndroid" class="mt-5 grid gap-4 md:grid-cols-2">
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Nama Versi Saat Ini
                            <input wire:model="mobileAndroid.current_version_name" type="text" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950" placeholder="Contoh: 1.0.0">
                            @error('mobileAndroid.current_version_name') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Kode Versi Saat Ini
                            <input wire:model="mobileAndroid.current_version_code" type="number" min="1" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                            @error('mobileAndroid.current_version_code') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Kode Versi Minimum
                            <input wire:model="mobileAndroid.minimum_version_code" type="number" min="1" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950">
                            <span class="text-xs font-normal text-slate-500">Jika versi aplikasi di bawah angka ini, update menjadi wajib.</span>
                            @error('mobileAndroid.minimum_version_code') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600">
                            Link Unduh APK
                            <input wire:model="mobileAndroid.download_url" type="url" class="min-h-11 rounded-lg border border-slate-200 px-3 text-slate-950" placeholder="https://esurat.simpelmrp.com/downloads/esurat-android.apk">
                            @error('mobileAndroid.download_url') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm font-bold text-slate-600 md:col-span-2">
                            Catatan Rilis
                            <textarea wire:model="mobileAndroid.release_notes" rows="4" class="rounded-lg border border-slate-200 px-3 py-2 text-slate-950" placeholder="Ringkasan perubahan versi Android terbaru."></textarea>
                            @error('mobileAndroid.release_notes') <span class="text-xs text-rose-600">{{ $message }}</span> @enderror
                        </label>

                        <div class="grid gap-3 rounded-lg border border-teal-100 bg-teal-50 p-4 md:col-span-2">
                            <div>
                                <div class="text-sm font-bold text-teal-800">Link publik saat ini</div>
                                <a href="{{ $mobileAndroid['download_url'] ?? '#' }}" target="_blank" class="mt-1 break-all text-sm font-semibold text-teal-900 underline">{{ $mobileAndroid['download_url'] ?? '-' }}</a>
                            </div>
                            <div class="text-sm text-teal-900">File APK production ditempatkan di <span class="font-bold">public/downloads/esurat-android.apk</span> agar dapat diakses dari domain.</div>
                        </div>

                        <div class="flex justify-end md:col-span-2">
                            <button type="submit" class="min-h-11 rounded-lg bg-teal-700 px-4 text-sm font-bold text-white hover:bg-teal-800">Simpan Setting Android</button>
                        </div>
                    </form>
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
