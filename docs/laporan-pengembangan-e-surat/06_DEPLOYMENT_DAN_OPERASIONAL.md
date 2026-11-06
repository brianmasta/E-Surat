# Deployment dan Operasional Aplikasi E-Surat

## Prasyarat Server

- PHP 8.3 atau lebih baru.
- Composer.
- Node.js dan NPM.
- SQLite extension untuk PHP.
- Web server seperti Nginx atau Apache.
- Akses tulis ke folder `storage` dan `bootstrap/cache`.

## Instalasi Awal

```bash
composer install --no-dev --optimize-autoloader
npm install --ignore-scripts
npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Jika menggunakan SQLite, pastikan file database tersedia dan path pada `.env` benar.

## Konfigurasi `.env` Penting

```env
APP_NAME="E-Surat"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-instansi.go.id

DB_CONNECTION=sqlite
DB_DATABASE=/path/ke/database/database.sqlite

FILESYSTEM_DISK=public
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

## Struktur File Operasional

| Path | Fungsi |
| --- | --- |
| `database/database.sqlite` | File database jika memakai SQLite |
| `storage/app/public` | Dokumen surat, lampiran, scan disposisi, file SK |
| `storage/logs` | Log Laravel |
| `public/build` | Asset frontend hasil build |
| `bootstrap/cache` | Cache konfigurasi, route, dan view |

## Perintah Operasional

### Migrasi Database

```bash
php artisan migrate --force
```

### Menjalankan Test

```bash
composer test
```

### Build Asset

```bash
npm run build
```

### Membersihkan Cache

```bash
php artisan optimize:clear
```

### Membuat Cache Produksi

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Backup

Backup minimal harus mencakup:

- File database SQLite.
- Folder `storage/app/public`.
- File `.env` atau catatan konfigurasi production yang aman.

Contoh backup manual:

```bash
mkdir -p backups
cp database/database.sqlite backups/database-$(date +%Y%m%d-%H%M%S).sqlite
tar -czf backups/storage-public-$(date +%Y%m%d-%H%M%S).tar.gz storage/app/public
```

Untuk Windows PowerShell:

```powershell
$stamp = Get-Date -Format "yyyyMMdd-HHmmss"
New-Item -ItemType Directory -Force -Path backups | Out-Null
Copy-Item database\database.sqlite "backups\database-$stamp.sqlite"
Compress-Archive -Path storage\app\public -DestinationPath "backups\storage-public-$stamp.zip"
```

## Restore

Langkah restore:

1. Matikan sementara akses aplikasi jika sedang produksi.
2. Pulihkan file database.
3. Pulihkan folder `storage/app/public`.
4. Jalankan `php artisan optimize:clear`.
5. Uji login, dashboard, buka dokumen, dan export CSV.

## SOP Operasional Harian

- Admin memeriksa surat baru dan status disposisi.
- Admin memastikan dokumen yang diunggah dapat dibuka.
- Pimpinan atau sekretaris pribadi memproses surat masuk yang menunggu disposisi.
- Kepala bagian memperbarui status disposisi.
- Admin memantau nomor surat dan nomor SK.
- Admin mengecek log jika ada error atau pengguna melaporkan kendala.

## SOP Operasional Mingguan

- Jalankan backup database dan storage.
- Uji satu file backup secara sampling.
- Review daftar pengguna aktif.
- Review activity log untuk aktivitas tidak wajar.
- Review nomor kosong atau nomor yang belum dipakai.

## SOP Rilis Perubahan

1. Pull atau deploy perubahan kode.
2. Jalankan `composer install --no-dev --optimize-autoloader` jika dependency berubah.
3. Jalankan `npm install --ignore-scripts` dan `npm run build` jika asset berubah.
4. Jalankan `php artisan migrate --force` jika migration berubah.
5. Jalankan `php artisan optimize:clear`.
6. Jalankan cache produksi.
7. Uji smoke test: login, dashboard, buka dokumen, buat data uji bila perlu, dan cek API mobile.

