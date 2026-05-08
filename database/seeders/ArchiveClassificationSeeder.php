<?php

namespace Database\Seeders;

use App\Models\ArchiveClassification;
use Illuminate\Database\Seeder;

class ArchiveClassificationSeeder extends Seeder
{
    /**
     * Seed starter archive classification codes based on Permendagri 83/2022.
     */
    public function run(): void
    {
        $classifications = [
            ['code' => '000', 'name' => 'Umum', 'parent_code' => null],
            ['code' => '000.1', 'name' => 'Ketatausahaan dan Kerumahtanggaan', 'parent_code' => '000'],
            ['code' => '000.1.1', 'name' => 'Telekomunikasi', 'parent_code' => '000.1'],
            ['code' => '000.1.2', 'name' => 'Perjalanan Dinas Dalam Negeri', 'parent_code' => '000.1'],
            ['code' => '000.1.2.1', 'name' => 'Perjalanan Dinas Kepala Daerah', 'parent_code' => '000.1.2'],
            ['code' => '000.1.2.2', 'name' => 'Perjalanan Dinas DPRD', 'parent_code' => '000.1.2'],
            ['code' => '000.1.2.3', 'name' => 'Perjalanan Dinas Pegawai', 'parent_code' => '000.1.2'],
            ['code' => '000.1.3', 'name' => 'Perjalanan Dinas Luar Negeri', 'parent_code' => '000.1'],
            ['code' => '000.1.4', 'name' => 'Penggunaan Fasilitas Kantor', 'parent_code' => '000.1'],
            ['code' => '000.2', 'name' => 'Perlengkapan', 'parent_code' => '000'],
            ['code' => '000.3', 'name' => 'Pengadaan', 'parent_code' => '000'],
            ['code' => '000.4', 'name' => 'Perpustakaan', 'parent_code' => '000'],
            ['code' => '000.5', 'name' => 'Kearsipan', 'parent_code' => '000'],
            ['code' => '100', 'name' => 'Pemerintahan', 'parent_code' => null],
            ['code' => '200', 'name' => 'Politik', 'parent_code' => null],
            ['code' => '300', 'name' => 'Keamanan dan Ketertiban', 'parent_code' => null],
            ['code' => '400', 'name' => 'Kesejahteraan Rakyat', 'parent_code' => null],
            ['code' => '500', 'name' => 'Perekonomian', 'parent_code' => null],
            ['code' => '600', 'name' => 'Pekerjaan Umum dan Ketenagaan', 'parent_code' => null],
            ['code' => '700', 'name' => 'Pengawasan', 'parent_code' => null],
            ['code' => '800', 'name' => 'Kepegawaian', 'parent_code' => null],
            ['code' => '900', 'name' => 'Keuangan', 'parent_code' => null],
        ];

        foreach ($classifications as $classification) {
            ArchiveClassification::updateOrCreate(
                ['code' => $classification['code']],
                [
                    'name' => $classification['name'],
                    'parent_code' => $classification['parent_code'],
                    'description' => 'Kode klasifikasi arsip lingkungan pemerintah daerah sesuai Permendagri Nomor 83 Tahun 2022.',
                ],
            );
        }
    }
}
