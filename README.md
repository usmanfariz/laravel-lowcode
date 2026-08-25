# Low-Code Form & Report Generator

Platform internal berbasis Laravel untuk membuat CRUD dan laporan **tanpa menulis kode**.
Arahkan ke sebuah tabel di database, dan aplikasi menghasilkan form, halaman daftar,
validasi, hak akses, serta laporan — semuanya dari metadata yang tersimpan di database,
bukan dari kode yang di-generate ke berkas.

```
Browser → AdminLTE → Laravel → Form/Report Engine → Metadata DB → Tabel Bisnis
```

## Apa yang membedakannya

**Tidak ada kode yang di-generate.** Definisi form dan laporan hidup sebagai baris di
tabel metadata. Mengubah sebuah field berarti mengubah satu baris — bukan menulis ulang
controller, request, dan view lalu berharap ketiganya tetap sinkron.

**Whitelist adalah gerbangnya.** Tabel `data_sources` menentukan tabel mana yang boleh
disentuh engine, dan kolom mana yang diblokir. Metadata bisa disunting lewat UI, jadi
metadata diperlakukan sebagai masukan yang tidak dipercaya — sama seperti input dari
request. Nama tabel atau kolom yang tidak lolos whitelist ditolak, bukan diloloskan
diam-diam.

**Aplikasi tidak membuat tabel bisnis.** Engine hanya membaca struktur tabel yang sudah
ada lewat `information_schema`. Koneksi produksi tidak perlu — dan sebaiknya tidak
punya — hak DDL.

## Kemampuan

| | |
|---|---|
| **Generator CRUD** | Pilih tabel → form, halaman daftar, validasi, dan permission dibuat otomatis dari struktur kolom |
| **Form Builder** | Sunting field, kolom daftar, baris detail, tombol aksi, dan tata letak visual; setiap perubahan terekam dan bisa dikembalikan |
| **Report Builder** | Join, agregat, `GROUP BY`, filter dengan 13 operator, semuanya lewat UI |
| **Master-detail** | Satu induk dengan banyak baris anak dalam satu form |
| **Hak akses** | Role, permission per aksi, dan pembatasan data per baris (row-level scope) |
| **Ekspor** | Excel, CSV, PDF, dan Cetak; data besar otomatis dikerjakan di latar belakang |
| **Log aktivitas** | Setiap perubahan tercatat lengkap dengan nilai lama dan barunya |

## Kebutuhan

- PHP 8.3 dengan ekstensi `pdo_mysql`, `zip`, `gd`, `mbstring`
- MySQL 5.7.7 ke atas (`utf8mb4`)
- Composer

## Pemasangan

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Sesuaikan koneksi database di `.env`, lalu:

```bash
php artisan migrate
php artisan db:seed --class=MetadataSeeder
php artisan storage:link
php artisan serve
```

`MetadataSeeder` aman dijalankan berulang — menambah permission baru tidak menuntut
`migrate:fresh`. Password dan setelan yang sudah Anda ubah tidak akan ditimpa.

Login awal: **`admin@example.com`** / **`Admin#12345`** — ganti segera setelah masuk.

### Data contoh (opsional)

```bash
php artisan db:seed --class=DemoProductSeeder   # form CRUD + tabel demo
php artisan db:seed --class=DemoReportSeeder    # dua laporan contoh
php artisan db:seed --class=DemoFormSeeder      # contoh renderer di atas tabel menus
```

Tabel demo dibuat oleh migrasi `2026_08_25_1000*` dan aman dihapus:

```bash
php artisan migrate:rollback --step=4
rm database/migrations/2026_08_25_1000*_demo_*.php
```

### Queue worker dan penjadwal

Queue worker diperlukan agar ekspor data besar berjalan. Tanpa ini, ekspor besar akan
mengantre selamanya:

```bash
php artisan queue:work
```

Penjadwal membuang berkas ekspor yang lewat masa simpan. Tambahkan satu entri cron:

```
* * * * * cd /path/ke/proyek && php artisan schedule:run >> /dev/null 2>&1
```

## Menjalankan test

```bash
php artisan test
```

161 test. Sebagian besar berjalan di SQLite in-memory tanpa persiapan apa pun.

19 test menyentuh perilaku yang hanya muncul di MySQL (`information_schema`, kolom
`ENUM`, penolakan nama kolom ganda pada derived table) dan **dilewati otomatis** sampai
database ujinya disiapkan:

```bash
sudo mysql -e "CREATE DATABASE laravel_lowcode_test
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    GRANT ALL ON laravel_lowcode_test.* TO 'lowcode'@'127.0.0.1';"
```

## Dokumentasi

| Berkas | Isi |
|---|---|
| [`docs/TUTORIAL.md`](docs/TUTORIAL.md) | Panduan penggunaan langkah demi langkah |
| [`docs/RANCANGAN.md`](docs/RANCANGAN.md) | Rancangan sistem, keputusan desain, dan batasannya |
| [`database/migrations/README-database.md`](database/migrations/README-database.md) | Rincian skema metadata |

## Teknologi

Laravel 13 · MySQL · AdminLTE 3 · Bootstrap 4 · jQuery · DataTables · Select2 ·
Laravel Excel · DomPDF

Aset frontend dimuat lewat CDN, jadi tidak perlu Node.js untuk menjalankan aplikasi.

## Lisensi

MIT.
