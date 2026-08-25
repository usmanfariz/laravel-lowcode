# Database Metadata — Low-Code Form & Report Generator

## Cara pasang

```bash
# salin ke project Laravel
cp database/migrations/*.php  <project>/database/migrations/
cp database/seeders/*.php     <project>/database/seeders/

php artisan migrate
php artisan db:seed --class=MetadataSeeder
```

Login awal: `admin@example.com` / `Admin#12345` — **ganti segera**.

Pastikan `config/database.php` memakai `utf8mb4` dan MySQL 5.7.7 ke atas
(dibutuhkan untuk indeks pada kolom varchar 150 dengan utf8mb4).

## Daftar tabel

| Kelompok | Tabel |
|---|---|
| Akses | `roles`, `permissions`, `role_permissions`, `user_roles` |
| Navigasi | `menus` |
| Keamanan data | `data_sources` |
| Form | `forms`, `form_details`, `form_fields`, `form_field_options`, `form_list_columns`, `form_actions`, `form_versions` |
| Report | `reports`, `report_joins`, `report_columns`, `report_filters` |
| Sistem | `settings`, `activity_logs` |

## Keputusan desain

**`data_sources` adalah gerbang keamanan.** Setiap nama tabel yang muncul di
metadata (`forms.table_name`, `form_details.table_name`, `reports.base_table`,
`report_joins.table_name`, `form_fields.data_source`) wajib dicocokkan ke tabel
ini sebelum masuk query. Tanpa lapisan ini, siapa pun yang bisa menyunting
metadata otomatis bisa membaca seluruh database. Kolom `blocked_columns`
menutup kolom sensitif seperti `password`.

**`form_fields` melayani form induk dan detail.** Field milik baris detail
ditandai dengan `form_detail_id` terisi. Ini menghindari duplikasi struktur
tabel yang isinya sama persis.

**Kolom list dipisah dari field form.** `form_list_columns` berdiri sendiri
karena kolom pada halaman index sering berasal dari join (`category.name`,
bukan `category_id`) atau ekspresi, sehingga tidak selalu punya pasangan di
`form_fields`.

**Report default memakai mode `builder`.** Mode `raw` disediakan tapi harus
dikunci: hanya role dengan permission `system.raw_query`, dan wajib divalidasi
SELECT-only sebelum disimpan. Setting `security.allow_raw_query` default mati.

**`settings` menggambarkan halamannya sendiri.** Selain nilai dan `value_type`,
tiap baris membawa `input_type`, `options`, dan `order_no` (migrasi
`2026_08_25_100010`) — cukup untuk menggambar isiannya di halaman Pengaturan
tanpa kode tambahan. `value_type` saja tidak memadai: teks satu baris, area
teks, dan daftar pilihan sama-sama bertipe `string`. Menambah pengaturan baru
berarti menambah satu baris di seeder, bukan menyunting controller dan view.

**Row-level access** memakai pasangan `roles.data_scope` dan
`forms.scope_column` / `reports.scope_column`, dibandingkan dengan
`users.scope_value`.

## Catatan penting sebelum lanjut coding

1. **Indeks unik `form_fields_unique_field` tidak menutup semua kasus.**
   MySQL memperlakukan NULL sebagai nilai berbeda, sehingga baris dengan
   `form_detail_id` NULL (field form induk) masih bisa duplikat `field_name`.
   Validasi tambahan wajib dilakukan di FormRequest saat menyimpan field.

2. **Kolom `expression` pada `form_list_columns` dan `report_columns` adalah
   titik rawan.** Isinya masuk ke klausa SELECT. Batasi hanya untuk
   `system.raw_query`, dan saring dengan whitelist pola (huruf, angka, titik,
   kurung, operator aritmetika, fungsi agregat) sebelum disimpan.

3. **Cache metadata.** Satu halaman form memuat 4–5 tabel metadata. Cache per
   `forms.code`, hapus cache saat builder menyimpan atau saat
   `form_versions` bertambah.

4. **Tabel bisnis tidak dibuat oleh aplikasi ini.** Engine hanya membaca
   struktur tabel yang sudah ada lewat `information_schema`. Aplikasi tidak
   boleh punya hak DDL (CREATE / ALTER / DROP) pada koneksi produksi.
