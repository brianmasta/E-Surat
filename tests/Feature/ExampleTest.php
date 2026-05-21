<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\ActivityLog;
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

    public function test_dashboard_shows_monitoring_number_button_for_admin(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->first());

        Livewire::test('dashboard')
            ->assertSee('Monitoring Surat');

        Livewire::withQueryParams(['tab' => 'monitoring-nomor'])
            ->test('settings')
            ->assertSet('activeSettingsTab', 'monitoring-nomor')
            ->assertSee('Monitoring Nomor Surat Keluar');
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
            'status' => 'Disposisi',
        ]);
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
