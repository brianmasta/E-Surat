<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Letter;
use App\Models\User;
use App\Support\LetterNumbering;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UnitLetterNumberingTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::where('role', 'Admin Sekretariat')->firstOrFail());
        AppSetting::putValue('letter_units', [
            ['code' => 'UNIT-A', 'name' => 'Unit A', 'is_default' => true],
            ['code' => 'UNIT-B', 'name' => 'Unit B', 'is_default' => false],
            ['code' => 'UNIT-C', 'name' => 'Unit C', 'is_default' => false],
        ]);
        AppSetting::putValue('letter_numbering', [
            'prefix' => '800',
            'unit_code' => 'UNIT-A',
            'separator' => '/',
            'include_month' => false,
            'include_year' => false,
            'next_sequence' => 42,
        ]);
    }

    public function test_existing_units_continue_their_own_history_and_new_units_start_at_one(): void
    {
        Letter::create([
            'type' => 'Keluar', 'unit_code' => 'UNIT-B', 'number' => '800/007/UNIT-B',
            'subject' => 'Riwayat unit B', 'external_party' => 'Instansi',
            'letter_date' => now()->toDateString(), 'status' => 'Selesai',
        ]);

        Livewire::test('dashboard')
            ->call('openLetterForm', 'Keluar', 'UNIT-A')->assertSet('number', '800/042/UNIT-A')
            ->set('unitCode', 'UNIT-B')->assertSet('number', '800/008/UNIT-B')
            ->set('unitCode', 'UNIT-C')->assertSet('number', '800/001/UNIT-C');
    }

    public function test_saving_a_letter_only_advances_its_unit_even_when_the_number_jumps(): void
    {
        Livewire::test('dashboard')
            ->call('openLetterForm', 'Keluar', 'UNIT-B')
            ->set('number', '800/050/UNIT-B')
            ->set('subject', 'Nomor lompat unit B')->set('externalParty', 'Instansi')
            ->call('saveLetter')->assertHasNoErrors();

        $this->assertSame('800/051/UNIT-B', LetterNumbering::nextLetterNumber('UNIT-B'));
        $this->assertSame('800/042/UNIT-A', LetterNumbering::nextLetterNumber('UNIT-A'));
        $this->assertSame('800/001/UNIT-C', LetterNumbering::nextLetterNumber('UNIT-C'));

        LetterNumbering::advanceNextLetterSequence('800/003/UNIT-B', 'UNIT-B');
        $this->assertSame('800/051/UNIT-B', LetterNumbering::nextLetterNumber('UNIT-B'));
    }

    public function test_settings_can_change_each_unit_counter_independently(): void
    {
        Livewire::test('settings')
            ->call('setSettingsTab', 'nomor')
            ->assertSet('numbering.next_sequence', 42)
            ->set('numbering.next_sequence', 60)->call('saveNumbering')->assertHasNoErrors()
            ->set('numbering.unit_code', 'UNIT-B')->assertSet('numbering.next_sequence', 1)
            ->set('numbering.next_sequence', 9)->call('saveNumbering')->assertHasNoErrors()
            ->set('numbering.unit_code', 'UNIT-A')->assertSet('numbering.next_sequence', 60);

        $this->assertSame('800/060/UNIT-A', LetterNumbering::nextLetterNumber('UNIT-A'));
        $this->assertSame('800/009/UNIT-B', LetterNumbering::nextLetterNumber('UNIT-B'));
        $this->assertSame('800/001/UNIT-C', LetterNumbering::nextLetterNumber('UNIT-C'));
    }

    public function test_creating_a_new_default_unit_keeps_the_existing_units_counters(): void
    {
        Livewire::test('settings')
            ->set('unitCode', 'UNIT-D')->set('unitName', 'Unit D')->set('unitIsDefault', true)
            ->call('saveUnit')->assertHasNoErrors();

        $this->assertSame('800/001/UNIT-D', LetterNumbering::nextLetterNumber('UNIT-D'));
        $this->assertSame('800/042/UNIT-A', LetterNumbering::nextLetterNumber('UNIT-A'));

        Livewire::test('settings')->call('deleteUnit', 3);
        $this->assertSame('800/042/UNIT-A', LetterNumbering::nextLetterNumber('UNIT-A'));
        $this->assertArrayNotHasKey('UNIT-D', AppSetting::getValue('letter_numbering')['unit_sequences']);
    }

    public function test_mobile_creation_and_number_monitoring_use_independent_counters(): void
    {
        $this->actingAs(User::where('role', 'Admin Sekretariat')->firstOrFail(), 'sanctum');
        foreach (['UNIT-A' => '800/042/UNIT-A', 'UNIT-B' => '800/001/UNIT-B'] as $unit => $number) {
            $this->postJson('/api/mobile/letters', [
                'type' => 'Keluar', 'unit_code' => $unit, 'subject' => 'Surat '.$unit,
                'external_party' => 'Instansi', 'letter_date' => now()->toDateString(),
            ])->assertCreated()->assertJsonPath('letter.number', $number);
        }

        $this->getJson('/api/mobile/numbering?unit_code=UNIT-A')
            ->assertOk()->assertJsonPath('numbering.next_sequence', 43);
        $this->getJson('/api/mobile/numbering?unit_code=UNIT-B')
            ->assertOk()->assertJsonPath('numbering.next_sequence', 2);

        Livewire::test('number-monitor-page')->set('unitCode', 'UNIT-B')
            ->assertSee('800/002/UNIT-B');
        $settings = Livewire::test('settings')->set('numberMonitorUnit', 'UNIT-B');
        $this->assertSame(2, $settings->instance()->numberMonitor()['next_sequence']);
    }
}
