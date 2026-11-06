# Spesifikasi Kebutuhan Aplikasi E-Surat

## Tujuan Dokumen

Dokumen ini merangkum kebutuhan fungsional dan non-fungsional aplikasi E-Surat berdasarkan dokumen PRD dan implementasi repository.

## Kebutuhan Fungsional

### Autentikasi dan Pengguna

- Sistem menyediakan halaman login untuk pengguna web.
- Sistem menyediakan endpoint login mobile yang mengembalikan token Bearer.
- Sistem mendukung logout web dan API mobile.
- Sistem menyimpan status aktif pengguna.
- Sistem membatasi akses fitur berdasarkan role pengguna.

### Dashboard dan Pencarian Surat

- Sistem menampilkan daftar surat masuk dan keluar.
- Sistem menyediakan filter berdasarkan jenis, unit, status, prioritas, tenggat, tanggal, dan kata kunci.
- Sistem menampilkan status dan metadata utama surat.
- Sistem menyediakan export daftar surat ke CSV.

### Pengelolaan Surat

- Sistem mencatat surat masuk dan surat keluar.
- Sistem mendukung nomor agenda untuk surat masuk.
- Sistem mendukung nomor surat untuk surat keluar.
- Sistem menyimpan informasi perihal, pihak eksternal, tanggal surat, tanggal diterima, sifat, prioritas, tenggat, dan status.
- Sistem menyimpan informasi arsip seperti lokasi, boks, kategori retensi, tanggal retensi, dan catatan arsip.
- Sistem mendukung unggah file dokumen surat.
- Sistem mendukung lampiran surat.
- Sistem mendukung template surat keluar dan export DOCX.

### Disposisi

- Sistem memungkinkan pimpinan membuat disposisi atas surat masuk.
- Sistem memungkinkan sekretaris pribadi menginput scan disposisi pimpinan.
- Sistem memungkinkan disposisi ke satu atau beberapa penerima.
- Sistem memungkinkan disposisi mandiri tanpa surat terkait.
- Sistem memungkinkan kepala bagian meneruskan disposisi kepada penerima lain.
- Sistem memperbarui status surat masuk berdasarkan status disposisi.
- Sistem menyimpan metode input, penginput, scan disposisi, dan status disposisi.

### Penomoran Surat dan SK

- Sistem menghasilkan nomor surat keluar berdasarkan format yang dikonfigurasi.
- Sistem menghasilkan nomor agenda surat masuk.
- Sistem memonitor nomor urut terpakai dan nomor kosong.
- Sistem mencatat nomor SK dengan unit, kode klasifikasi, nomor urut, tahun, judul, tanggal, status, catatan, dan file.
- Sistem mencegah duplikasi nomor SK berdasarkan unit, tahun, dan sequence.

### Arsip dan Referensi

- Sistem menyimpan master kode klasifikasi arsip.
- Sistem menyediakan template import kode klasifikasi arsip.
- Sistem menyimpan daftar penerima disposisi.
- Sistem menyediakan endpoint referensi untuk aplikasi mobile.

### Approval

- Sistem mendukung pembuatan alur approval surat.
- Sistem mendukung approve dan reject approval.
- Sistem menyimpan status, aktor, catatan, waktu aksi, dan urutan approval.

## Kebutuhan Non-Fungsional

### Keamanan

- Password pengguna harus disimpan dalam bentuk hash.
- Endpoint mobile yang sensitif harus dilindungi `auth:sanctum`.
- Dokumen surat, lampiran, scan disposisi, dan file SK hanya dapat dibuka oleh pengguna berwenang.
- Aktivitas penting harus dicatat dalam activity log.

### Kinerja

- Dashboard harus mendukung pencarian dan filter tanpa beban penuh berlebihan.
- SQLite cukup untuk skala instansi tunggal dengan jumlah akses terbatas.
- Jika beban meningkat, database perlu dimigrasikan ke mesin database server.

### Ketersediaan dan Backup

- Database dan file storage harus dicadangkan rutin.
- Restore backup harus diuji berkala.
- Folder storage publik harus dibuat symbolic link melalui `php artisan storage:link` pada deployment.

### Maintainability

- Kode mengikuti struktur standar Laravel.
- Business logic pendukung ditempatkan pada namespace `App\Support`.
- Perubahan database dilakukan melalui migration.
- Test otomatis digunakan untuk menjaga regresi fitur utama.

### Kompatibilitas

- Backend membutuhkan PHP 8.3 atau lebih baru.
- Aplikasi menggunakan Laravel 13, Livewire 4, Laravel Sanctum 4, Vite 8, TailwindCSS 4, dan Composer/NPM.

## Acceptance Criteria Utama

- Pengguna tidak login diarahkan ke halaman login.
- Admin dapat mengakses settings dan fitur administratif.
- Role non-admin tidak dapat mengakses settings.
- Pimpinan dan sekretaris pribadi dapat membuka halaman pimpinan.
- Kepala bagian dapat membuka halaman kepala bagian.
- Staf tidak melihat menu yang tidak sesuai kewenangannya.
- API mobile login mengembalikan token dan dapat membuka dashboard.
- Disposisi surat masuk dapat dibuat untuk banyak penerima.
- Disposisi tidak dapat dibuat untuk surat keluar.
- Kepala bagian dapat memperbarui status disposisi sendiri.
- Status surat masuk berubah menjadi `Diproses` atau `Selesai` sesuai status disposisi.
- Nomor SK dapat dibuat, diperbarui, dihapus, dan memiliki file pendukung.
- Dokumen hanya dapat dibuka oleh pengguna yang berhak.

