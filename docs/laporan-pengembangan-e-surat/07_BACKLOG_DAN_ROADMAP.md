# Backlog dan Roadmap Pengembangan E-Surat

## Prinsip Prioritas

Prioritas disusun berdasarkan dampak terhadap operasional, keamanan data, stabilitas, dan kemudahan pengguna.

## Prioritas Tinggi

### 1. Backup Otomatis

Deskripsi:

- Menambahkan job atau script backup harian untuk database dan storage dokumen.
- Menyimpan backup ke lokasi terpisah.
- Menambahkan prosedur restore teruji.

Manfaat:

- Mengurangi risiko kehilangan arsip digital.

### 2. Audit Hak Akses Dokumen

Deskripsi:

- Menambah test dan laporan audit untuk akses dokumen per role.
- Memastikan kepala bagian hanya melihat dokumen yang terkait disposisinya.

Manfaat:

- Memperkuat keamanan dokumen internal.

### 3. Dokumentasi API Mobile Lengkap

Deskripsi:

- Menulis contoh request, response, error code, dan struktur payload.
- Menambahkan koleksi Postman atau OpenAPI.

Manfaat:

- Memudahkan pengembangan dan maintenance aplikasi mobile.

### 4. Monitoring Error Produksi

Deskripsi:

- Menambahkan integrasi error reporting.
- Menyusun dashboard error dan log penting.

Manfaat:

- Mempercepat deteksi masalah produksi.

## Prioritas Menengah

### 5. Migrasi Database untuk Skala Lebih Besar

Deskripsi:

- Menyiapkan opsi migrasi dari SQLite ke MySQL atau PostgreSQL.
- Menguji performa dashboard dan API pada data besar.

Manfaat:

- Menyiapkan aplikasi untuk jumlah pengguna dan data yang lebih tinggi.

### 6. Approval Surat Keluar Lebih Lengkap

Deskripsi:

- Memperluas workflow approval, notifikasi, dan riwayat revisi.
- Menambahkan tampilan timeline approval.

Manfaat:

- Memperjelas proses validasi surat keluar.

### 7. Dashboard Audit dan Statistik

Deskripsi:

- Menampilkan statistik surat masuk/keluar, waktu penyelesaian, disposisi terbuka, nomor kosong, dan aktivitas pengguna.

Manfaat:

- Membantu pimpinan memantau kinerja administrasi.

### 8. Notifikasi

Deskripsi:

- Menambahkan notifikasi web atau mobile untuk disposisi baru, tenggat mendekat, dan approval.

Manfaat:

- Mengurangi keterlambatan tindak lanjut.

## Prioritas Rendah

### 9. Import Data Lama

Deskripsi:

- Menyediakan template import surat lama dari CSV atau Excel.

Manfaat:

- Mempercepat migrasi arsip historis.

### 10. OCR Dokumen

Deskripsi:

- Menambahkan OCR untuk dokumen scan agar isi surat dapat dicari.

Manfaat:

- Meningkatkan kemampuan pencarian arsip.

### 11. Tanda Tangan Elektronik

Deskripsi:

- Menyiapkan integrasi tanda tangan elektronik jika kebijakan instansi mendukung.

Manfaat:

- Memperkuat proses surat keluar digital end-to-end.

## Roadmap Fase

| Fase | Fokus | Output |
| --- | --- | --- |
| Fase 1 | Stabilitas produksi | Backup otomatis, audit akses dokumen, dokumentasi API |
| Fase 2 | Efisiensi kerja | Notifikasi, dashboard statistik, approval lanjutan |
| Fase 3 | Skalabilitas | Migrasi database, optimasi query, object storage |
| Fase 4 | Digitalisasi lanjutan | OCR, import arsip lama, tanda tangan elektronik |

## Definition of Done Pengembangan Lanjutan

- Kode selesai dan mengikuti pola Laravel yang sudah ada.
- Migration dan seeder tersedia bila ada perubahan data.
- Test otomatis ditambahkan untuk fitur atau bug fix yang berisiko.
- Dokumentasi pengguna atau teknis diperbarui.
- Fitur diuji manual pada alur utama.
- Tidak ada regresi pada `composer test`.

