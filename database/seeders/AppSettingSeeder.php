<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Http\Controllers\Api\Mobile\AppVersionController;
use Illuminate\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AppSetting::putValue('agency', [
            'app_name' => 'E-Surat MRP Papua Tengah',
            'short_name' => 'MRP',
            'name' => 'Sekretariat MRP Provinsi Papua Tengah',
            'unit' => 'Majelis Rakyat Papua Provinsi Papua Tengah',
            'leader_title' => 'Pimpinan MRP Papua Tengah',
            'city' => 'Papua Tengah',
            'address' => 'Provinsi Papua Tengah',
            'email' => 'sekretariat@mrp-papuatengah.go.id',
            'phone' => '-',
        ]);

        AppSetting::putValue('letter_numbering', [
            'prefix' => '800',
            'unit_code' => 'SET-MRP',
            'separator' => '/',
            'include_month' => true,
            'include_year' => true,
            'next_sequence' => 19,
        ]);

        AppSetting::putValue('letter_units', [
            ['code' => 'SET-MRP', 'name' => 'Sekretariat MRP', 'description' => 'Unit surat sekretariat', 'is_default' => true],
            ['code' => 'MRP', 'name' => 'Majelis Rakyat Papua', 'description' => 'Unit surat lembaga', 'is_default' => false],
        ]);

        AppSetting::putValue('workflow', [
            'max_upload_mb' => 5,
            'allowed_files' => 'PDF, JPG, JPEG, PNG',
            'auto_archive_done' => true,
            'notify_disposition' => true,
        ]);

        AppSetting::putValue('roles', [
            ['name' => 'Admin Sekretariat', 'description' => 'Mencatat surat, mengunggah dokumen, dan mengelola arsip instansi.'],
            ['name' => 'Pimpinan MRP', 'description' => 'Membaca surat masuk dan mengirim disposisi elektronik.'],
            ['name' => 'Kepala Bagian', 'description' => 'Menerima disposisi pimpinan dan memantau tindak lanjut pada unit kerja.'],
            ['name' => 'Staf Sekretariat', 'description' => 'Menerima instruksi, memproses surat, dan memperbarui status.'],
        ]);

        AppSetting::putValue('mobile_versions', AppVersionController::defaults());
    }
}
