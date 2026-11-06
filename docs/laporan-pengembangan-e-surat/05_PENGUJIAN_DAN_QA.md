# Pengujian dan QA Aplikasi E-Surat

## Tujuan Pengujian

Pengujian dilakukan untuk memastikan fitur utama aplikasi berjalan sesuai kebutuhan, akses role aman, data tersimpan benar, dokumen dapat diunggah dan dibuka sesuai hak akses, serta API mobile dapat digunakan oleh aplikasi client.

Status eksekusi terakhir: seluruh fungsi yang dikembangkan telah diuji melalui test otomatis dan terdokumentasi pada `09_LAPORAN_HASIL_PENGUJIAN.md`.

## Tool Pengujian

- PHPUnit 12
- Laravel Feature Test
- Laravel RefreshDatabase
- Livewire Test
- Storage fake untuk pengujian upload file
- Sanctum guard untuk simulasi API mobile

## Cakupan Test yang Tersedia

Test feature di repository mencakup:

- Halaman utama berhasil dibuka oleh pengguna login.
- Guest diarahkan ke login.
- Endpoint versi mobile mengembalikan informasi update.
- Login mobile menghasilkan token dan dapat membuka dashboard.
- Endpoint penomoran mobile mengembalikan nomor berikutnya dan nomor kosong.
- Disposisi mobile dapat dikirim ke banyak penerima dengan scan.
- Disposisi dibatasi hanya untuk surat masuk.
- Disposisi mandiri dapat dibuat.
- Penomoran SK mobile dapat create, update, delete, dan membuka file.
- Halaman tugas membutuhkan login.
- Settings hanya dapat diakses admin.
- Halaman pimpinan dan kepala bagian dibatasi role.
- Menu dashboard disesuaikan dengan role.
- Inbox pimpinan dan kepala bagian menampilkan tugas yang relevan.
- Admin dapat mengelola pengguna dan pengguna nonaktif tidak dapat login.
- Admin dapat mengelola penerima disposisi.
- Disposisi menggunakan master penerima.
- Dashboard dan halaman pimpinan dapat upload file disposisi.
- Disposisi dapat dikirim ke banyak penerima.
- Kepala bagian dapat update status disposisi sendiri.
- Status surat masuk mengikuti status disposisi.
- Kepala bagian dapat meneruskan disposisi.
- Modal detail menampilkan aksi sesuai role.

## Cara Menjalankan Test

Dari root repository:

```bash
composer test
```

Atau langsung:

```bash
php artisan test
```

## Skenario UAT

### UAT-01 Login dan Hak Akses

Langkah:

1. Login sebagai Admin Sekretariat.
2. Pastikan menu settings, monitoring nomor, dan penomoran SK tampil.
3. Logout.
4. Login sebagai Staf Sekretariat.
5. Pastikan menu administratif tidak tampil.

Hasil yang diharapkan:

- Setiap role hanya melihat menu sesuai kewenangan.

### UAT-02 Pencatatan Surat Masuk

Langkah:

1. Login sebagai Admin Sekretariat.
2. Buat surat masuk baru.
3. Isi nomor, agenda, perihal, pihak luar, tanggal, sifat, prioritas, dan tenggat.
4. Unggah dokumen surat.
5. Simpan.

Hasil yang diharapkan:

- Surat muncul pada dashboard.
- Dokumen dapat direview/download oleh pengguna berwenang.
- Activity log tercatat saat dokumen direview atau diunduh.

### UAT-03 Disposisi Pimpinan

Langkah:

1. Login sebagai Pimpinan MRP atau Sekretaris Pribadi.
2. Buka halaman pimpinan.
3. Pilih surat masuk.
4. Buat disposisi ke satu atau beberapa penerima.
5. Tambahkan scan disposisi jika tersedia.

Hasil yang diharapkan:

- Disposisi tersimpan.
- Status surat menjadi `Disposisi Pimpinan`.
- Penerima melihat tugas pada inbox.

### UAT-04 Tindak Lanjut Kepala Bagian

Langkah:

1. Login sebagai Kepala Bagian.
2. Buka halaman kepala bagian atau tugas saya.
3. Buka disposisi yang diterima.
4. Ubah status menjadi `Diproses`.
5. Teruskan disposisi ke penerima lanjutan bila diperlukan.
6. Ubah status menjadi `Selesai`.

Hasil yang diharapkan:

- Status disposisi berubah.
- Status surat tersinkron.
- Disposisi lanjutan memiliki parent disposition.

### UAT-05 Penomoran SK

Langkah:

1. Login sebagai Admin Sekretariat.
2. Buka halaman penomoran SK.
3. Tambahkan nomor SK dengan unit, kode klasifikasi, judul, tanggal, status, dan file.
4. Edit nomor SK.
5. Buka dan download file.
6. Hapus nomor SK uji.

Hasil yang diharapkan:

- Nomor SK unik.
- File tersimpan dan dapat dibuka.
- Update dan hapus berjalan sesuai hak akses.

### UAT-06 API Mobile

Langkah:

1. Login ke `/api/mobile/login`.
2. Simpan token Bearer.
3. Buka `/api/mobile/dashboard`.
4. Ambil daftar surat dari `/api/mobile/letters`.
5. Buat disposisi dari endpoint mobile.
6. Update status disposisi.

Hasil yang diharapkan:

- Semua endpoint protected menerima token valid.
- Response JSON sesuai struktur yang dibutuhkan aplikasi mobile.

## Checklist QA Sebelum Produksi

- Semua test otomatis lulus. Status terakhir: 79 test lulus, 388 assertion lulus.
- Database migration berjalan bersih pada environment baru.
- Seeder awal menghasilkan user dan referensi yang valid.
- Upload dokumen PDF/JPG/PNG berhasil.
- Storage link tersedia.
- Role akses dokumen sudah diuji manual.
- Export CSV berhasil dibuka di spreadsheet.
- API mobile diuji dengan token valid dan token invalid.
- Backup dan restore diuji minimal satu kali.
- Log aplikasi tidak menunjukkan error kritis.

## Rekomendasi Tambahan Test

- Test akses dokumen untuk setiap role dan kombinasi disposisi.
- Test validasi ukuran dan tipe file upload.
- Test approval end-to-end untuk surat keluar.
- Test conflict nomor surat dan nomor SK.
- Test API pagination dan filter pada dataset besar.
- Test backup/restore pada salinan data produksi.

