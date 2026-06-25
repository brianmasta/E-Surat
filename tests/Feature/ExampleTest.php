<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\ActivityLog;
use App\Models\ArchiveClassification;
use App\Models\DecisionLetterNumber;
use App\Models\DispositionRecipient;
use App\Models\Letter;
use App\Models\LetterAttachment;
use App\Models\User;
use App\Support\TaskInbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_mobile_version_endpoint_reports_available_and_required_update(): void
    {
        AppSetting::putValue('mobile_versions', [
            'android' => [
                'current_version_name' => '1.2.0',
                'current_version_code' => 12,
                'minimum_version_code' => 10,
                'download_url' => 'https://example.test/esurat.apk',
                'release_notes' => 'Perbaikan disposisi Android.',
            ],
        ]);

        $this->getJson('/api/mobile/version?platform=android&version_code=9')
            ->assertOk()
            ->assertJson([
                'platform' => 'android',
                'current_version_name' => '1.2.0',
                'current_version_code' => 12,
                'minimum_version_code' => 10,
                'update_available' => true,
                'update_required' => true,
            ]);
    }

    public function test_mobile_login_returns_token_and_can_open_dashboard(): void
    {
        $login = $this->postJson('/api/mobile/login', [
            'email' => 'admin@mrp-papuatengah.test',
            'password' => 'password',
            'device_name' => 'Android test',
        ]);

        $login
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.role', 'Admin Sekretariat');

        $token = $login->json('access_token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/mobile/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'summary' => ['letters_total', 'incoming_total', 'outgoing_total', 'task_total'],
                'tasks' => ['incoming', 'dispositions', 'approvals', 'deadlines'],
            ]);
    }

    public function test_mobile_numbering_endpoint_returns_auto_and_unused_numbers(): void
    {
        $admin = User::where('role', 'Admin Sekretariat')->firstOrFail();

        AppSetting::putValue('letter_numbering', [
            'prefix' => '800',
            'unit_code' => 'MOB',
            'separator' => '/',
            'include_month' => true,
            'include_year' => true,
            'next_sequence' => 4,
        ]);
        AppSetting::putValue('letter_units', [
            ['code' => 'MOB', 'name' => 'Mobile Test', 'description' => 'Unit uji mobile', 'is_default' => true],
        ]);

        foreach ([1, 3] as $sequence) {
            Letter::create([
                'type' => 'Keluar',
                'unit_code' => 'MOB',
                'number' => '800/'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT).'/MOB/'.now()->format('m').'/'.now()->format('Y'),
                'subject' => 'Surat mobile nomor '.$sequence,
                'external_party' => 'Instansi Tujuan',
                'letter_date' => now()->toDateString(),
                'status' => 'Selesai',
            ]);
        }

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/mobile/numbering?unit_code=MOB&year='.now()->format('Y'))
            ->assertOk()
            ->assertJsonPath('numbering.next_outgoing_number', '800/004/MOB/'.now()->format('m').'/'.now()->format('Y'))
            ->assertJsonPath('numbering.missing_count', 1)
            ->assertJsonPath('numbering.missing_items.0.sequence', 2);
    }

    public function test_mobile_disposition_can_send_to_multiple_recipients_with_scan(): void
    {
        Storage::fake('public');

        $secretary = User::where('role', 'Sekretaris Pribadi')->firstOrFail();
        $letter = Letter::where('type', 'Masuk')->where('status', 'Baru')->firstOrFail();
        $first = DispositionRecipient::where('name', 'Kepala Bagian Umum')->firstOrFail();
        $second = DispositionRecipient::create([
            'name' => 'Kepala Bagian Persidangan Mobile',
            'position' => 'Kepala Bagian',
            'unit' => 'Persidangan',
            'is_active' => true,
        ]);
        $scan = UploadedFile::fake()->create('scan-mobile.pdf', 80, 'application/pdf');

        $this->actingAs($secretary, 'sanctum')
            ->post('/api/mobile/letters/'.$letter->id.'/dispositions', [
                'recipient_ids' => [$first->id, $second->id],
                'instruction' => 'Mohon ditindaklanjuti dari aplikasi mobile.',
                'disposition_scan' => $scan,
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'dispositions')
            ->assertJsonPath('dispositions.0.input_method', 'Upload File Disposisi')
            ->assertJsonPath('dispositions.0.input_by_role', 'Sekretaris Pribadi');

        foreach ([$first, $second] as $recipient) {
            $this->assertDatabaseHas('dispositions', [
                'letter_id' => $letter->id,
                'disposition_recipient_id' => $recipient->id,
                'input_method' => 'Upload File Disposisi',
                'input_by_name' => $secretary->name,
            ]);
        }

        $disposition = $letter->fresh()->dispositions()->latest()->firstOrFail();
        Storage::disk('public')->assertExists($disposition->scan_path);
    }

    public function test_mobile_disposition_is_limited_to_incoming_letters(): void
    {
        $leader = User::where('role', 'Pimpinan MRP')->firstOrFail();
        $letter = Letter::where('type', 'Keluar')->firstOrFail();
        $recipient = DispositionRecipient::where('is_active', true)->firstOrFail();

        $this->actingAs($leader, 'sanctum')
            ->post('/api/mobile/letters/'.$letter->id.'/dispositions', [
                'recipient_ids' => [$recipient->id],
                'instruction' => 'Disposisi tidak boleh untuk surat keluar.',
            ])
            ->assertStatus(422);
    }

    public function test_mobile_can_create_standalone_disposition(): void
    {
        $leader = User::where('role', 'Pimpinan MRP')->firstOrFail();
        $recipient = DispositionRecipient::where('name', 'Kepala Bagian Umum')->firstOrFail();

        $this->actingAs($leader, 'sanctum')
            ->post('/api/mobile/dispositions', [
                'recipient_ids' => [$recipient->id],
                'instruction' => 'Arahan langsung pimpinan tanpa surat masuk.',
            ])
            ->assertCreated()
            ->assertJsonPath('disposition.letter_id', null)
            ->assertJsonPath('disposition.recipient_id', $recipient->id)
            ->assertJsonPath('disposition.letter', null);

        $this->assertDatabaseHas('dispositions', [
            'letter_id' => null,
            'recipient_name' => 'Kepala Bagian Umum',
            'disposition_recipient_id' => $recipient->id,
            'instruction' => 'Arahan langsung pimpinan tanpa surat masuk.',
        ]);
    }

    public function test_mobile_sk_numbering_can_create_update_delete_and_open_file(): void
    {
        Storage::fake('public');

        $admin = User::where('role', 'Admin Sekretariat')->firstOrFail();
        $year = (int) now()->format('Y');

        $create = $this->actingAs($admin, 'sanctum')
            ->post('/api/mobile/sk-numbers', [
                'unit_code' => 'SET-MRP',
                'classification_code' => '180.1',
                'year' => $year,
                'include_year' => '1',
                'title' => 'SK mobile dengan file',
                'decision_date' => now()->toDateString(),
                'sequence' => '1',
                'status' => 'Dipesan',
                'notes' => 'Dicatat dari aplikasi Android.',
                'file' => UploadedFile::fake()->create('sk-mobile.pdf', 80, 'application/pdf'),
            ])
            ->assertCreated()
            ->assertJsonPath('record.number', '180.1/001/SET-MRP/'.$year)
            ->assertJsonPath('record.has_file', true);

        $record = DecisionLetterNumber::findOrFail($create->json('record.id'));
        Storage::disk('public')->assertExists($record->file_path);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/mobile/sk-numbers?unit_code=SET-MRP&year='.$year)
            ->assertOk()
            ->assertJsonPath('settings.classification_code', '180.1')
            ->assertJsonPath('data.0.number', '180.1/001/SET-MRP/'.$year);

        $this->actingAs($admin, 'sanctum')
            ->get(route('api.mobile.sk-numbers.file', $record, false))
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->post('/api/mobile/sk-numbers/'.$record->id, [
                'unit_code' => 'SET-MRP',
                'classification_code' => '180.2',
                'year' => $year,
                'include_year' => '0',
                'title' => 'SK mobile diperbarui',
                'decision_date' => now()->toDateString(),
                'sequence' => '2',
                'status' => 'Dipakai',
                'notes' => 'Diperbarui tanpa upload file baru.',
            ])
            ->assertOk()
            ->assertJsonPath('record.number', '180.2/002/SET-MRP')
            ->assertJsonPath('record.has_file', true);

        $record->refresh();
        $filePath = $record->file_path;

        $this->assertDatabaseHas('decision_letter_numbers', [
            'id' => $record->id,
            'classification_code' => '180.2',
            'number' => '180.2/002/SET-MRP',
            'title' => 'SK mobile diperbarui',
            'file_path' => $filePath,
        ]);
        Storage::disk('public')->assertExists($filePath);

        $this->actingAs($admin, 'sanctum')
            ->delete('/api/mobile/sk-numbers/'.$record->id)
            ->assertOk();

        $this->assertDatabaseMissing('decision_letter_numbers', [
            'id' => $record->id,
        ]);
        Storage::disk('public')->assertMissing($filePath);
    }

    public function test_my_tasks_requires_login(): void
    {
        $this->get('/tugas-saya')->assertRedirect(route('login'));
    }

    public function test_settings_requires_admin_role(): void
    {
        $this->actingAs(User::where('role', 'Staf Sekretariat')->first());

        $this->get('/settings')->assertForbidden();
    }

    public function test_leadership_page_requires_leader_role(): void
    {
        $this->actingAs(User::where('role', 'Pimpinan MRP')->first());

        $this->get('/pimpinan')->assertOk();

        $this->actingAs(User::where('role', 'Sekretaris Pribadi')->first());

        $this->get('/pimpinan')->assertOk();

        $this->actingAs(User::where('role', 'Staf Sekretariat')->first());

        $this->get('/pimpinan')->assertForbidden();
    }

    public function test_department_head_page_requires_department_head_role(): void
    {
        $this->actingAs(User::where('role', 'Kepala Bagian')->first());

        $this->get('/kepala-bagian')->assertOk();

        $this->actingAs(User::where('role', 'Staf Sekretariat')->first());

        $this->get('/kepala-bagian')->assertForbidden();

        $this->actingAs(User::where('role', 'Pimpinan MRP')->first());

        $this->get('/kepala-bagian')->assertForbidden();
    }

    public function test_dashboard_menu_is_limited_by_role(): void
    {
        $this->actingAs(User::where('role', 'Staf Sekretariat')->first());

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Halaman Pimpinan')
            ->assertDontSee('Halaman Kepala Bagian')
            ->assertDontSee('Setting');

        $this->actingAs(User::where('role', 'Pimpinan MRP')->first());

        $this->get('/')
            ->assertOk()
            ->assertSee('Halaman Pimpinan')
            ->assertDontSee('Halaman Kepala Bagian')
            ->assertDontSee('Setting');

        $this->actingAs(User::where('role', 'Sekretaris Pribadi')->first());

        $this->get('/')
            ->assertOk()
            ->assertSee('Halaman Pimpinan')
            ->assertDontSee('Halaman Kepala Bagian')
            ->assertDontSee('Setting');

        $this->actingAs(User::where('role', 'Kepala Bagian')->first());

        $this->get('/')
            ->assertOk()
            ->assertSee('Halaman Kepala Bagian')
            ->assertDontSee('Halaman Pimpinan')
            ->assertDontSee('Setting');
    }

    public function test_leader_inbox_shows_incoming_letters_waiting_for_disposition(): void
    {
        $leader = User::where('role', 'Pimpinan MRP')->firstOrFail();
        $this->actingAs($leader);

        Letter::create([
            'type' => 'Masuk',
            'unit_code' => 'SET-MRP',
            'agenda_number' => 'AG/INBOX/'.now()->format('Y').'/0001',
            'number' => 'SM/INBOX/001',
            'subject' => 'Surat perlu disposisi inbox',
            'external_party' => 'Biro Umum',
            'letter_date' => now()->toDateString(),
            'received_date' => now()->toDateString(),
            'nature' => 'Biasa',
            'urgency' => 'Normal',
            'status' => 'Baru',
        ]);

        $this->assertGreaterThanOrEqual(1, TaskInbox::countFor($leader));

        $this->get('/tugas-saya')
            ->assertOk()
            ->assertSee('Inbox / Tugas Saya')
            ->assertSee('Surat perlu disposisi inbox')
            ->assertSee('Menunggu Disposisi');
    }

    public function test_department_head_inbox_shows_assigned_dispositions(): void
    {
        $departmentHead = User::where('role', 'Kepala Bagian')->firstOrFail();
        $this->actingAs($departmentHead);

        $letter = Letter::where('type', 'Masuk')->firstOrFail();
        $letter->dispositions()->create([
            'sender_name' => 'Pimpinan',
            'recipient_name' => $departmentHead->name,
            'instruction' => 'Mohon tindak lanjuti dari inbox.',
            'status' => 'Belum Dibaca',
        ]);

        $this->assertGreaterThanOrEqual(1, TaskInbox::countFor($departmentHead));

        $this->get('/tugas-saya')
            ->assertOk()
            ->assertSee('Disposisi untuk Saya')
            ->assertSee('Mohon tindak lanjuti dari inbox.');
    }

    public function test_admin_can_manage_users_and_inactive_user_cannot_login(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        Livewire::test('settings')
            ->set('userName', 'Operator Agenda')
            ->set('userEmail', 'operator@mrp-papuatengah.test')
            ->set('userRole', 'Staf Sekretariat')
            ->set('userPassword', 'password')
            ->call('saveUser');

        $user = User::where('email', 'operator@mrp-papuatengah.test')->firstOrFail();

        Livewire::test('settings')
            ->call('toggleUser', $user->id);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'user.status_updated',
            'subject_id' => $user->id,
        ]);

        Auth::logout();

        Livewire::test('login')
            ->set('email', 'operator@mrp-papuatengah.test')
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors(['email']);
    }

    public function test_agency_profile_can_customize_application_identity(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        Livewire::test('settings')
            ->set('agency', [
                'app_name' => 'E-Surat Setda',
                'short_name' => 'SETDA',
                'name' => 'Sekretariat Daerah Kabupaten Contoh',
                'unit' => 'Bagian Umum',
                'leader_title' => 'Sekretaris Daerah',
                'city' => 'Kota Contoh',
                'address' => 'Jl. Pemerintahan No. 1',
                'email' => 'setda@example.go.id',
                'phone' => '0900-0000',
            ])
            ->call('saveAgency')
            ->assertHasNoErrors();

        $this->assertSame('E-Surat Setda', AppSetting::agency()['app_name']);
        $this->assertSame('SETDA', AppSetting::agency()['short_name']);
        $this->assertSame('Sekretaris Daerah', AppSetting::agency()['leader_title']);

        $this->get('/')
            ->assertOk()
            ->assertSee('E-Surat Setda')
            ->assertSee('Sekretariat Daerah Kabupaten Contoh');

        Livewire::test('dashboard')
            ->call('openLetterForm', 'Keluar', 'SET-MRP')
            ->assertSet('signerTitle', 'Sekretaris Daerah');
    }

    public function test_letter_units_can_be_created_edited_used_and_deleted_when_unused(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        Livewire::test('settings')
            ->call('setSettingsTab', 'unit')
            ->assertSee('Unit Surat')
            ->set('unitCode', 'BUP')
            ->set('unitName', 'Bupati')
            ->set('unitDescription', 'Unit surat kepala daerah')
            ->set('unitIsDefault', true)
            ->call('saveUnit')
            ->assertHasNoErrors()
            ->call('editUnit', 2)
            ->assertSet('unitCode', 'BUP')
            ->set('unitName', 'Bupati dan Wakil Bupati')
            ->call('saveUnit')
            ->assertHasNoErrors();

        $this->assertContains('BUP', AppSetting::letterUnitCodes());
        $this->assertSame('BUP', AppSetting::defaultLetterUnitCode());
        $this->assertSame('BUP', AppSetting::getValue('letter_numbering')['unit_code']);

        Livewire::test('dashboard')
            ->call('openLetterForm', 'Keluar')
            ->assertSet('unitCode', 'BUP')
            ->assertSet('number', '800/019/BUP/'.now()->format('m').'/'.now()->format('Y'));

        Livewire::test('settings')
            ->call('setSettingsTab', 'unit')
            ->call('deleteUnit', 2);

        $this->assertNotContains('BUP', AppSetting::letterUnitCodes());
    }

    public function test_letter_unit_code_can_use_spaces(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        Livewire::test('settings')
            ->call('setSettingsTab', 'unit')
            ->set('unitCode', 'bag   umum')
            ->set('unitName', 'Bagian Umum')
            ->set('unitDescription', 'Unit persuratan bagian umum')
            ->set('unitIsDefault', true)
            ->call('saveUnit')
            ->assertHasNoErrors();

        $this->assertContains('BAG UMUM', AppSetting::letterUnitCodes());
        $this->assertSame('BAG UMUM', AppSetting::defaultLetterUnitCode());

        Livewire::test('dashboard')
            ->call('openLetterForm', 'Keluar')
            ->assertSet('unitCode', 'BAG UMUM')
            ->assertSet('number', '800/019/BAG UMUM/'.now()->format('m').'/'.now()->format('Y'));
    }

    public function test_archive_classification_codes_can_be_managed_in_settings(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        Livewire::test('settings')
            ->call('setSettingsTab', 'kode-arsip')
            ->assertSee('Kode Klasifikasi Arsip')
            ->set('classificationCode', '990.1')
            ->set('classificationName', 'Urusan Khusus Pengujian')
            ->set('classificationParentCode', '990')
            ->set('classificationDescription', 'Kode arsip untuk pengujian.')
            ->call('saveClassification')
            ->assertHasNoErrors();

        $classification = ArchiveClassification::where('code', '990.1')->firstOrFail();

        $this->assertDatabaseHas('archive_classifications', [
            'code' => '990.1',
            'name' => 'Urusan Khusus Pengujian',
            'parent_code' => '990',
        ]);

        Livewire::test('settings')
            ->call('setSettingsTab', 'kode-arsip')
            ->call('editClassification', $classification->id)
            ->assertSet('classificationCode', '990.1')
            ->set('classificationName', 'Urusan Khusus Setelah Edit')
            ->call('saveClassification')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('archive_classifications', [
            'code' => '990.1',
            'name' => 'Urusan Khusus Setelah Edit',
        ]);

        Livewire::test('settings')
            ->call('setSettingsTab', 'kode-arsip')
            ->call('deleteClassification', $classification->id);

        $this->assertDatabaseMissing('archive_classifications', [
            'code' => '990.1',
        ]);
    }

    public function test_archive_classification_codes_can_be_imported_from_csv(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        ArchiveClassification::create([
            'code' => '991',
            'name' => 'Kode Arsip Lama',
            'description' => 'Data lama tidak boleh tertimpa.',
        ]);

        $csv = "kode,nama,kode_induk,deskripsi\n991,Import Kode Arsip,,Induk import\n991.1,Import Sub Kode,991,Turunan import\n991.1,Import Sub Kode Duplikat,991,Duplikat dalam file\n991.2,Import Sub Kode Kedua,991,Turunan kedua\n";

        $component = Livewire::test('settings')
            ->call('setSettingsTab', 'kode-arsip')
            ->set('classificationImport', UploadedFile::fake()->createWithContent('kode-arsip.csv', $csv))
            ->call('previewClassificationImport')
            ->assertHasNoErrors()
            ->assertSee('Preview Data Import')
            ->assertSee('Duplikat')
            ->assertSeeHtml('bg-rose-50')
            ->assertSee('Kode sudah ada di database')
            ->assertSee('Duplikat di file import');

        $this->assertDatabaseMissing('archive_classifications', [
            'code' => '991.1',
        ]);

        $component->call('confirmClassificationImport');

        $this->assertDatabaseHas('archive_classifications', [
            'code' => '991',
            'name' => 'Kode Arsip Lama',
            'description' => 'Data lama tidak boleh tertimpa.',
        ]);
        $this->assertDatabaseHas('archive_classifications', [
            'code' => '991.1',
            'name' => 'Import Sub Kode',
            'parent_code' => '991',
        ]);
        $this->assertDatabaseHas('archive_classifications', [
            'code' => '991.2',
            'name' => 'Import Sub Kode Kedua',
            'parent_code' => '991',
        ]);
        $this->assertSame(1, ArchiveClassification::where('code', '991.1')->count());
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'classification.imported',
        ]);
        $this->assertStringContainsString('2 data duplikat dilewati', ActivityLog::where('action', 'classification.imported')->latest()->value('description'));
    }

    public function test_archive_classification_table_is_paginated(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        for ($i = 1; $i <= 12; $i++) {
            ArchiveClassification::create([
                'code' => '993.'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'name' => 'Kode paginasi arsip '.$i,
            ]);
        }

        Livewire::test('settings')
            ->call('setSettingsTab', 'kode-arsip')
            ->assertSee('Menampilkan 1-10 dari')
            ->assertSee('Per halaman')
            ->set('classificationPerPage', 25)
            ->assertSee('Menampilkan 1-25 dari');
    }

    public function test_archive_classification_table_can_be_searched_and_filtered(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        ArchiveClassification::create([
            'code' => '994',
            'name' => 'Filter Induk Pengujian',
        ]);
        ArchiveClassification::create([
            'code' => '994.1',
            'name' => 'Filter Dipakai Pengujian',
            'parent_code' => '994',
        ]);
        ArchiveClassification::create([
            'code' => '994.2',
            'name' => 'Filter Belum Dipakai Pengujian',
            'parent_code' => '994',
        ]);
        Letter::create([
            'type' => 'Masuk',
            'unit_code' => 'SET-MRP',
            'classification_code' => '994.1',
            'agenda_number' => 'AG/FILTER-KODE/'.now()->format('Y').'/0001',
            'number' => 'SM/FILTER-KODE/001',
            'subject' => 'Surat pemakai kode arsip',
            'external_party' => 'Biro Umum',
            'letter_date' => now()->toDateString(),
            'received_date' => now()->toDateString(),
            'status' => 'Baru',
        ]);

        Livewire::test('settings')
            ->call('setSettingsTab', 'kode-arsip')
            ->set('classificationSearch', 'Filter Dipakai')
            ->assertSee('994.1')
            ->assertDontSee('994.2')
            ->set('classificationSearch', '994')
            ->set('classificationUsageFilter', 'Dipakai')
            ->assertSee('994.1')
            ->assertDontSee('994.2')
            ->set('classificationUsageFilter', 'Belum Dipakai')
            ->assertSee('994.2')
            ->assertDontSee('994.1')
            ->set('classificationParentFilter', 'Induk Utama')
            ->assertSee('Filter Induk Pengujian')
            ->call('resetClassificationFilters')
            ->assertSet('classificationSearch', '')
            ->assertSet('classificationParentFilter', 'Semua')
            ->assertSet('classificationUsageFilter', 'Semua');
    }

    public function test_archive_classification_import_preview_is_paginated(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        $csv = "kode,nama,kode_induk,deskripsi\n";
        for ($i = 1; $i <= 12; $i++) {
            $number = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $csv .= '996.'.$number.',Preview Baris '.$number.',996,Data preview '.$number."\n";
        }

        Livewire::test('settings')
            ->call('setSettingsTab', 'kode-arsip')
            ->set('classificationImport', UploadedFile::fake()->createWithContent('preview-kode-arsip.csv', $csv))
            ->call('previewClassificationImport')
            ->assertSee('Menampilkan 1-10 dari 12 data preview')
            ->assertSee('Preview Baris 01')
            ->assertDontSee('Preview Baris 12')
            ->call('setClassificationPreviewPage', 2)
            ->assertSee('Halaman 2 dari 2')
            ->assertSee('Preview Baris 12')
            ->assertDontSee('Preview Baris 01');
    }

    public function test_archive_classification_import_template_can_be_downloaded(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        Livewire::test('settings')
            ->call('setSettingsTab', 'kode-arsip')
            ->assertSee('Download Template');

        $response = $this->get(route('archive-classifications.import-template'));
        $response
            ->assertOk()
            ->assertDownload('template-import-kode-arsip.xlsx')
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'classification.template_downloaded',
        ]);
    }

    public function test_used_letter_unit_code_cannot_be_changed_or_deleted(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        AppSetting::putValue('letter_units', [
            ...AppSetting::letterUnits(),
            ['code' => 'ARSIP', 'name' => 'Bidang Arsip', 'description' => 'Unit arsip', 'is_default' => false],
        ]);

        Letter::create([
            'type' => 'Masuk',
            'unit_code' => 'ARSIP',
            'agenda_number' => 'AG/ARSIP/'.now()->format('Y').'/0001',
            'number' => 'SM/ARSIP/001',
            'subject' => 'Surat unit arsip',
            'external_party' => 'Biro Umum',
            'letter_date' => now()->toDateString(),
            'received_date' => now()->toDateString(),
            'nature' => 'Biasa',
            'urgency' => 'Normal',
            'status' => 'Baru',
        ]);

        Livewire::test('settings')
            ->call('setSettingsTab', 'unit')
            ->call('editUnit', 2)
            ->set('unitCode', 'ARSIP-BARU')
            ->call('saveUnit')
            ->assertHasErrors(['unitCode']);

        Livewire::test('settings')
            ->call('setSettingsTab', 'unit')
            ->call('deleteUnit', 2);

        $this->assertContains('ARSIP', AppSetting::letterUnitCodes());
    }

    public function test_outgoing_letter_number_uses_configured_next_sequence(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        AppSetting::putValue('letter_numbering', [
            'prefix' => '900',
            'unit_code' => 'SET-MRP',
            'separator' => '/',
            'include_month' => true,
            'include_year' => true,
            'next_sequence' => 42,
        ]);

        Livewire::test('dashboard')
            ->call('openLetterForm', 'Keluar', 'MRP')
            ->assertSet('number', '900/042/MRP/'.now()->format('m').'/'.now()->format('Y'));
    }

    public function test_outgoing_letter_jump_updates_next_sequence(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        AppSetting::putValue('letter_numbering', [
            'prefix' => '800',
            'unit_code' => 'SET-MRP',
            'separator' => '/',
            'include_month' => true,
            'include_year' => true,
            'next_sequence' => 19,
        ]);

        Livewire::test('dashboard')
            ->call('openLetterForm', 'Keluar', 'SET-MRP')
            ->set('number', '800/050/SET-MRP/'.now()->format('m').'/'.now()->format('Y'))
            ->set('subject', 'Surat keluar dengan nomor lompat')
            ->set('externalParty', 'Biro Umum')
            ->call('saveLetter');

        $this->assertDatabaseHas('letters', [
            'type' => 'Keluar',
            'number' => '800/050/SET-MRP/'.now()->format('m').'/'.now()->format('Y'),
        ]);

        $this->assertSame(51, AppSetting::getValue('letter_numbering')['next_sequence']);
    }

    public function test_settings_can_monitor_unused_outgoing_letter_sequences(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        AppSetting::putValue('letter_numbering', [
            'prefix' => '800',
            'unit_code' => 'SET-MRP',
            'separator' => '/',
            'include_month' => true,
            'include_year' => true,
            'next_sequence' => 6,
        ]);

        $letterDates = [
            1 => now()->subDays(10),
            3 => now()->subDays(8),
            5 => now()->subDays(6),
        ];

        foreach ([1, 3, 5] as $sequence) {
            Letter::create([
                'type' => 'Keluar',
                'unit_code' => 'SET-MRP',
                'number' => '800/'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT).'/SET-MRP/'.$letterDates[$sequence]->format('m').'/'.$letterDates[$sequence]->format('Y'),
                'subject' => 'Surat monitoring nomor '.$sequence,
                'external_party' => 'Biro Umum',
                'letter_date' => $letterDates[$sequence]->toDateString(),
                'status' => 'Selesai',
            ]);
        }

        Livewire::test('settings')
            ->call('setSettingsTab', 'monitoring-nomor')
            ->set('numberMonitorUnit', 'SET-MRP')
            ->set('numberMonitorYear', (int) now()->format('Y'))
            ->set('numberMonitorCheck', '4')
            ->assertSee('Monitoring Nomor Surat Keluar')
            ->assertSee('Histori Tanggal Nomor Terpakai')
            ->assertSee('Tanggal surat:')
            ->assertSee('Rekomendasi tanggal lama')
            ->assertSee($letterDates[3]->translatedFormat('d M Y'))
            ->assertSee('Pakai')
            ->assertSee('002')
            ->assertSee('004')
            ->assertSee('Nomor 004')
            ->assertSee('masih kosong');
    }

    public function test_number_monitor_page_shows_unused_outgoing_letter_sequences(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        AppSetting::putValue('letter_numbering', [
            'prefix' => '800',
            'unit_code' => 'SET-MRP',
            'separator' => '/',
            'include_month' => true,
            'include_year' => true,
            'next_sequence' => 6,
        ]);

        $letterDates = [
            1 => now()->subDays(10),
            3 => now()->subDays(8),
            5 => now()->subDays(6),
        ];

        foreach ([1, 3, 5] as $sequence) {
            Letter::create([
                'type' => 'Keluar',
                'unit_code' => 'SET-MRP',
                'number' => '800/'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT).'/SET-MRP/'.$letterDates[$sequence]->format('m').'/'.$letterDates[$sequence]->format('Y'),
                'subject' => 'Surat monitoring halaman '.$sequence,
                'external_party' => 'Biro Umum',
                'letter_date' => $letterDates[$sequence]->toDateString(),
                'status' => 'Selesai',
            ]);
        }

        $this->get('/monitoring-nomor-surat')
            ->assertOk()
            ->assertSee('Monitoring Nomor Surat');

        Livewire::test('number-monitor-page')
            ->set('unitCode', 'SET-MRP')
            ->set('year', (int) now()->format('Y'))
            ->set('checkSequence', '4')
            ->assertSee('Nomor Kosong yang Bisa Dipakai')
            ->assertSee('Histori Nomor Terpakai')
            ->assertSee('Rekomendasi tanggal lama')
            ->assertSee('002')
            ->assertSee('004')
            ->assertSee('Nomor 004')
            ->assertSee('masih kosong')
            ->assertSee('Pakai');
    }

    public function test_number_monitor_page_requires_admin_role(): void
    {
        $this->actingAs(User::where('role', 'Staf Sekretariat')->first());

        $this->get('/monitoring-nomor-surat')->assertForbidden();
    }

    public function test_sk_numbering_page_can_create_and_track_decision_numbers(): void
    {
        Storage::fake('public');

        $admin = User::where('role', 'Admin Sekretariat')->firstOrFail();
        $this->actingAs($admin);

        DecisionLetterNumber::create([
            'unit_code' => 'SET-MRP',
            'sequence' => 1,
            'year' => (int) now()->format('Y'),
            'number' => 'SK/001/SET-MRP/'.now()->format('Y'),
            'title' => 'SK pembanding nomor awal',
            'decision_date' => now()->subDays(2)->toDateString(),
            'status' => 'Dipakai',
            'created_by' => $admin->id,
        ]);
        DecisionLetterNumber::create([
            'unit_code' => 'SET-MRP',
            'sequence' => 3,
            'year' => (int) now()->format('Y'),
            'number' => 'SK/003/SET-MRP/'.now()->format('Y'),
            'title' => 'SK pembanding nomor lompat',
            'decision_date' => now()->subDay()->toDateString(),
            'status' => 'Dipesan',
            'created_by' => $admin->id,
        ]);

        $this->get('/penomoran-sk')
            ->assertOk()
            ->assertSee('Penomoran Surat Keputusan');

        Livewire::test('sk-numbering-page')
            ->set('unitCode', 'SET-MRP')
            ->set('year', (int) now()->format('Y'))
            ->call('openNumberModal')
            ->assertSet('showNumberModal', true)
            ->assertSee('SK/002/SET-MRP/'.now()->format('Y'))
            ->set('sequenceOverride', '2')
            ->set('title', 'Penetapan panitia kerja')
            ->set('decisionDate', now()->toDateString())
            ->set('status', 'Dipesan')
            ->set('notes', 'Dicatat dari halaman penomoran SK')
            ->set('decisionFile', UploadedFile::fake()->create('sk-panitia.pdf', 96, 'application/pdf'))
            ->call('saveNumber')
            ->assertHasNoErrors();

        $record = DecisionLetterNumber::where('number', 'SK/002/SET-MRP/'.now()->format('Y'))->firstOrFail();

        $this->assertDatabaseHas('decision_letter_numbers', [
            'unit_code' => 'SET-MRP',
            'sequence' => 2,
            'year' => (int) now()->format('Y'),
            'number' => 'SK/002/SET-MRP/'.now()->format('Y'),
            'title' => 'Penetapan panitia kerja',
            'status' => 'Dipesan',
            'file_original_name' => 'sk-panitia.pdf',
            'created_by' => $admin->id,
        ]);

        Storage::disk('public')->assertExists($record->file_path);
        $this->get(route('sk-numbering.file.review', $record))->assertOk();
        $this->get(route('sk-numbering.file.download', $record))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_sk_numbering_year_can_be_disabled_from_number_format(): void
    {
        $admin = User::where('role', 'Admin Sekretariat')->firstOrFail();
        $this->actingAs($admin);

        Livewire::test('sk-numbering-page')
            ->set('unitCode', 'SET-MRP')
            ->set('includeYear', false)
            ->call('openNumberModal')
            ->assertSet('showNumberModal', true)
            ->assertSee('SK/001/SET-MRP')
            ->assertDontSee('SK/001/SET-MRP/'.now()->format('Y'))
            ->set('title', 'SK tanpa komponen tahun')
            ->set('decisionDate', now()->toDateString())
            ->call('saveNumber')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('decision_letter_numbers', [
            'unit_code' => 'SET-MRP',
            'sequence' => 1,
            'year' => (int) now()->format('Y'),
            'number' => 'SK/001/SET-MRP',
            'title' => 'SK tanpa komponen tahun',
        ]);
        $this->assertFalse(AppSetting::getValue('sk_numbering')['include_year']);
    }

    public function test_sk_numbering_classification_code_can_be_edited(): void
    {
        $admin = User::where('role', 'Admin Sekretariat')->firstOrFail();
        $this->actingAs($admin);

        Livewire::test('sk-numbering-page')
            ->call('openNumberModal')
            ->set('classificationCode', '180.1')
            ->assertSee('180.1/001/SET-MRP/'.now()->format('Y'))
            ->set('title', 'SK dengan kode klasifikasi khusus')
            ->set('decisionDate', now()->toDateString())
            ->call('saveNumber')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('decision_letter_numbers', [
            'classification_code' => '180.1',
            'sequence' => 1,
            'number' => '180.1/001/SET-MRP/'.now()->format('Y'),
            'title' => 'SK dengan kode klasifikasi khusus',
        ]);
        $this->assertSame('180.1', AppSetting::getValue('sk_numbering')['classification_code']);
    }

    public function test_sk_numbering_page_can_detail_edit_and_delete_number(): void
    {
        Storage::fake('public');

        $admin = User::where('role', 'Admin Sekretariat')->firstOrFail();
        $this->actingAs($admin);
        Storage::disk('public')->put('file-sk/sk-uji.pdf', 'File SK uji');

        $record = DecisionLetterNumber::create([
            'unit_code' => 'SET-MRP',
            'sequence' => 7,
            'year' => (int) now()->format('Y'),
            'number' => 'SK/007/SET-MRP/'.now()->format('Y'),
            'title' => 'SK untuk uji aksi',
            'decision_date' => now()->toDateString(),
            'status' => 'Dipesan',
            'notes' => 'Catatan awal SK',
            'file_path' => 'file-sk/sk-uji.pdf',
            'file_original_name' => 'sk-uji.pdf',
            'file_mime_type' => 'application/pdf',
            'file_size' => 120,
            'created_by' => $admin->id,
        ]);

        Livewire::test('sk-numbering-page')
            ->call('openDetailModal', $record->id)
            ->assertSet('showDetailModal', true)
            ->assertSee('Detail SK')
            ->assertSee('Buka File SK')
            ->call('openEditModal', $record->id)
            ->assertSet('showNumberModal', true)
            ->assertSet('editingRecordId', $record->id)
            ->set('title', 'SK untuk uji aksi diperbarui')
            ->set('status', 'Dipakai')
            ->set('notes', 'Catatan diperbarui tanpa upload file baru')
            ->call('saveNumber')
            ->assertHasNoErrors()
            ->assertSet('showNumberModal', false);

        $this->assertDatabaseHas('decision_letter_numbers', [
            'id' => $record->id,
            'title' => 'SK untuk uji aksi diperbarui',
            'status' => 'Dipakai',
            'file_path' => 'file-sk/sk-uji.pdf',
        ]);
        Storage::disk('public')->assertExists('file-sk/sk-uji.pdf');

        Livewire::test('sk-numbering-page')
            ->call('deleteNumber', $record->id);

        $this->assertDatabaseMissing('decision_letter_numbers', [
            'id' => $record->id,
        ]);
        Storage::disk('public')->assertMissing('file-sk/sk-uji.pdf');
    }

    public function test_sk_numbering_page_requires_admin_role(): void
    {
        $this->actingAs(User::where('role', 'Staf Sekretariat')->first());

        $this->get('/penomoran-sk')->assertForbidden();
    }

    public function test_dashboard_keeps_number_monitoring_available_without_header_shortcut(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        Livewire::test('dashboard')
            ->assertSee('Dasbor Persuratan')
            ->assertDontSee('Monitoring Surat');

        $this->get('/monitoring-nomor-surat')
            ->assertOk()
            ->assertSee('Monitoring Nomor Surat');
    }

    public function test_available_number_recommendation_can_prefill_outgoing_letter_form(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        $oldDate = now()->subDays(8)->toDateString();
        $number = '800/004/SET-MRP/'.now()->subDays(8)->format('m').'/'.now()->subDays(8)->format('Y');

        Livewire::withQueryParams([
            'create' => 'Keluar',
            'unit' => 'SET-MRP',
            'number' => $number,
            'letter_date' => $oldDate,
        ])
            ->test('dashboard')
            ->assertSet('showLetterForm', true)
            ->assertSet('type', 'Keluar')
            ->assertSet('unitCode', 'SET-MRP')
            ->assertSet('number', $number)
            ->assertSet('letterDate', $oldDate);
    }

    public function test_archive_classification_can_be_saved_on_letter(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        Livewire::test('dashboard')
            ->call('openLetterForm', 'Keluar', 'SET-MRP')
            ->set('classificationCode', '000.1.2')
            ->assertSet('number', '000.1.2/019/SET-MRP/'.now()->format('m').'/'.now()->format('Y'))
            ->set('subject', 'Surat tugas perjalanan dinas')
            ->set('externalParty', 'Bagian Umum')
            ->call('saveLetter');

        $this->assertDatabaseHas('letters', [
            'type' => 'Keluar',
            'classification_code' => '000.1.2',
            'number' => '000.1.2/019/SET-MRP/'.now()->format('m').'/'.now()->format('Y'),
        ]);
    }

    public function test_outgoing_letter_template_fields_can_be_saved(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        Livewire::test('dashboard')
            ->call('openLetterForm', 'Keluar', 'SET-MRP')
            ->set('subject', 'Permohonan fasilitasi rapat')
            ->set('externalParty', 'Sekretaris Daerah Provinsi Papua Tengah')
            ->set('outgoingBody', "Dengan hormat,\n\nMohon fasilitasi rapat koordinasi sesuai jadwal terlampir.")
            ->set('signerName', 'Drs. Pejabat Penandatangan')
            ->set('signerTitle', 'Ketua MRP Papua Tengah')
            ->call('saveLetter');

        $this->assertDatabaseHas('letters', [
            'type' => 'Keluar',
            'outgoing_input_mode' => 'template',
            'subject' => 'Permohonan fasilitasi rapat',
            'external_party' => 'Sekretaris Daerah Provinsi Papua Tengah',
            'outgoing_body' => "Dengan hormat,\n\nMohon fasilitasi rapat koordinasi sesuai jadwal terlampir.",
            'signer_name' => 'Drs. Pejabat Penandatangan',
            'signer_title' => 'Ketua MRP Papua Tengah',
        ]);
    }

    public function test_outgoing_letter_can_be_created_by_uploading_finished_document(): void
    {
        Storage::fake('public');
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        Livewire::test('dashboard')
            ->call('openLetterForm', 'Keluar', 'SET-MRP')
            ->set('outgoingInputMode', 'upload')
            ->set('subject', 'Surat keluar final hasil unggahan')
            ->set('externalParty', 'Biro Pemerintahan')
            ->set('outgoingBody', '')
            ->set('document', UploadedFile::fake()->create('surat-jadi.pdf', 120, 'application/pdf'))
            ->call('saveLetter');

        $letter = Letter::where('subject', 'Surat keluar final hasil unggahan')->firstOrFail();

        $this->assertSame('upload', $letter->outgoing_input_mode);
        $this->assertNull($letter->outgoing_body);
        $this->assertNotNull($letter->file_path);
        Storage::disk('public')->assertExists($letter->file_path);
    }

    public function test_upload_mode_requires_finished_document(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        Livewire::test('dashboard')
            ->call('openLetterForm', 'Keluar', 'SET-MRP')
            ->set('outgoingInputMode', 'upload')
            ->set('subject', 'Surat keluar tanpa file final')
            ->set('externalParty', 'Biro Pemerintahan')
            ->set('outgoingBody', '')
            ->call('saveLetter')
            ->assertHasErrors(['document']);
    }

    public function test_admin_can_edit_letter_data(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        $letter = Letter::create([
            'type' => 'Masuk',
            'unit_code' => 'SET-MRP',
            'agenda_number' => 'AG-EDIT-001',
            'number' => 'SM/EDIT/001',
            'subject' => 'Surat sebelum edit',
            'external_party' => 'Biro Umum',
            'letter_date' => now()->toDateString(),
            'received_date' => now()->toDateString(),
            'nature' => 'Biasa',
            'urgency' => 'Normal',
            'status' => 'Baru',
        ]);

        Livewire::test('dashboard')
            ->call('openEditLetterForm', $letter->id)
            ->assertSet('editingLetterId', $letter->id)
            ->assertSet('subject', 'Surat sebelum edit')
            ->set('subject', 'Surat setelah edit')
            ->set('urgency', 'Segera')
            ->call('saveLetter');

        $this->assertDatabaseHas('letters', [
            'id' => $letter->id,
            'subject' => 'Surat setelah edit',
            'urgency' => 'Segera',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'letter.updated',
            'subject_id' => $letter->id,
        ]);
    }

    public function test_editing_letter_keeps_existing_number_and_document_when_not_replaced(): void
    {
        Storage::fake('public');
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        Storage::disk('public')->put('dokumen-surat/file-lama.pdf', 'file lama');

        $letter = Letter::create([
            'type' => 'Keluar',
            'outgoing_input_mode' => 'upload',
            'unit_code' => 'SET-MRP',
            'classification_code' => '000.1.1',
            'number' => '800/555/SET-MRP/'.now()->format('m').'/'.now()->format('Y'),
            'subject' => 'Surat keluar sebelum edit',
            'external_party' => 'Biro Umum',
            'letter_date' => now()->toDateString(),
            'file_path' => 'dokumen-surat/file-lama.pdf',
            'status' => 'Selesai',
        ]);

        Livewire::test('dashboard')
            ->call('openEditLetterForm', $letter->id)
            ->assertSee('File lama masih tersimpan')
            ->assertSee('file-lama.pdf')
            ->assertSee('Review')
            ->assertSee('Download')
            ->set('unitCode', 'MRP')
            ->set('classificationCode', '000.1.2')
            ->assertSet('number', '800/555/SET-MRP/'.now()->format('m').'/'.now()->format('Y'))
            ->set('subject', 'Surat keluar setelah edit')
            ->call('saveLetter');

        $letter->refresh();

        $this->assertSame('800/555/SET-MRP/'.now()->format('m').'/'.now()->format('Y'), $letter->number);
        $this->assertSame('MRP', $letter->unit_code);
        $this->assertSame('000.1.2', $letter->classification_code);
        $this->assertSame('dokumen-surat/file-lama.pdf', $letter->file_path);
        Storage::disk('public')->assertExists('dokumen-surat/file-lama.pdf');
    }

    public function test_admin_can_delete_letter_and_its_documents(): void
    {
        Storage::fake('public');
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        Storage::disk('public')->put('dokumen-surat/surat-utama.pdf', 'file utama');
        Storage::disk('public')->put('dokumen-surat/lampiran.pdf', 'file lampiran');

        $letter = Letter::create([
            'type' => 'Masuk',
            'unit_code' => 'SET-MRP',
            'agenda_number' => 'AG-DELETE-001',
            'number' => 'SM/DELETE/001',
            'subject' => 'Surat yang akan dihapus',
            'external_party' => 'Biro Umum',
            'letter_date' => now()->toDateString(),
            'received_date' => now()->toDateString(),
            'nature' => 'Biasa',
            'urgency' => 'Normal',
            'file_path' => 'dokumen-surat/surat-utama.pdf',
            'status' => 'Baru',
        ]);

        LetterAttachment::create([
            'letter_id' => $letter->id,
            'category' => 'Lampiran',
            'original_name' => 'lampiran.pdf',
            'file_path' => 'dokumen-surat/lampiran.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);

        Livewire::test('dashboard')
            ->set('selectedLetterId', $letter->id)
            ->call('deleteLetter', $letter->id);

        $this->assertDatabaseMissing('letters', [
            'id' => $letter->id,
        ]);
        Storage::disk('public')->assertMissing('dokumen-surat/surat-utama.pdf');
        Storage::disk('public')->assertMissing('dokumen-surat/lampiran.pdf');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'letter.deleted',
        ]);
    }

    public function test_non_admin_roles_cannot_edit_or_delete_letters(): void
    {
        $letter = Letter::where('type', 'Masuk')->firstOrFail();

        $this->actingAs(User::where('role', 'Staf Sekretariat')->first());

        Livewire::test('dashboard')
            ->call('openEditLetterForm', $letter->id)
            ->assertForbidden();

        Livewire::test('dashboard')
            ->call('deleteLetter', $letter->id)
            ->assertForbidden();

        $this->actingAs(User::where('role', 'Pimpinan MRP')->first());

        Livewire::test('dashboard')
            ->call('openEditLetterForm', $letter->id)
            ->assertForbidden();

        Livewire::test('dashboard')
            ->call('deleteLetter', $letter->id)
            ->assertForbidden();
    }

    public function test_outgoing_letter_template_can_be_exported_to_pdf_preview_and_docx(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        $letter = Letter::create([
            'type' => 'Keluar',
            'unit_code' => 'SET-MRP',
            'number' => '800/888/SET-MRP/'.now()->format('m').'/'.now()->format('Y'),
            'subject' => 'Undangan rapat kerja',
            'outgoing_body' => "Dengan hormat,\n\nKami mengundang Bapak/Ibu untuk menghadiri rapat kerja.",
            'signer_name' => 'Drs. Pejabat Penandatangan',
            'signer_title' => 'Ketua MRP Papua Tengah',
            'external_party' => 'Sekretaris Daerah',
            'letter_date' => now()->toDateString(),
            'status' => 'Selesai',
        ]);

        $this->get(route('letters.template.pdf', $letter))
            ->assertOk()
            ->assertSee('Undangan rapat kerja')
            ->assertSee('Print / Save as PDF');

        $this->get(route('letters.template.docx', $letter))
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'letter.template_docx_downloaded',
            'subject_id' => $letter->id,
        ]);
    }

    public function test_incoming_letter_agenda_priority_fields_are_saved(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        $expectedAgenda = 'AG/MRP/'.now()->format('Y').'/'.str_pad(
            (string) (Letter::where('type', 'Masuk')->where('unit_code', 'MRP')->whereYear('received_date', now()->year)->count() + 1),
            4,
            '0',
            STR_PAD_LEFT,
        );

        Livewire::test('dashboard')
            ->call('openLetterForm', 'Masuk', 'MRP')
            ->assertSet('agendaNumber', $expectedAgenda)
            ->set('classificationCode', '000.1.1')
            ->set('subject', 'Undangan rapat koordinasi pemerintah daerah')
            ->set('externalParty', 'Sekretariat Daerah')
            ->set('letterDate', now()->toDateString())
            ->set('receivedDate', now()->toDateString())
            ->set('nature', 'Penting')
            ->set('urgency', 'Segera')
            ->set('dueDate', now()->addDays(2)->toDateString())
            ->call('saveLetter');

        $this->assertDatabaseHas('letters', [
            'type' => 'Masuk',
            'unit_code' => 'MRP',
            'agenda_number' => $expectedAgenda,
            'nature' => 'Penting',
            'urgency' => 'Segera',
        ]);

        $letter = Letter::where('agenda_number', $expectedAgenda)->firstOrFail();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'letter.created',
            'subject_id' => $letter->id,
        ]);
    }

    public function test_letter_archive_location_and_retention_fields_are_saved(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        Livewire::test('dashboard')
            ->call('openLetterForm', 'Masuk', 'SET-MRP')
            ->set('subject', 'Surat dengan lokasi arsip fisik')
            ->set('externalParty', 'Dinas Arsip')
            ->set('receivedDate', now()->toDateString())
            ->set('archiveLocation', 'Ruang Arsip Lantai 2')
            ->set('archiveBox', 'Rak A-03 / Boks 12')
            ->set('retentionCategory', 'Inaktif')
            ->set('retentionUntil', now()->addYears(5)->toDateString())
            ->set('archiveNotes', 'Dipindahkan ke arsip inaktif setelah audit selesai.')
            ->call('saveLetter');

        $this->assertDatabaseHas('letters', [
            'subject' => 'Surat dengan lokasi arsip fisik',
            'archive_location' => 'Ruang Arsip Lantai 2',
            'archive_box' => 'Rak A-03 / Boks 12',
            'retention_category' => 'Inaktif',
            'retention_until' => now()->addYears(5)->startOfDay()->toDateTimeString(),
            'archive_notes' => 'Dipindahkan ke arsip inaktif setelah audit selesai.',
        ]);
    }

    public function test_outgoing_letter_document_can_be_uploaded_after_saved(): void
    {
        Storage::fake('public');
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        $letter = Letter::create([
            'type' => 'Keluar',
            'unit_code' => 'SET-MRP',
            'number' => '800/099/SET-MRP/'.now()->format('m').'/'.now()->format('Y'),
            'subject' => 'Surat keluar menunggu dokumen final',
            'external_party' => 'Bagian Umum',
            'letter_date' => now()->toDateString(),
            'status' => 'Selesai',
        ]);

        Livewire::test('dashboard')
            ->set('outgoingDocument', UploadedFile::fake()->create('surat-final.pdf', 120, 'application/pdf'))
            ->call('uploadOutgoingDocument', $letter->id);

        $letter->refresh();

        $this->assertNotNull($letter->file_path);
        Storage::disk('public')->assertExists($letter->file_path);
    }

    public function test_outgoing_letter_signature_workflow_moves_through_formal_steps(): void
    {
        $admin = User::where('role', 'Admin Sekretariat')->firstOrFail();
        $departmentHead = User::where('role', 'Kepala Bagian')->firstOrFail();
        $leader = User::where('role', 'Pimpinan MRP')->firstOrFail();

        $letter = Letter::create([
            'type' => 'Keluar',
            'unit_code' => 'SET-MRP',
            'number' => '800/777/SET-MRP/'.now()->format('m').'/'.now()->format('Y'),
            'subject' => 'Konsep surat keluar untuk tanda tangan',
            'external_party' => 'Gubernur Papua Tengah',
            'letter_date' => now()->toDateString(),
            'status' => 'Selesai',
        ]);

        $this->actingAs($admin);

        Livewire::test('dashboard')
            ->call('startSignatureWorkflow', $letter->id);

        $this->assertDatabaseHas('letters', [
            'id' => $letter->id,
            'status' => 'Menunggu Paraf',
        ]);
        $this->assertDatabaseHas('letter_approvals', [
            'letter_id' => $letter->id,
            'step' => 'Paraf Konsep',
            'status' => 'Menunggu',
        ]);

        $paraf = $letter->approvals()->where('step', 'Paraf Konsep')->firstOrFail();

        $this->actingAs($departmentHead);

        Livewire::test('dashboard')
            ->call('approveLetterStep', $paraf->id);

        $this->assertDatabaseHas('letters', [
            'id' => $letter->id,
            'status' => 'Menunggu Persetujuan',
        ]);
        $this->assertDatabaseHas('letter_approvals', [
            'id' => $paraf->id,
            'status' => 'Disetujui',
            'actor_name' => $departmentHead->name,
        ]);

        $approval = $letter->approvals()->where('step', 'Persetujuan Pimpinan')->firstOrFail();

        $this->actingAs($leader);

        Livewire::test('dashboard')
            ->call('approveLetterStep', $approval->id);

        $this->assertDatabaseHas('letters', [
            'id' => $letter->id,
            'status' => 'Menunggu Tanda Tangan',
        ]);

        $signature = $letter->approvals()->where('step', 'Tanda Tangan Elektronik')->firstOrFail();

        Livewire::test('dashboard')
            ->call('approveLetterStep', $signature->id);

        $this->assertDatabaseHas('letters', [
            'id' => $letter->id,
            'status' => 'Selesai',
        ]);
        $this->assertDatabaseHas('letter_approvals', [
            'id' => $signature->id,
            'status' => 'Ditandatangani',
            'actor_name' => $leader->name,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'letter.approval_completed',
            'subject_id' => $signature->id,
        ]);
    }

    public function test_outgoing_letter_concept_can_be_rejected_and_resubmitted(): void
    {
        $admin = User::where('role', 'Admin Sekretariat')->firstOrFail();
        $departmentHead = User::where('role', 'Kepala Bagian')->firstOrFail();

        $letter = Letter::create([
            'type' => 'Keluar',
            'unit_code' => 'SET-MRP',
            'number' => '800/778/SET-MRP/'.now()->format('m').'/'.now()->format('Y'),
            'subject' => 'Konsep surat keluar perlu revisi',
            'external_party' => 'Gubernur Papua Tengah',
            'letter_date' => now()->toDateString(),
            'status' => 'Selesai',
        ]);

        $this->actingAs($admin);

        Livewire::test('dashboard')
            ->call('startSignatureWorkflow', $letter->id);

        $paraf = $letter->approvals()->where('step', 'Paraf Konsep')->firstOrFail();

        $this->actingAs($departmentHead);

        Livewire::test('dashboard')
            ->call('openApprovalRevisionModal', $paraf->id)
            ->assertSet('showRevisionModal', true)
            ->set('revisionNote', 'Perbaiki tujuan surat dan dasar hukum.')
            ->call('rejectApprovalStep');

        $this->assertDatabaseHas('letter_approvals', [
            'id' => $paraf->id,
            'status' => 'Ditolak',
            'note' => 'Perbaiki tujuan surat dan dasar hukum.',
            'actor_name' => $departmentHead->name,
        ]);
        $this->assertDatabaseHas('letters', [
            'id' => $letter->id,
            'status' => 'Revisi Konsep',
        ]);

        $this->actingAs($admin);

        Livewire::test('dashboard')
            ->call('resubmitApprovalWorkflow', $letter->id);

        $this->assertDatabaseHas('letter_approvals', [
            'id' => $paraf->id,
            'status' => 'Menunggu',
            'note' => null,
        ]);
        $this->assertDatabaseHas('letters', [
            'id' => $letter->id,
            'status' => 'Menunggu Paraf',
        ]);
    }

    public function test_letter_can_store_multiple_attachment_categories(): void
    {
        Storage::fake('public');
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        Livewire::test('dashboard')
            ->call('openLetterForm', 'Masuk', 'SET-MRP')
            ->set('subject', 'Surat dengan banyak dokumen')
            ->set('externalParty', 'Sekretariat Daerah')
            ->set('receivedDate', now()->toDateString())
            ->set('attachmentFiles', [
                UploadedFile::fake()->create('lampiran-a.pdf', 120, 'application/pdf'),
                UploadedFile::fake()->create('lampiran-b.pdf', 120, 'application/pdf'),
            ])
            ->set('memoFiles', [
                UploadedFile::fake()->create('nota-dinas.pdf', 120, 'application/pdf'),
            ])
            ->set('supportingFiles', [
                UploadedFile::fake()->image('dokumen-pendukung.jpg'),
            ])
            ->call('saveLetter');

        $letter = Letter::where('subject', 'Surat dengan banyak dokumen')->firstOrFail();

        $this->assertDatabaseCount('letter_attachments', 4);
        $this->assertDatabaseHas('letter_attachments', [
            'letter_id' => $letter->id,
            'category' => 'Lampiran',
            'original_name' => 'lampiran-a.pdf',
        ]);
        $this->assertDatabaseHas('letter_attachments', [
            'letter_id' => $letter->id,
            'category' => 'Nota Dinas',
            'original_name' => 'nota-dinas.pdf',
        ]);
        $this->assertDatabaseHas('letter_attachments', [
            'letter_id' => $letter->id,
            'category' => 'Dokumen Pendukung',
            'original_name' => 'dokumen-pendukung.jpg',
        ]);

        foreach ($letter->attachments as $attachment) {
            Storage::disk('public')->assertExists($attachment->file_path);
        }
    }

    public function test_attachment_can_be_reviewed_and_downloaded(): void
    {
        Storage::fake('public');
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        $letter = Letter::where('type', 'Masuk')->firstOrFail();
        Storage::disk('public')->put('lampiran-surat/uji-lampiran.pdf', 'Lampiran uji');

        $attachment = $letter->attachments()->create([
            'category' => 'Lampiran',
            'original_name' => 'uji-lampiran.pdf',
            'file_path' => 'lampiran-surat/uji-lampiran.pdf',
            'mime_type' => 'application/pdf',
            'size' => 120,
        ]);

        $this->get(route('letter-attachments.review', $attachment))->assertOk();

        $this->get(route('letter-attachments.download', $attachment))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_document_can_be_reviewed_and_downloaded(): void
    {
        Storage::fake('public');
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        Storage::disk('public')->put('dokumen-surat/review.pdf', 'Dokumen uji');

        $letter = Letter::create([
            'type' => 'Masuk',
            'unit_code' => 'SET-MRP',
            'number' => 'SM/SET-MRP/REVIEW',
            'subject' => 'Surat dengan dokumen review',
            'external_party' => 'Bagian Umum',
            'letter_date' => now()->toDateString(),
            'file_path' => 'dokumen-surat/review.pdf',
            'status' => 'Baru',
        ]);

        $this->get(route('letters.document.review', $letter))->assertOk();

        $this->get(route('letters.document.download', $letter))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_department_head_file_access_follows_disposition_assignment(): void
    {
        Storage::fake('public');

        $departmentHead = User::where('role', 'Kepala Bagian')->firstOrFail();
        Storage::disk('public')->put('dokumen-surat/akses-kabag.pdf', 'Dokumen kabag');
        Storage::disk('public')->put('lampiran-surat/akses-kabag.pdf', 'Lampiran kabag');
        Storage::disk('public')->put('file-disposisi/akses-kabag.pdf', 'Disposisi kabag');

        $letter = Letter::create([
            'type' => 'Masuk',
            'unit_code' => 'SET-MRP',
            'number' => 'SM/SET-MRP/AKSES-KABAG',
            'subject' => 'Surat akses kepala bagian',
            'external_party' => 'Bagian Umum',
            'letter_date' => now()->toDateString(),
            'file_path' => 'dokumen-surat/akses-kabag.pdf',
            'status' => 'Disposisi Pimpinan',
        ]);
        $attachment = $letter->attachments()->create([
            'category' => 'Lampiran',
            'original_name' => 'akses-kabag.pdf',
            'file_path' => 'lampiran-surat/akses-kabag.pdf',
            'mime_type' => 'application/pdf',
            'size' => 120,
        ]);
        $disposition = $letter->dispositions()->create([
            'sender_name' => 'Pimpinan MRP Papua Tengah',
            'recipient_name' => $departmentHead->name,
            'instruction' => 'Tindak lanjut sesuai bidang.',
            'status' => 'Belum Dibaca',
            'scan_path' => 'file-disposisi/akses-kabag.pdf',
            'scan_original_name' => 'akses-kabag.pdf',
        ]);

        $this->actingAs($departmentHead);
        $this->get(route('letters.document.review', $letter))->assertOk();
        $this->get(route('letter-attachments.review', $attachment))->assertOk();
        $this->get(route('dispositions.scan.review', $disposition))->assertOk();

        Storage::disk('public')->put('dokumen-surat/tidak-ditugaskan.pdf', 'Dokumen lain');
        $unassignedLetter = Letter::create([
            'type' => 'Masuk',
            'unit_code' => 'SET-MRP',
            'number' => 'SM/SET-MRP/TIDAK-DITUGASKAN',
            'subject' => 'Surat tanpa disposisi ke kepala bagian',
            'external_party' => 'Bagian Umum',
            'letter_date' => now()->toDateString(),
            'file_path' => 'dokumen-surat/tidak-ditugaskan.pdf',
            'status' => 'Baru',
        ]);

        $this->get(route('letters.document.review', $unassignedLetter))->assertForbidden();
    }

    public function test_letter_detail_opens_in_modal(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        $letter = Letter::first();

        Livewire::test('dashboard')
            ->call('openDetailModal', $letter->id)
            ->assertSet('selectedLetterId', $letter->id)
            ->assertSet('showDetailModal', true)
            ->assertSee('Aksi Surat')
            ->assertSee('Disposisi')
            ->assertSee('Edit Surat')
            ->assertSee('Hapus Surat')
            ->call('openDispositionForm', $letter->id)
            ->assertSet('showDetailModal', false)
            ->assertSet('showDispositionForm', true);
    }

    public function test_letter_detail_shows_disposition_timeline(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        $letter = Letter::where('type', 'Masuk')->firstOrFail();
        $parent = $letter->dispositions()->create([
            'sender_name' => 'Pimpinan MRP Papua Tengah',
            'recipient_name' => 'Kepala Bagian Umum',
            'instruction' => 'Telaah awal.',
            'status' => 'Diproses',
        ]);
        $letter->dispositions()->create([
            'parent_id' => $parent->id,
            'sender_name' => 'Kepala Bagian Umum',
            'recipient_name' => 'Staf Administrasi',
            'instruction' => 'Siapkan bahan jawaban.',
            'status' => 'Belum Dibaca',
        ]);

        Livewire::test('dashboard')
            ->call('openDetailModal', $letter->id)
            ->assertSee('Timeline Disposisi')
            ->assertSee('Pimpinan MRP Papua Tengah ke Kepala Bagian Umum')
            ->assertSee('Kepala Bagian Umum ke Staf Administrasi');
    }

    public function test_tracking_page_shows_letter_status_and_timeline(): void
    {
        $staff = User::where('role', 'Staf Sekretariat')->firstOrFail();
        $admin = User::where('role', 'Admin Sekretariat')->firstOrFail();
        $this->actingAs($staff);

        $letter = Letter::where('type', 'Masuk')->firstOrFail();
        $letter->update([
            'number' => 'TRK/001/SET-MRP/'.now()->format('Y'),
            'subject' => 'Surat untuk pelacakan status',
            'status' => 'Diproses',
        ]);
        $parent = $letter->dispositions()->create([
            'sender_name' => 'Pimpinan MRP Papua Tengah',
            'recipient_name' => 'Kepala Bagian Umum',
            'instruction' => 'Telusuri dan koordinasikan tindak lanjut.',
            'status' => 'Diproses',
        ]);
        $letter->dispositions()->create([
            'parent_id' => $parent->id,
            'sender_name' => 'Kepala Bagian Umum',
            'recipient_name' => 'Staf Administrasi',
            'instruction' => 'Catat progres pelacakan.',
            'status' => 'Belum Dibaca',
        ]);
        ActivityLog::create([
            'user_id' => $admin->id,
            'action' => 'letter.created',
            'subject_type' => $letter->getMorphClass(),
            'subject_id' => $letter->id,
            'description' => 'Surat dicatat oleh admin.',
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(30),
        ]);
        ActivityLog::create([
            'user_id' => $staff->id,
            'action' => 'disposition.status_updated',
            'subject_type' => $parent->getMorphClass(),
            'subject_id' => $parent->id,
            'description' => 'Status disposisi diperbarui.',
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        $this->get('/tracking-surat')
            ->assertOk()
            ->assertSee('Tracking Surat');

        Livewire::test('letter-tracking-page')
            ->set('search', 'TRK/001')
            ->call('selectLetter', $letter->id)
            ->assertSet('showDetailModal', true)
            ->assertSee('Surat untuk pelacakan status')
            ->assertSee('Tutup')
            ->assertSee('Posisi Surat')
            ->assertSee('Timeline Disposisi')
            ->assertSee('Riwayat Aksi')
            ->assertSee('Surat dicatat oleh admin.')
            ->assertSee('Status disposisi diperbarui.')
            ->assertSee('Oleh '.$admin->name)
            ->assertSee('Oleh '.$staff->name)
            ->assertSee('Diproses')
            ->assertSee('Pimpinan MRP Papua Tengah ke Kepala Bagian Umum')
            ->assertSee('Kepala Bagian Umum ke Staf Administrasi');
    }

    public function test_tracking_page_requires_login(): void
    {
        $this->get('/tracking-surat')->assertRedirect(route('login'));
    }

    public function test_letter_table_is_paginated(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        for ($i = 1; $i <= 12; $i++) {
            Letter::create([
                'type' => 'Masuk',
                'unit_code' => 'SET-MRP',
                'number' => 'SM/PAGINATION/'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'subject' => 'Surat pagination '.$i,
                'external_party' => 'Bagian Umum',
                'letter_date' => now()->subDays($i)->toDateString(),
                'status' => 'Baru',
            ]);
        }

        Livewire::test('dashboard')
            ->assertSee('Menampilkan 1-10');
    }

    public function test_letter_table_can_filter_overdue_letters(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        Letter::create([
            'type' => 'Masuk',
            'unit_code' => 'SET-MRP',
            'agenda_number' => 'AG/SET-MRP/'.now()->format('Y').'/9901',
            'number' => 'SM/FILTER/OVERDUE',
            'subject' => 'Surat prioritas lewat batas',
            'external_party' => 'Inspektorat',
            'letter_date' => now()->subDays(5)->toDateString(),
            'received_date' => now()->subDays(5)->toDateString(),
            'urgency' => 'Sangat Segera',
            'due_date' => now()->subDay()->toDateString(),
            'status' => 'Diproses',
        ]);

        Letter::create([
            'type' => 'Masuk',
            'unit_code' => 'SET-MRP',
            'agenda_number' => 'AG/SET-MRP/'.now()->format('Y').'/9902',
            'number' => 'SM/FILTER/NORMAL',
            'subject' => 'Surat prioritas normal aman',
            'external_party' => 'Biro Umum',
            'letter_date' => now()->toDateString(),
            'received_date' => now()->toDateString(),
            'urgency' => 'Normal',
            'due_date' => now()->addWeek()->toDateString(),
            'status' => 'Baru',
        ]);

        Livewire::test('dashboard')
            ->set('dueFilter', 'Lewat Batas')
            ->assertSee('Surat prioritas lewat batas')
            ->assertDontSee('Surat prioritas normal aman');
    }

    public function test_letters_can_be_exported_as_filtered_csv(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        Letter::create([
            'type' => 'Masuk',
            'unit_code' => 'MRP',
            'agenda_number' => 'AG/MRP/'.now()->format('Y').'/8801',
            'number' => 'SM/EXPORT/SEGERA',
            'subject' => 'Surat export prioritas segera',
            'external_party' => 'Sekretariat Daerah',
            'letter_date' => now()->toDateString(),
            'received_date' => now()->toDateString(),
            'urgency' => 'Segera',
            'status' => 'Baru',
        ]);

        Letter::create([
            'type' => 'Masuk',
            'unit_code' => 'MRP',
            'agenda_number' => 'AG/MRP/'.now()->format('Y').'/8802',
            'number' => 'SM/EXPORT/NORMAL',
            'subject' => 'Surat export prioritas normal',
            'external_party' => 'Bagian Umum',
            'letter_date' => now()->toDateString(),
            'received_date' => now()->toDateString(),
            'urgency' => 'Normal',
            'status' => 'Baru',
        ]);

        $response = $this->get(route('letters.export', ['urgency' => 'Segera']));

        $response
            ->assertOk()
            ->assertHeader('content-disposition');

        $content = $response->streamedContent();

        $this->assertStringContainsString('Surat export prioritas segera', $content);
        $this->assertStringNotContainsString('Surat export prioritas normal', $content);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'letters.exported',
        ]);
    }

    public function test_admin_can_manage_disposition_recipient(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        Livewire::test('settings')
            ->set('recipientName', 'Kepala Bagian Persidangan')
            ->set('recipientPosition', 'Kepala Bagian')
            ->set('recipientUnit', 'Persidangan')
            ->call('saveRecipient');

        $recipient = DispositionRecipient::where('name', 'Kepala Bagian Persidangan')->firstOrFail();

        Livewire::test('settings')
            ->call('editRecipient', $recipient->id)
            ->set('recipientUnit', 'Bagian Persidangan')
            ->call('saveRecipient');

        $this->assertDatabaseHas('disposition_recipients', [
            'id' => $recipient->id,
            'unit' => 'Bagian Persidangan',
        ]);

        Livewire::test('settings')
            ->call('toggleRecipient', $recipient->id);

        $this->assertDatabaseHas('disposition_recipients', [
            'id' => $recipient->id,
            'is_active' => false,
        ]);

        Livewire::test('settings')
            ->call('deleteRecipient', $recipient->id);

        $this->assertDatabaseMissing('disposition_recipients', [
            'id' => $recipient->id,
        ]);
    }

    public function test_disposition_uses_master_recipient(): void
    {
        $this->actingAs(User::where('role', 'Pimpinan MRP')->first());

        $letter = Letter::where('type', 'Masuk')->firstOrFail();
        $recipient = DispositionRecipient::where('is_active', true)->firstOrFail();

        Livewire::test('dashboard')
            ->call('openDispositionForm', $letter->id)
            ->set('recipientId', $recipient->id)
            ->set('instruction', 'Mohon ditindaklanjuti hari ini.')
            ->call('saveDisposition');

        $this->assertDatabaseHas('dispositions', [
            'letter_id' => $letter->id,
            'disposition_recipient_id' => $recipient->id,
            'recipient_name' => $recipient->name,
        ]);
    }

    public function test_dashboard_disposition_can_upload_disposition_file(): void
    {
        Storage::fake('public');

        $this->actingAs(User::where('role', 'Pimpinan MRP')->first());

        $letter = Letter::where('type', 'Masuk')->firstOrFail();
        $recipient = DispositionRecipient::where('is_active', true)->firstOrFail();
        $file = UploadedFile::fake()->create('file-disposisi.pdf', 80, 'application/pdf');

        Livewire::test('dashboard')
            ->call('openDispositionForm', $letter->id)
            ->set('recipientId', $recipient->id)
            ->set('instruction', 'Mohon ditindaklanjuti sesuai file disposisi.')
            ->set('dispositionScan', $file)
            ->call('saveDisposition');

        $disposition = $letter->fresh()->dispositions()->latest()->firstOrFail();

        $this->assertDatabaseHas('dispositions', [
            'id' => $disposition->id,
            'input_method' => 'Upload File Disposisi',
            'scan_original_name' => 'file-disposisi.pdf',
        ]);

        Storage::disk('public')->assertExists($disposition->scan_path);
    }

    public function test_dashboard_disposition_can_send_to_multiple_recipients(): void
    {
        $this->actingAs(User::where('role', 'Pimpinan MRP')->first());

        $letter = Letter::where('type', 'Masuk')->firstOrFail();
        $recipients = DispositionRecipient::where('is_active', true)->take(2)->get();

        Livewire::test('dashboard')
            ->call('openDispositionForm', $letter->id)
            ->set('recipientIds', $recipients->pluck('id')->all())
            ->set('instruction', 'Mohon ditindaklanjuti bersama.')
            ->call('saveDisposition');

        foreach ($recipients as $recipient) {
            $this->assertDatabaseHas('dispositions', [
                'letter_id' => $letter->id,
                'disposition_recipient_id' => $recipient->id,
                'recipient_name' => $recipient->name,
                'instruction' => 'Mohon ditindaklanjuti bersama.',
            ]);
        }
    }

    public function test_department_head_can_update_own_disposition_status(): void
    {
        $departmentHead = User::where('role', 'Kepala Bagian')->firstOrFail();
        $this->actingAs($departmentHead);

        $letter = Letter::where('type', 'Masuk')->firstOrFail();
        $disposition = $letter->dispositions()->create([
            'sender_name' => 'Pimpinan',
            'recipient_name' => $departmentHead->name,
            'instruction' => 'Tindak lanjuti sesuai bidang.',
            'status' => 'Belum Dibaca',
        ]);

        Livewire::test('department-head-page')
            ->call('updateDispositionStatus', $disposition->id, 'Diproses');

        $this->assertDatabaseHas('dispositions', [
            'id' => $disposition->id,
            'status' => 'Diproses',
        ]);
    }

    public function test_incoming_letter_status_follows_all_disposition_statuses(): void
    {
        $departmentHead = User::where('role', 'Kepala Bagian')->firstOrFail();
        $this->actingAs($departmentHead);

        $letter = Letter::create([
            'type' => 'Masuk',
            'unit_code' => 'SET-MRP',
            'number' => 'SYNC-DISPOSISI/001',
            'subject' => 'Surat sinkron disposisi',
            'external_party' => 'Unit Penguji',
            'letter_date' => now()->toDateString(),
            'received_date' => now()->toDateString(),
            'status' => 'Disposisi Pimpinan',
        ]);
        $first = $letter->dispositions()->create([
            'sender_name' => 'Pimpinan',
            'recipient_name' => $departmentHead->name,
            'instruction' => 'Tindak lanjut pertama.',
            'status' => 'Belum Dibaca',
        ]);
        $second = $letter->dispositions()->create([
            'sender_name' => 'Pimpinan',
            'recipient_name' => $departmentHead->name,
            'instruction' => 'Tindak lanjut kedua.',
            'status' => 'Belum Dibaca',
        ]);

        Livewire::test('department-head-page')
            ->call('updateDispositionStatus', $first->id, 'Selesai');

        $this->assertDatabaseHas('letters', [
            'id' => $letter->id,
            'status' => 'Diproses',
        ]);

        Livewire::test('department-head-page')
            ->call('updateDispositionStatus', $second->id, 'Selesai');

        $this->assertDatabaseHas('letters', [
            'id' => $letter->id,
            'status' => 'Selesai',
        ]);
    }

    public function test_leader_can_dispose_letter_to_department_head(): void
    {
        $this->actingAs(User::where('role', 'Pimpinan MRP')->firstOrFail());

        $letter = Letter::where('type', 'Masuk')->firstOrFail();
        $recipient = DispositionRecipient::where('name', 'Kepala Bagian Umum')->firstOrFail();

        Livewire::test('leadership-page')
            ->call('openDispositionModal', $letter->id)
            ->set('recipientId', $recipient->id)
            ->set('instruction', 'Telaah dan koordinasikan tindak lanjut dengan unit terkait.')
            ->call('saveDisposition');

        $this->assertDatabaseHas('dispositions', [
            'letter_id' => $letter->id,
            'recipient_name' => 'Kepala Bagian Umum',
            'disposition_recipient_id' => $recipient->id,
            'status' => 'Belum Dibaca',
        ]);

        $this->assertDatabaseHas('letters', [
            'id' => $letter->id,
            'status' => 'Disposisi Pimpinan',
        ]);
    }

    public function test_leadership_disposition_can_upload_disposition_file(): void
    {
        Storage::fake('public');

        $this->actingAs(User::where('role', 'Pimpinan MRP')->firstOrFail());

        $letter = Letter::where('type', 'Masuk')->firstOrFail();
        $recipient = DispositionRecipient::where('name', 'Kepala Bagian Umum')->firstOrFail();
        $file = UploadedFile::fake()->create('disposisi-pimpinan.pdf', 96, 'application/pdf');

        Livewire::test('leadership-page')
            ->call('openDispositionModal', $letter->id)
            ->set('recipientId', $recipient->id)
            ->set('instruction', 'Telaah dan tindaklanjuti sesuai file disposisi.')
            ->set('dispositionScan', $file)
            ->call('saveDisposition');

        $disposition = $letter->fresh()->dispositions()->latest()->firstOrFail();

        $this->assertDatabaseHas('dispositions', [
            'id' => $disposition->id,
            'input_method' => 'Upload File Disposisi',
            'scan_original_name' => 'disposisi-pimpinan.pdf',
        ]);

        Storage::disk('public')->assertExists($disposition->scan_path);
    }

    public function test_leadership_disposition_can_send_to_multiple_department_heads(): void
    {
        $this->actingAs(User::where('role', 'Pimpinan MRP')->firstOrFail());

        $letter = Letter::where('type', 'Masuk')->firstOrFail();
        $first = DispositionRecipient::where('name', 'Kepala Bagian Umum')->firstOrFail();
        $second = DispositionRecipient::create([
            'name' => 'Kepala Bagian Persidangan',
            'position' => 'Kepala Bagian',
            'unit' => 'Persidangan',
            'is_active' => true,
        ]);

        Livewire::test('leadership-page')
            ->call('openDispositionModal', $letter->id)
            ->set('recipientIds', [$first->id, $second->id])
            ->set('instruction', 'Koordinasikan tindak lanjut lintas bagian.')
            ->call('saveDisposition');

        foreach ([$first, $second] as $recipient) {
            $this->assertDatabaseHas('dispositions', [
                'letter_id' => $letter->id,
                'disposition_recipient_id' => $recipient->id,
                'recipient_name' => $recipient->name,
                'instruction' => 'Koordinasikan tindak lanjut lintas bagian.',
            ]);
        }
    }

    public function test_leadership_can_create_standalone_disposition(): void
    {
        $this->actingAs(User::where('role', 'Pimpinan MRP')->firstOrFail());

        $recipient = DispositionRecipient::where('name', 'Kepala Bagian Umum')->firstOrFail();

        Livewire::test('leadership-page')
            ->call('openStandaloneDispositionModal')
            ->assertSet('selectedLetterId', null)
            ->assertSet('showDispositionModal', true)
            ->set('recipientIds', [$recipient->id])
            ->set('instruction', 'Arahan langsung pimpinan tanpa surat masuk.')
            ->call('saveDisposition')
            ->assertHasNoErrors()
            ->assertSet('showDispositionModal', false);

        $this->assertDatabaseHas('dispositions', [
            'letter_id' => null,
            'recipient_name' => 'Kepala Bagian Umum',
            'disposition_recipient_id' => $recipient->id,
            'instruction' => 'Arahan langsung pimpinan tanpa surat masuk.',
        ]);

        $this->actingAs(User::where('role', 'Admin Sekretariat')->firstOrFail());

        Livewire::test('department-head-page')
            ->assertSee('Disposisi Mandiri')
            ->assertSee('Arahan langsung pimpinan tanpa surat masuk.');
    }

    public function test_personal_secretary_can_input_scanned_leader_disposition(): void
    {
        Storage::fake('public');

        $secretary = User::where('role', 'Sekretaris Pribadi')->firstOrFail();
        $this->actingAs($secretary);

        $letter = Letter::where('type', 'Masuk')->where('status', 'Baru')->firstOrFail();
        $recipient = DispositionRecipient::where('name', 'Kepala Bagian Umum')->firstOrFail();
        $scan = UploadedFile::fake()->create('disposisi-pimpinan.pdf', 120, 'application/pdf');

        Livewire::test('leadership-page')
            ->call('openDispositionModal', $letter->id)
            ->set('recipientId', $recipient->id)
            ->set('instruction', 'Mohon segera ditindaklanjuti sesuai catatan pimpinan.')
            ->set('dispositionScan', $scan)
            ->call('saveDisposition');

        $disposition = $letter->fresh()->dispositions()->latest()->firstOrFail();

        $this->assertDatabaseHas('dispositions', [
            'id' => $disposition->id,
            'letter_id' => $letter->id,
            'recipient_name' => 'Kepala Bagian Umum',
            'disposition_recipient_id' => $recipient->id,
            'input_method' => 'Upload File Disposisi',
            'input_by_name' => $secretary->name,
            'input_by_role' => 'Sekretaris Pribadi',
            'scan_original_name' => 'disposisi-pimpinan.pdf',
            'status' => 'Belum Dibaca',
        ]);

        $this->assertDatabaseHas('letters', [
            'id' => $letter->id,
            'status' => 'Disposisi Pimpinan',
        ]);

        Storage::disk('public')->assertExists($disposition->scan_path);
    }

    public function test_leadership_page_letter_detail_modal_shows_leader_actions(): void
    {
        $this->actingAs(User::where('role', 'Pimpinan MRP')->firstOrFail());

        $letter = Letter::where('type', 'Masuk')->firstOrFail();

        Livewire::test('leadership-page')
            ->call('openLetterDetailModal', $letter->id)
            ->assertSet('selectedLetterId', $letter->id)
            ->assertSet('showDetailModal', true)
            ->assertSee('Aksi Pimpinan')
            ->assertSee('Disposisi')
            ->assertDontSee('Input Scan Disposisi')
            ->assertDontSee('Edit Surat')
            ->assertDontSee('Hapus Surat')
            ->call('openDispositionModal', $letter->id)
            ->assertSet('showDetailModal', false)
            ->assertSet('showDispositionModal', true);
    }

    public function test_department_head_can_forward_disposition_to_executor(): void
    {
        $departmentHead = User::where('role', 'Kepala Bagian')->firstOrFail();
        $this->actingAs($departmentHead);

        $letter = Letter::where('type', 'Masuk')->firstOrFail();
        $parent = $letter->dispositions()->create([
            'sender_name' => 'Pimpinan MRP Papua Tengah',
            'recipient_name' => $departmentHead->name,
            'instruction' => 'Tindak lanjuti sesuai bidang.',
            'status' => 'Belum Dibaca',
        ]);
        $recipient = DispositionRecipient::where('name', 'Staf Administrasi')->firstOrFail();

        Livewire::test('department-head-page')
            ->call('openForwardModal', $parent->id)
            ->set('forwardRecipientId', $recipient->id)
            ->set('forwardInstruction', 'Siapkan bahan jawaban dan laporkan progres.')
            ->call('forwardDisposition');

        $this->assertDatabaseHas('dispositions', [
            'letter_id' => $letter->id,
            'parent_id' => $parent->id,
            'sender_name' => $departmentHead->name,
            'recipient_name' => 'Staf Administrasi',
            'status' => 'Belum Dibaca',
        ]);

        $this->assertDatabaseHas('dispositions', [
            'id' => $parent->id,
            'status' => 'Diproses',
        ]);
    }

    public function test_department_head_page_disposition_detail_modal_shows_limited_actions(): void
    {
        $departmentHead = User::where('role', 'Kepala Bagian')->firstOrFail();
        $this->actingAs($departmentHead);

        $letter = Letter::where('type', 'Masuk')->firstOrFail();
        $disposition = $letter->dispositions()->create([
            'sender_name' => 'Pimpinan MRP Papua Tengah',
            'recipient_name' => $departmentHead->name,
            'instruction' => 'Tindak lanjuti dan laporkan hasilnya.',
            'status' => 'Belum Dibaca',
        ]);

        Livewire::test('department-head-page')
            ->call('openDispositionDetailModal', $disposition->id)
            ->assertSet('selectedDispositionId', $disposition->id)
            ->assertSet('showDetailModal', true)
            ->assertSee('Aksi Kepala Bagian')
            ->assertSee('Teruskan')
            ->assertDontSee('Edit Surat')
            ->assertDontSee('Hapus Surat')
            ->call('openForwardModal', $disposition->id)
            ->assertSet('showDetailModal', false)
            ->assertSet('showForwardModal', true);
    }
}
