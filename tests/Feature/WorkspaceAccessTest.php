<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\User;
use App\Support\WorkspaceNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkspaceAccessTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_admin_opens_all_features_inside_one_workspace(): void
    {
        $admin = User::where('role', 'Admin Sekretariat')->firstOrFail();
        $this->actingAs($admin);

        foreach (WorkspaceNavigation::pages($admin) as $page) {
            $response = $this->get('/?view='.$page)->assertOk()->assertSeeLivewire('workspace');
            $this->assertSame(1, substr_count($response->getContent(), 'aria-label="Menu utama"'));
        }
    }

    public function test_role_access_is_enforced_for_direct_urls_and_livewire_navigation(): void
    {
        foreach (['Pimpinan MRP', 'Sekretaris Pribadi', 'Kepala Bagian', 'Staf Sekretariat'] as $role) {
            $user = User::where('role', $role)->firstOrFail();
            $this->actingAs($user);
            $this->get('/')->assertOk()->assertSeeLivewire('workspace');
            foreach (['monitoring', 'sk', 'pengaturan'] as $page) {
                $this->get('/?view='.$page)->assertForbidden();
            }
            Livewire::test('workspace')->call('navigate', 'pengaturan')->assertForbidden();

            if ($user->isLeader() || $user->isPersonalSecretary()) {
                $this->get('/?view=disposisi')->assertOk()->assertSeeLivewire('leadership-page');
                $this->get('/?view=tindak-lanjut')->assertForbidden();
            } else {
                $this->get('/?view=tindak-lanjut')->assertOk()->assertSeeLivewire('department-head-page');
                $this->get('/?view=disposisi')->assertForbidden();
            }
        }
    }

    public function test_changing_the_public_page_property_cannot_bypass_role_checks(): void
    {
        $this->actingAs(User::where('role', 'Staf Sekretariat')->firstOrFail());
        Livewire::test('workspace')->set('activePage', 'pengaturan')->assertForbidden();
        Livewire::test('workspace')->call('createLetter', 'Keluar')->assertForbidden();
        Livewire::test('settings')->assertForbidden();
        Livewire::test('number-monitor-page')->assertForbidden();
        Livewire::test('sk-numbering-page')->assertForbidden();
    }

    public function test_child_components_recheck_permissions_after_the_role_changes(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->firstOrFail());
        $settings = Livewire::test('settings');
        $this->actingAs(User::where('role', 'Staf Sekretariat')->firstOrFail());
        $settings->call('saveAgency')->assertForbidden();
    }

    public function test_department_head_only_sees_assigned_letters_in_the_dashboard_tracking_and_export(): void
    {
        $head = User::where('role', 'Kepala Bagian')->firstOrFail();
        $this->actingAs($head);
        $visible = Letter::create([
            'type' => 'Masuk', 'unit_code' => 'SET-MRP', 'number' => 'ACCESS/HEAD/001',
            'subject' => 'Surat khusus penugasan kepala bagian', 'external_party' => 'Instansi',
            'letter_date' => now()->toDateString(), 'received_date' => now()->toDateString(), 'status' => 'Baru',
        ]);
        $visible->dispositions()->create([
            'sender_name' => 'Pimpinan', 'recipient_name' => $head->name,
            'instruction' => 'Tindak lanjuti surat ini', 'status' => 'Belum Dibaca',
        ]);
        $hidden = Letter::create([
            'type' => 'Masuk', 'unit_code' => 'SET-MRP', 'number' => 'ACCESS/OTHER/002',
            'subject' => 'Surat milik bagian lain yang tidak ditugaskan', 'external_party' => 'Instansi',
            'letter_date' => now()->toDateString(), 'received_date' => now()->toDateString(), 'status' => 'Baru',
        ]);

        foreach (['surat', 'pelacakan'] as $page) {
            $this->get('/?view='.$page)->assertOk()->assertSee($visible->subject)->assertDontSee($hidden->subject);
        }
        Livewire::test('dashboard')->call('openDetailModal', $hidden->id)->assertNotFound();
        Livewire::test('letter-tracking-page')->call('selectLetter', $hidden->id)->assertNotFound();
        $csv = $this->get(route('letters.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString($visible->subject, $csv);
        $this->assertStringNotContainsString($hidden->subject, $csv);
    }

    public function test_staff_can_only_act_on_dispositions_addressed_to_them(): void
    {
        $staff = User::where('role', 'Staf Sekretariat')->firstOrFail();
        $this->actingAs($staff);
        $letter = Letter::where('type', 'Masuk')->firstOrFail();
        $own = $letter->dispositions()->create([
            'sender_name' => 'Kepala Bagian', 'recipient_name' => $staff->name,
            'instruction' => 'Kerjakan tugas staf ini', 'status' => 'Belum Dibaca',
        ]);
        $other = $letter->dispositions()->create([
            'sender_name' => 'Pimpinan', 'recipient_name' => 'Pengguna lain',
            'instruction' => 'Tugas milik pengguna lain', 'status' => 'Belum Dibaca',
        ]);

        Livewire::test('department-head-page')
            ->assertSee($own->instruction)->assertDontSee($other->instruction)
            ->call('updateDispositionStatus', $own->id, 'Diproses')->assertHasNoErrors();
        $this->assertSame('Diproses', $own->fresh()->status);
        Livewire::test('department-head-page')->call('updateDispositionStatus', $other->id, 'Selesai')->assertForbidden();
        Livewire::test('department-head-page')->call('openForwardModal', $own->id)->assertForbidden();
        $this->assertSame('Belum Dibaca', $other->fresh()->status);
    }

    public function test_sidebar_actions_dispatch_to_the_current_panel_or_open_the_requested_panel(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->firstOrFail());
        Livewire::test('workspace')->call('createLetter', 'Masuk')
            ->assertDispatched('workspace-create-letter', type: 'Masuk');
        Livewire::test('workspace')->call('navigate', 'monitoring')->call('createLetter', 'Keluar')
            ->assertSet('activePage', 'surat')->assertSet('initialAction', 'Keluar');
        Livewire::test('dashboard', ['embedded' => true, 'initialAction' => 'Keluar'])
            ->assertSet('showLetterForm', true)->assertSet('type', 'Keluar');
        Livewire::test('workspace')->call('openSettings', 'pengguna')
            ->assertSet('activePage', 'pengaturan')->assertSet('settingsTab', 'pengguna')
            ->call('openSettings', 'unit')->assertDispatched('workspace-settings-tab', tab: 'unit');
    }

    public function test_legacy_pages_redirect_into_the_same_dashboard(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->firstOrFail());
        $this->get('/monitoring-nomor-surat')->assertRedirect(route('dashboard', ['view' => 'monitoring']));
        $this->get('/pimpinan')->assertRedirect(route('dashboard', ['view' => 'disposisi']));
        $this->get('/settings?tab=pengguna')->assertRedirect(route('dashboard', ['view' => 'pengaturan', 'tab' => 'pengguna']));
    }

    public function test_inactive_or_unrecognized_roles_cannot_open_the_workspace(): void
    {
        $staff = User::where('role', 'Staf Sekretariat')->firstOrFail();
        $staff->update(['is_active' => false]);
        $this->actingAs($staff)->get('/')->assertForbidden();
        $staff->update(['is_active' => true, 'role' => 'Role tanpa izin']);
        $this->actingAs($staff->fresh())->get('/')->assertForbidden();
    }

    public function test_letter_pagination_does_not_conflict_with_workspace_navigation(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->firstOrFail());
        foreach (range(1, 21) as $number) {
            Letter::create([
                'type' => 'Masuk', 'unit_code' => 'SET-MRP', 'number' => 'WORKSPACE/PAGINATION/'.$number,
                'subject' => 'Surat halaman '.$number, 'external_party' => 'Instansi',
                'letter_date' => now()->toDateString(), 'received_date' => now()->toDateString(), 'status' => 'Baru',
            ]);
        }

        $this->get('/?page=2')->assertOk()->assertSeeLivewire('workspace')->assertSee('Menampilkan 11-20');
        $this->get('/?view=surat&page=2')->assertOk()->assertSee('Dasbor Persuratan')->assertSee('Menampilkan 11-20');
    }
}
