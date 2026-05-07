<?php

namespace Database\Seeders;

use App\Models\Letter;
use Illuminate\Database\Seeder;

class LetterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $letters = [
            [
                'type' => 'Masuk',
                'unit_code' => 'SET-MRP',
                'number' => '045/UM/2026',
                'subject' => 'Undangan rapat koordinasi persiapan layanan publik',
                'external_party' => 'Sekretariat Daerah',
                'letter_date' => '2026-05-04',
                'status' => 'Baru',
                'file_path' => 'dokumen/undangan-rapat-koordinasi.pdf',
            ],
            [
                'type' => 'Masuk',
                'unit_code' => 'MRP',
                'number' => '112/BPKAD/V/2026',
                'subject' => 'Permintaan verifikasi data aset triwulan',
                'external_party' => 'BPKAD Provinsi',
                'letter_date' => '2026-05-03',
                'status' => 'Disposisi',
                'file_path' => 'dokumen/verifikasi-data-aset.jpg',
                'dispositions' => [
                    [
                        'sender_name' => 'Pimpinan',
                        'recipient_name' => 'Kepala Subbagian Umum',
                        'instruction' => 'Cek kelengkapan data dan siapkan rekap sebelum Jumat.',
                        'status' => 'Belum Dibaca',
                    ],
                ],
            ],
            [
                'type' => 'Keluar',
                'unit_code' => 'SET-MRP',
                'number' => '800/017/SET-MRP/05/2026',
                'subject' => 'Balasan permintaan data pegawai non ASN',
                'external_party' => 'BKD Kabupaten',
                'letter_date' => '2026-05-02',
                'status' => 'Selesai',
                'file_path' => 'dokumen/balasan-data-pegawai.pdf',
            ],
            [
                'type' => 'Masuk',
                'unit_code' => 'MRP',
                'number' => '021/DINKES/V/2026',
                'subject' => 'Pemberitahuan jadwal sosialisasi aplikasi layanan',
                'external_party' => 'Dinas Kesehatan',
                'letter_date' => '2026-05-01',
                'status' => 'Diproses',
                'file_path' => 'dokumen/sosialisasi-aplikasi.pdf',
                'dispositions' => [
                    [
                        'sender_name' => 'Pimpinan',
                        'recipient_name' => 'Analis Kebijakan',
                        'instruction' => 'Koordinasikan peserta dan laporkan kebutuhan dukungan teknis.',
                        'status' => 'Diproses',
                    ],
                ],
            ],
            [
                'type' => 'Keluar',
                'unit_code' => 'MRP',
                'number' => '800/018/MRP/05/2026',
                'subject' => 'Surat tugas pendampingan arsip digital',
                'external_party' => 'Kantor Distrik Jayapura Utara',
                'letter_date' => '2026-05-05',
                'status' => 'Selesai',
                'file_path' => 'dokumen/surat-tugas-arsip.pdf',
            ],
        ];

        foreach ($letters as $data) {
            $dispositions = $data['dispositions'] ?? [];
            unset($data['dispositions']);

            $letter = Letter::create($data);

            foreach ($dispositions as $disposition) {
                $letter->dispositions()->create($disposition);
            }
        }
    }
}
