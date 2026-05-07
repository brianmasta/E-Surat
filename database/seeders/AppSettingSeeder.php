<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AppSetting::putValue('agency', [
            'name' => 'Sekretariat MRP Provinsi Papua Tengah',
            'unit' => 'Majelis Rakyat Papua Provinsi Papua Tengah',
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

        AppSetting::putValue('workflow', [
            'max_upload_mb' => 5,
            'allowed_files' => 'PDF, JPG, JPEG, PNG',
            'auto_archive_done' => true,
            'notify_disposition' => true,
        ]);

        AppSetting::putValue('roles', [
            ['name' => 'Admin Sekretariat', 'description' => 'Mencatat surat, mengunggah dokumen, dan mengelola arsip Sekretariat MRP.'],
            ['name' => 'Pimpinan MRP', 'description' => 'Membaca surat masuk dan mengirim disposisi elektronik.'],
            ['name' => 'Staf Sekretariat', 'description' => 'Menerima instruksi, memproses surat, dan memperbarui status.'],
        ]);
    }
}
