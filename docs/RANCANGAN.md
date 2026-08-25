# Rancangan Aplikasi Low-Code Form & Report Generator

> Revisi dokumen `Rancangan_Low_Code_Form_Report_Generator_Laravel.pdf`, disesuaikan
> dengan skema yang sudah berjalan. **Dokumen ini yang berlaku**; PDF asli disimpan
> sebagai catatan visi awal. Bila keduanya berbeda, ikuti dokumen ini.
>
> Terakhir disinkronkan: 25 Agustus 2026 — seluruh roadmap dan daftar §12 tuntas.

## 1. Konsep

Template Laravel yang sudah membawa autentikasi, permission, menu dinamis, generator
form, generator report, dan CRUD dinamis. Tujuannya mempercepat pembuatan aplikasi
internal tanpa menulis CRUD dan report dari nol.

## 2. Arsitektur

```
Browser → AdminLTE → Laravel → Form/Report Engine → Metadata DB → Application DB
```

Metadata menyimpan definisi form, field, report, kolom, filter, menu, dan permission.
Data bisnis tetap di tabel masing-masing (`products`, `customers`, `sales`, …).

**Aplikasi tidak membuat tabel bisnis.** Engine hanya membaca struktur tabel yang sudah
ada lewat `information_schema`. Koneksi produksi tidak boleh punya hak DDL
(CREATE / ALTER / DROP). Ini berlaku juga untuk fitur auto-generate CRUD (§11):
fitur itu *membaca* `information_schema`, bukan mengubah struktur.

## 3. Perubahan terhadap PDF asli

PDF menyebut 14 tabel inti. Skema berjalan punya 28 tabel. Tambahannya bukan hiasan —
masing-masing menutup lubang yang membuat rancangan asli tidak bisa dipakai:

| Tambahan | Alasan |
|---|---|
| `data_sources` | **Gerbang keamanan.** PDF tidak punya whitelist tabel sama sekali. Tanpa ini, siapa pun yang bisa menyunting metadata cukup mengisi `forms.table_name = 'users'` untuk membaca seluruh database |
| `report_joins` | PDF tidak punya konsep join, sehingga report multi-tabel mustahil |
| `form_details` | Master-detail (invoice + item) tidak ada di PDF |
| `form_list_columns` | Kolom halaman index sering berasal dari join (`category.name`, bukan `category_id`) atau ekspresi, jadi tidak selalu punya pasangan di `form_fields` |
| `form_actions` | Tombol per baris / toolbar / bulk tidak ada di PDF |
| `form_versions` | Riwayat perubahan definisi form |
| `roles.data_scope` + `scope_column` | Row-level access tidak dibahas PDF |

Perbedaan penamaan yang perlu diperhatikan — PDF memakai nama lama:

| PDF | Skema berjalan |
|---|---|
| `form_fields.type` + `input_type` | hanya `input_type` (satu kolom, enum) |
| `form_fields.required` / `readonly` | `is_required` / `is_readonly` |
| `*.status` | `is_active` (boolean) |
| `forms.route` | tidak ada — route diturunkan dari `forms.code` |
| `menus.route` / `permission` | `menus.target_value` / `permission_code` |

## 4. Tabel metadata (28 tabel)

| Kelompok | Tabel |
|---|---|
| Akses | `roles`, `permissions`, `role_permissions`, `user_roles`, `users` |
| Navigasi | `menus` |
| Keamanan data | `data_sources` |
| Form | `forms`, `form_details`, `form_fields`, `form_field_options`, `form_list_columns`, `form_actions`, `form_versions` |
| Report | `reports`, `report_joins`, `report_columns`, `report_filters` |
| Sistem | `settings`, `activity_logs` |
| Laravel | `migrations`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`, `password_reset_tokens` |

Rincian kolom ada di [`database/migrations/README-database.md`](../database/migrations/README-database.md).

## 5. Keamanan

Tiga lapis, semuanya wajib:

1. **Whitelist tabel.** Setiap nama tabel di metadata (`forms.table_name`,
   `form_details.table_name`, `reports.base_table`, `report_joins.table_name`,
   `form_fields.data_source`) dicocokkan ke `data_sources` sebelum masuk query.
   `data_sources.blocked_columns` menutup kolom sensitif seperti `password`.
2. **Raw query dikunci.** `reports.source_type = 'raw'` hanya untuk role dengan
   permission `system.raw_query`, wajib divalidasi SELECT-only sebelum disimpan.
   Setting `security.allow_raw_query` default mati.
3. **Ekspresi disaring `SqlExpressionGuard`.** `form_list_columns.expression` dan
   `report_columns.expression` masuk ke klausa SELECT, jadi dijaga izin
   `system.raw_query` **dan** tiga lapis penyaringan.

   > **Whitelist karakter saja tidak cukup.** `(SELECT password FROM users)` hanya
   > memakai huruf, spasi, dan kurung — versi pertama penyaring ini meloloskannya.
   > Karena itu `SqlExpressionGuard` menambahkan blocklist kata kunci SQL
   > (`select`, `from`, `union`, `sleep`, …), whitelist nama fungsi (hanya agregat,
   > matematika, tanggal, dan string dasar), penolakan komentar SQL (`/*`, `*/`, `--`),
   > dan pemeriksaan keseimbangan kurung.

Row-level access memakai pasangan `roles.data_scope` × `forms.scope_column` /
`reports.scope_column`, dibandingkan dengan `users.scope_value`.

## 6. Permission

Konvensi kode: `<prefix>.<action>`.

- Form memakai `forms.permission_prefix`; action: `view`, `create`, `edit`, `delete`,
  `export`, `print`.
- Report memakai `reports.permission_code` langsung.
- Permission sistem memakai prefix `system.` (lihat `MetadataSeeder`).

Middleware: `->middleware('permission:user.view')`. Beberapa kode dipisah koma bersifat
OR. Menu dengan `permission_code` kosong terbuka untuk semua user yang login.

Gate Laravel dijembatani ke tabel permission lewat `Gate::before` di
`AppServiceProvider`, sehingga `@can('user.edit')` di Blade langsung bekerja tanpa
mendaftarkan ability satu per satu.

> **Belum diputuskan:** action `approve` yang disebut PDF belum punya kolom pasangan di
> `forms` (yang ada `allow_create/edit/delete/export/print`). Tentukan saat modul
> approval dikerjakan.

## 7. Filter report

Operator tersedia di enum `report_filters.operator`: `=`, `!=`, `>`, `>=`, `<`, `<=`,
`like`, `not_like`, `between`, `in`, `not_in`, `is_null`, `is_not_null`.

**Format nilai filter — sudah diputuskan.** `report_filters.default_value`
(`varchar(255)`) diganti `default_values` bertipe `json` lewat migrasi
`2026_08_25_100002`. Nilainya **selalu larik**, walau operatornya bernilai tunggal:

| Operator | Bentuk tersimpan |
|---|---|
| `=`, `!=`, `>`, `>=`, `<`, `<=`, `like`, `not_like` | `["published"]` |
| `between` | `["2026-01-01", "2026-12-31"]` |
| `in`, `not_in` | `["draft", "published"]` |
| `is_null`, `is_not_null` | `[]` |

Pemisah koma sengaja tidak dipakai karena nilai teks bisa mengandung koma.
Di request, nilai dikirim sebagai `f[<id filter>]` — larik untuk `between` dan `in`.

`between` dengan satu ujung terisi diperlakukan sebagai `>=`, bukan diabaikan.

## 8. Stack frontend

Diputuskan: **AdminLTE 3 + Bootstrap 4 + jQuery 3 + DataTables + Select2**.

Asset dimuat lewat **CDN** di [`resources/views/layouts/adminlte/app.blade.php`](../resources/views/layouts/adminlte/app.blade.php),
bukan npm/Vite — mesin pengembangan saat ini tidak punya Node. Tailwind 4 + Vite 8
bawaan Laravel masih terpasang di `package.json` tapi tidak dipakai.

> Bila nanti Node dipasang, asset bisa dipindah ke npm + Vite. Selama belum, jangan
> hapus `vite.config.js` — `@vite` tidak dipakai di layout, jadi tidak mengganggu.

## 9. Struktur folder

```
app/
├── Helpers/settings.php          # helper setting(), terdaftar di composer autoload.files
├── Http/
│   ├── Controllers/
│   │   ├── Admin/{UserController, RoleController, MenuController}.php
│   │   ├── Auth/LoginController.php
│   │   └── DashboardController.php
│   ├── Middleware/
│   │   ├── CheckPermission.php   # alias: permission
│   │   └── EnsureUserIsActive.php# alias: active
│   └── Requests/
│       ├── Admin/{UserRequest, RoleRequest, MenuRequest}.php
│       └── Auth/LoginRequest.php
├── Models/                       # User, Role, Permission, Menu
└── Services/
    ├── MenuService.php           # pohon sidebar + cache
    ├── Form/                     # FormService, FormRenderer, FormValidator (belum)
    └── Report/                   # ReportService, ReportQueryBuilder, ReportExporter (belum)

resources/views/
├── layouts/adminlte/{app.blade.php, partials/}
├── auth/login.blade.php
├── dashboard/, forms/, reports/, admin/
└── components/{form/, report/}
```

## 10. Route CRUD dinamis

```
GET    /forms/{code}              list
GET    /forms/{code}/create       form tambah
POST   /forms/{code}              simpan
GET    /forms/{code}/{id}         detail
GET    /forms/{code}/{id}/edit    form ubah
PUT    /forms/{code}/{id}         perbarui
DELETE /forms/{code}/{id}         hapus
```

**Route builder dipisah** ke prefix `/builder` (`builder.forms.index`,
`builder.reports.index`) agar tidak bentrok dengan route data di atas.

Urutan pendaftaran penting: `/forms/{code}/create` harus didaftarkan sebelum
`/forms/{code}/{id}`, kalau tidak `create` akan tertangkap sebagai `{id}`.

## 11. Auto-generate CRUD dari tabel (tahap 8)

Route: `GET /builder/generate` (pilih tabel), `GET /builder/generate/{table}` (pratinjau),
`POST /builder/generate`. Dijaga permission `system.builder.form`.

| Kelas | Tanggung jawab |
|---|---|
| `TableInspector` | baca kolom, foreign key, indeks unik, dan nilai enum dari `information_schema` |
| `ColumnMapper` | turunkan satu kolom menjadi satu definisi field |
| `FormGenerator` | rangkai form + field + kolom list + permission + menu |

**Hanya membaca — tidak pernah menjalankan DDL.** Tabel yang boleh di-generate
dibatasi whitelist `data_sources`, dan kolom di `blocked_columns` tidak pernah muncul
sebagai field.

**Urutan penentuan `input_type`:** nilai enum → foreign key → `tinyint(1)` → petunjuk
nama kolom → tipe data. Nama diperiksa sebelum tipe karena kolom `email` bertipe
`varchar` lebih tepat jadi input email daripada teks biasa, dan itu hanya bisa
disimpulkan dari namanya.

Petunjuk nama hanya dipakai bila cocok dengan tipe datanya — kolom `total_item`
bertipe `int` tetap angka, bukan mata uang.

| Terdeteksi | Menjadi |
|---|---|
| `ENUM(...)` | `select` + `data_source_type = enum` + opsi statis cadangan |
| foreign key | `select2` + `data_source_type = table`, kolom label ditebak (`name`, `nama`, `title`, `code`, …) |
| `tinyint(1)` | `switch` |
| nama memuat `email` / `website` / `logo` / `file` / `harga` / `persen` | `email` / `url` / `image` / `file` / `currency` / `percentage` |
| `varchar > 255`, `text` | `textarea` |
| `NOT NULL` tanpa default | `is_required` |
| indeks unik satu kolom | `is_unique` |
| `character_maximum_length` | `validation: max:N` |

**Kolom yang tidak pernah jadi field:** primary key auto-increment, `created_at`,
`updated_at`, `deleted_at`, `created_by`, `updated_by`, `remember_token`,
`email_verified_at` — semuanya diurus engine.

**Flag form disesuaikan struktur nyata:** `use_soft_delete` menyala bila ada kolom
`deleted_at`, `use_audit_column` bila ada `created_by`/`updated_by`.

**Permission ikut dibuat** mengikuti konvensi `<prefix>.<action>` dan langsung
diberikan ke superadmin — tanpa ini form baru selalu 403 bahkan bagi superadmin.

Bila tabel tujuan relasi tidak ada di whitelist, kolom label tidak ditemukan dan
relasinya diturunkan jadi input biasa — lebih baik daripada membuat select yang pasti
gagal saat dibuka.

## 11a. Form Renderer (tahap 4)

Alur: `FormService::byCode()` memuat metadata → `FormRenderer` menyiapkan opsi dan
nilai awal tiap field → komponen Blade `<x-form.field>` menggambar HTML-nya.

**Komponen Blade** di `resources/views/components/form/`. Pemetaan `input_type` →
komponen ada di `FormField::component()`; satu komponen melayani beberapa tipe
(mis. `form.select` menangani `select`, `select2`, `multi_select`, `ajax_select`,
`autocomplete`).

**Sumber opsi** ditentukan `data_source_type`:

| Nilai | Asal opsi |
|---|---|
| `static` | tabel `form_field_options` |
| `table` | tabel lain lewat `DataSourceResolver::options()` — wajib lolos whitelist |
| `enum` | definisi kolom `ENUM` dibaca dari `information_schema` |
| `none` | tidak ada opsi |

`ajax_select` dan field ber-`depends_on` tidak memuat opsi di awal; keduanya memakai
endpoint `GET /forms/{code}/options/{field}`. Endpoint itu memverifikasi bahwa field
benar-benar milik form tersebut — id dari URL tidak dipercaya.

**Validasi** diturunkan `FormValidator` dari metadata. Selain aturan per `input_type`,
setiap select dibatasi nilainya di sisi server: `static` dan `enum` memakai `Rule::in`,
`table` memakai `Rule::exists`. Tanpa ini, select hanya membatasi di tampilan dan nilai
apa pun bisa dikirim langsung.

**Demo.** `php artisan db:seed --class=DemoFormSeeder` membuat form `demo_menu` di atas
tabel `menus` untuk mencoba renderer tanpa perlu tabel bisnis. Seeder itu membuka
`menus` dan `permissions` di `data_sources` sebagai **baca saja**, dan mendaftarkan
permission `demo_menu.*`. Hapus form dan kedua baris `data_sources` itu bila tidak
diperlukan lagi.

## 11b. Dynamic CRUD (tahap 5)

| Kelas | Tanggung jawab |
|---|---|
| `FormQueryBuilder` | query halaman list: select, join relasi, pencarian, sorting, scope |
| `FormRepository` | insert / update / delete, baris detail, kolom audit, soft delete |
| `FileHandler` | simpan & hapus berkas unggahan |
| `ActivityLogger` | catat aksi ke `activity_logs` |

Route: `GET /forms/{code}` (list), `/data` (DataTables), `/create`, `POST /forms/{code}`,
`/{id}/edit`, `PUT /forms/{code}/{id}`, `DELETE /forms/{code}/{id}`.

**Kolom list** dari `form_list_columns`. `source_type = 'relation'` menghasilkan
`LEFT JOIN` sehingga kolom menampilkan `category.name`, bukan `category_id`. Form tanpa
definisi kolom list memakai 6 field pertama sebagai cadangan.

**Row-level access.** `FormQueryBuilder::applyScope()` menyaring list, dan
`FormRepository::rowQuery()` menyaring baris tunggal — keduanya wajib, karena menyaring
list saja masih membiarkan user membuka baris unit lain lewat URL langsung. Baris di luar
scope menghasilkan **404**, bukan 403, agar keberadaannya tidak bocor.

`scope_value` kosong pada role ber-scope menutup semua baris, bukan membuka semua.
`scope_column` tidak pernah bisa diubah lewat form, dan baris baru selalu memakai
scope milik pembuatnya — bukan nilai dari request.

**Kolom audit dan timestamp** diisi otomatis bila kolomnya ada dan
`forms.use_audit_column` aktif. `use_soft_delete` mengisi `deleted_at` alih-alih
menghapus baris.

**Baris detail** ditulis ulang seluruhnya (hapus lalu sisipkan) setiap kali induknya
disimpan — lebih sederhana daripada melacak selisih baris yang dihapus di layar.

**Berkas unggahan.** Diganti → berkas lama dibuang. Dihapus permanen → berkasnya ikut
dibuang. **Soft delete → berkas dibiarkan**, karena barisnya masih bisa dikembalikan dan
tanpa berkasnya ia jadi tidak utuh.

**Optimistic locking.** Form edit membawa `__version` berisi `updated_at` saat form
dibuka. Bila baris sudah berubah sejak itu, penyimpanan **ditolak** dengan pesan yang
menyuruh memuat ulang — bukan menimpa pekerjaan orang lain diam-diam. Hanya berlaku
untuk tabel yang punya `updated_at`.

**Barcode dan QR.** `App\Services\CodeImageGenerator` menghasilkan **SVG**, bukan PNG:
tidak butuh ekstensi gambar, tetap tajam berapa pun ukuran cetaknya, dan bisa
disematkan langsung tanpa permintaan berkas terpisah. Barcode memakai Code 128
(dipahami hampir semua pemindai, menerima huruf maupun angka).

Nilai yang tidak bisa dikodekan mengembalikan `null`, bukan melempar exception —
satu produk tanpa kode tidak boleh mematikan seluruh halaman label.

QR memuat URL yang dibangun dari **`APP_URL`**, bukan host request. Label dicetak untuk
dipindai belakangan, sering dari perangkat lain; QR berisi `127.0.0.1` akan menunjuk
perangkat pemindainya sendiri.

**Contoh aksi yang bisa dicoba** ada di `App\Http\Controllers\Demo\ProductActionController`
dan didaftarkan `DemoProductSeeder`: setujui (per baris, dengan kondisi
`status = draft`), arsipkan (massal), dan cetak label (toolbar). Engine sengaja tidak
mengurus arti "setujui" atau "arsipkan" — itu urusan aplikasi. Blok route-nya diberi
prefix `demo/products` dan aman dihapus bersama demo lainnya.

**Tombol aksi** dari `form_actions` digambar di halaman list: `toolbar` di kepala kartu,
`row` di kolom aksi, `bulk` muncul setelah baris dicentang. `show_condition` dievaluasi
per baris di klien — nilai kolom yang dibutuhkan dikirim terpisah lewat `__cond` agar
tidak mengacaukan indeks kolom `c0..cN` milik DataTables. Aksi yang permission-nya tidak
dimiliki pengguna tidak pernah sampai ke klien.

**Demo.** `php artisan db:seed --class=DemoProductSeeder` membuat form `product` di atas
`demo_products`. Tabel demo-nya dari migrasi `2026_08_25_100001_create_demo_business_tables`
— satu-satunya migrasi yang membuat tabel bisnis, sengaja dipisah dan aman dihapus.

## 11c. Report Engine (tahap 6)

| Kelas | Tanggung jawab |
|---|---|
| `ReportService` | muat metadata, cache, penjagaan mode `raw` |
| `ReportQueryBuilder` | join, agregat, GROUP BY, filter, sorting, scope |
| `ReportFilterRenderer` | opsi dan nilai berlaku tiap filter |

Route: `GET /reports/{code}` dan `/reports/{code}/data`.

**Identifier berkualifikasi.** Kolom ditulis `alias.kolom` (mis. `p.price`, `k.name`).
`ReportQueryBuilder::qualify()` menolak alias yang tidak terdaftar sebagai tabel utama
atau tabel join — itu mencegah metadata menunjuk tabel yang tidak ikut di-join.

**Agregat dan GROUP BY.** Kolom dengan `aggregate` selain `none` dibungkus fungsi;
kolom ber-`is_group_column` masuk `GROUP BY`. Jumlah baris report beragregat dihitung
lewat subquery — `COUNT` langsung akan mengembalikan jumlah baris sebelum pengelompokan.

**Pengelompokan otomatis.** Bila tidak ada kolom bertanda `is_group_column` tapi report
mencampur kolom agregat dan non-agregat, kolom non-agregatnya dikelompokkan sendiri.
Campuran seperti `COUNT(id)` dengan `nama` hanya punya satu tafsir yang masuk akal —
"hitung per nama" — dan tanpa `GROUP BY`, MySQL dengan `only_full_group_by` menolaknya
dengan pesan yang tidak berarti apa-apa bagi pengguna.

Report yang **seluruh** kolomnya agregat tetap tanpa `GROUP BY`: itu memang satu baris
ringkasan, dan jumlah barisnya dilaporkan 1 — bukan jumlah baris sumbernya.

Ekspresi yang memuat fungsi agregat (`SUM(p.price * p.stock)`) dihitung sebagai agregat
walau kolom `aggregate`-nya `none`, sehingga tidak keliru ikut masuk `GROUP BY`. Lihat
`ReportColumn::isAggregated()`.

> Subquery penghitung itu memilih konstanta (`SELECT 1`), bukan kolom aslinya. Query
> tanpa `select` eksplisit jatuh ke `SELECT *`, dan pada report ber-join itu
> menghasilkan nama kolom ganda (mis. dua kolom `id`) yang **ditolak MySQL** sebagai
> derived table. SQLite mengizinkannya, jadi bug seperti ini tidak akan terlihat di
> suite SQLite — penjagaannya ada di `tests/Feature/ReportCountMysqlTest.php`.

**Soft delete.** `reports.use_soft_delete` ditambahkan migrasi `2026_08_25_100003`,
default **true**. Sebelumnya `forms` punya flag ini tapi `reports` tidak, sehingga report
memuat baris yang sudah dihapus lewat form. Flag hanya berlaku bila tabelnya memang punya
kolom `deleted_at`.

**Mode raw dijaga tiga lapis:** setting `security.allow_raw_query`, izin
`system.raw_query`, dan validasi SELECT-only (`ReportService::isSelectOnly()`) yang
menolak titik koma di tengah, kata kunci DML/DDL, `information_schema`, `mysql.`,
`INTO OUTFILE`, dan `LOAD_FILE`.

**Baris total** dihitung dari halaman yang sedang tampil saja. Total seluruh dataset
butuh query terpisah — dikerjakan bersama ekspor di tahap 7.

## 11d. Export (tahap 7)

Route: `GET /reports/{code}/export/{format}` dan `GET /forms/{code}/export/{format}`,
dengan `{format}` salah satu dari `xlsx`, `csv`, `pdf`, `print`.

| Kelas | Tanggung jawab |
|---|---|
| `ExportService` | mengubah judul kolom + baris menjadi berkas unduhan |
| `App\Exports\TabularExport` | lembar Excel generik (dipakai form maupun report) |
| `resources/views/exports/pdf.blade.php` | tata letak PDF |
| `resources/views/exports/print.blade.php` | halaman cetak browser |

**Ekspor mengikuti filter yang sedang berlaku,** bukan mengekspor tabel apa adanya —
URL ekspor dirakit dari form filter yang sama di sisi klien.

**Nilai diekspor sudah terformat** persis seperti di layar (`Rp 12.500.000,00`,
`25/08/2026`); boolean menjadi `Ya`/`Tidak` karena berkas tidak punya badge.

**Baris TOTAL pada ekspor dihitung dari seluruh dataset,** berbeda dari halaman list
yang hanya menjumlah halaman tampil — saat ekspor semua barisnya memang sudah ditarik.
Hanya kolom ber-`show_total` yang dijumlah; kolom `avg`/`max` sengaja dikosongkan karena
menjumlahkan rata-rata tidak bermakna.

**Izin format** dicek per report lewat `allow_export_excel` / `allow_export_pdf` /
`allow_export_csv` / `allow_print`; form memakai `allow_export` dan `allow_print` plus
permission `<prefix>.export`. Format di luar keempatnya ditolak di level route.

**Batas ukuran.** `ExportService::assertWithinLimit()` menolak ekspor yang melampaui
`reports.export_queue_threshold`, dengan batas keras 50.000 baris yang berlaku walau
ambang report lebih besar. CSV ditulis streaming, bukan dirakit di memori.

**Ekspor terantre.** Di atas ambang, pekerjaannya dipindah ke antrean: barisnya dicatat
di `export_jobs`, `App\Jobs\GenerateExport` menyusun berkasnya, dan pengguna diarahkan
ke halaman **Berkas Ekspor**. Cetak tidak pernah diantrekan — hasilnya halaman, bukan
berkas.

Izin pemesan diterapkan **ulang di worker**, sehingga berkas hasil tidak pernah memuat
baris yang tidak boleh dilihat pemesannya. Berkas disimpan di disk privat dan hanya bisa
diunduh oleh pemesannya.

> Butuh `php artisan queue:work` berjalan. Tanpa itu ekspor besar mengantre selamanya —
> halaman Berkas Ekspor menampilkan peringatan ini bila ada pekerjaan menggantung.

**Pembersihan berkas.** `exports:prune --days=7` terjadwal harian pukul 02.00 lewat
`routes/console.php`, dan tersedia juga sebagai tombol di halaman Berkas Ekspor
(hanya menyentuh milik penggunanya sendiri). Butuh satu entri cron:
`* * * * * cd <proyek> && php artisan schedule:run`. Opsi `--dry-run` menampilkan
yang akan dibuang tanpa menghapusnya.

## 11e. Form Builder — editor metadata

Route di bawah prefix `builder/`, dijaga permission `system.builder.form`:

| Route | Fungsi |
|---|---|
| `builder/forms` | daftar form |
| `builder/forms/{form}` | pengaturan form + riwayat versi |
| `builder/forms/{form}/fields` | daftar field, urutkan dengan drag & drop |
| `builder/forms/{form}/fields/{field}/edit` | editor satu field |
| `builder/forms/{form}/columns` | kolom halaman list, urutkan dengan drag & drop |
| `builder/forms/{form}/columns/{column}/edit` | editor satu kolom list |
| `builder/forms/{form}/columns/reset` | susun ulang kolom list dari field form |
| `builder/forms/{form}/restore/{version}` | kembalikan ke versi tersimpan |

**`code` dan `table_name` tidak dapat diubah.** Keduanya menentukan identitas form:
`code` dipakai di URL dan menu, `table_name` menentukan seluruh field. Menggantinya
sama saja membuat form baru.

**Validasi field bersifat struktural,** bukan sekadar format:

- `field_name` wajib benar-benar ada sebagai kolom di tabel target dan tidak sedang
  diblokir. Field yang menunjuk kolom tak ada baru gagal saat pengguna menyimpan
  data — jauh lebih mahal daripada ditolak di builder.
- Sumber data `table` wajib lolos whitelist `data_sources`, dan `value_field`,
  `label_field`, `data_order_by` diperiksa satu per satu ke kolom nyata.
- Sumber `static` wajib punya minimal satu opsi.

**Versioning.** Setiap perubahan merekam snapshot **sebelum** disimpan ke
`form_versions`, sehingga versi terekam adalah keadaan yang bisa dikembalikan.
Pemulihan sendiri juga di-snapshot lebih dulu, jadi mengembalikan versi bisa dibatalkan.

**Pembersihan cache.** `FormBuilderService::flush()` membuang cache id form, cache opsi
per id field, dan cache nilai enum. Tanpa membuang cache opsi, mengganti sumber data
field tidak terlihat sampai 10 menit berikutnya.

**Menghapus form tidak menyentuh tabel bisnis** — hanya definisi, opsi, kolom list,
riwayat versi, dan menu yang menunjuknya.

**Relasi `Form::allFields()`** dipakai builder karena `fields()` menyaring
`is_active` — justru di builder field nonaktif harus terlihat agar bisa dinyalakan lagi.

**Editor kolom list** menangani tiga jenis sumber, masing-masing dengan validasinya:

| Jenis | Yang diperiksa |
|---|---|
| `column` | kolom ada di tabel form dan tidak diblokir |
| `relation` | kolom kunci ada di tabel form; tabel tujuan lolos whitelist; kolom kunci dan label tujuan diperiksa satu per satu |
| `expression` | pemakai memegang `system.raw_query`, isi disaring `SqlExpressionGuard` |

Kolom yang tidak relevan dengan jenis sumber dikosongkan saat disimpan, supaya sisa
isian lama tidak terbawa. Kolom relasi diambil lewat `LEFT JOIN`, sehingga pencarian
dan pengurutan langsung padanya belum didukung — hal ini dinyatakan di formnya.

Tombol **Susun Ulang dari Field** mengisi ulang kolom list dari field form (6 pertama
yang layak tampil), untuk saat susunan terlanjur berantakan.

## 11f. Report Builder

Route di bawah `builder/reports`, dijaga permission `system.builder.report`:

| Route | Fungsi |
|---|---|
| `builder/reports` | daftar report |
| `builder/reports/create` | report baru — pilih tabel dasar dari whitelist |
| `builder/reports/{report}` | pengaturan, termasuk mode `raw` |
| `builder/reports/{report}/joins` | daftar & editor join |
| `builder/reports/{report}/columns` | kolom, urutkan dengan drag & drop |
| `builder/reports/{report}/filters` | filter, urutkan dengan drag & drop |

**`code` dan `base_table` dikunci setelah dibuat** — keduanya menentukan identitas
report dan seluruh referensi kolomnya.

**Referensi kolom ditulis `alias.kolom`.** Dropdown hanya menawarkan alias yang benar
terdaftar (tabel dasar + tabel join), dan setiap referensi divalidasi lewat
`ReportQueryBuilder::qualify()` — metode yang sama yang dipakai saat query dijalankan,
jadi tidak ada selisih antara yang lolos builder dan yang lolos runtime.

**Validasi yang tidak bisa disimpulkan dari tipe data:**

| Aturan | Alasan |
|---|---|
| kolom beragregat tidak boleh jadi kolom pengelompokan | MySQL menolak `SUM(x)` di dalam `GROUP BY` |
| kolom beragregat tidak boleh ikut pencarian | pencarian memakai `WHERE`, yang berjalan sebelum agregasi |
| alias join harus unik | dua tabel beralias sama membuat referensi kolom mendua |
| join tidak bisa dihapus bila aliasnya masih dipakai | kolom/filter yang menunjuknya akan gagal senyap |
| jumlah nilai default cocok dengan operator | `between` butuh dua, `is_null` tidak menerima nilai |

Kondisi join divalidasi terhadap alias yang berlaku **setelah** join itu ada — kalau
tidak, join pertama ke sebuah tabel selalu ditolak karena aliasnya belum terdaftar.

**Nilai default filter** diketik satu per baris di textarea, disimpan sebagai larik JSON
(§7). Opsi statis memakai format `nilai|label` per baris.

**Mode raw ditolak sejak di builder** — setelan `security.allow_raw_query`, izin
`system.raw_query`, dan validasi SELECT-only diperiksa saat menyimpan, bukan hanya saat
report dibuka.

**Versioning.** Tabel `report_versions` (migrasi `2026_08_25_100005`) mencerminkan
`form_versions`. Snapshot direkam **sebelum** perubahan pada sembilan titik: tambah,
ubah, dan hapus untuk join, kolom, dan filter, plus pengaturan report. Pemulihan sendiri
juga di-snapshot, jadi bisa dibatalkan.

## 11g. Log Aktivitas

Route `activity-logs`, dijaga permission `system.activity_log`.

`ActivityLogger` sudah mencatat sejak tahap 5; halaman ini yang membacanya.

- **Daftar** memakai DataTables server-side dengan filter pengguna, aksi, tabel, dan
  rentang tanggal. Pengurutan sengaja dibatasi ke `created_at` saja — kolom lain tidak
  berguna diurutkan dan hanya menambah jalur injeksi.
- **Rincian** menampilkan nilai lama berdampingan dengan nilai baru; kolom yang berubah
  disorot. `ActivityLog::changedKeys()` membandingkan keduanya setelah nilai non-skalar
  diserialisasi, sehingga larik dan JSON ikut terbandingkan.
- **Prune** membuang log yang lebih tua dari sekian hari (minimal 7). Tabel log tumbuh
  tanpa batas; tanpa pembersihan ia akan jadi tabel terbesar di database.

## 11h. Pengelola Sumber Data

Route `data-sources`, dijaga permission `system.data_source`.

Halaman ini mengelola isi tabel `data_sources` — whitelist yang jadi gerbang tunggal
seluruh engine (§5). Sebelum ada halaman ini, satu-satunya cara menambah tabel yang
boleh disentuh engine adalah lewat seeder atau SQL langsung.

**Menampilkan tabel yang belum terdaftar.** `TableInspector::physicalTables()` membaca
seluruh tabel fisik di database — **melewati whitelist dengan sengaja**, karena halaman
ini justru perlu melihat yang belum terdaftar. Karena itu pemanggilnya wajib dijaga
permission. Tabel bawaan Laravel dan tabel metadata engine disaring dari daftar.

**Deteksi kolom sensitif.** Kolom bernama `password`, `remember_token`, `api_token`,
`secret`, `token`, dan turunan two-factor ditandai dan bisa diblokir sekaligus dengan
satu tombol.

**Validasi struktural:**

| Aturan | Alasan |
|---|---|
| tabel harus benar-benar ada di database | mendaftarkan tabel fiktif hanya menunda kegagalan |
| primary key harus kolom nyata | engine perlu primary key untuk membaca dan menulis baris |
| kolom yang diblokir harus kolom nyata | salah ketik akan diam-diam tidak memblokir apa pun |
| primary key tidak boleh diblokir | sumbernya jadi tidak berguna |
| `is_writable` mensyaratkan `is_readable` | engine selalu membaca baris sebelum memperbaruinya |
| `table_name` dikunci setelah dibuat | metadata menunjuk sumber lewat nama tabelnya, bukan id |

**Peta pemakaian.** Sebelum sebuah sumber dicabut, seluruh tempat nama tabel bisa muncul
di metadata disapu: `forms.table_name`, `form_details.table_name`,
`form_fields.data_source`, `form_list_columns.relation_table`, `reports.base_table`,
`report_joins.table_name`, dan `report_filters.data_source`. Bila masih dipakai,
penghapusan ditolak dengan daftar pemakainya — mencabut sumber yang masih dipakai
membuat form dan report-nya gagal tanpa petunjuk apa pun.

**Menghapus sumber data tidak menyentuh tabelnya** — hanya mencabut izin engine.
Perubahan `blocked_columns` langsung berlaku karena cache kolom ikut dibuang.

## 11i. Editor Detail, Aksi, dan Tata Letak

Halaman builder form kini punya enam tab: **Pengaturan**, **Field**, **Tata Letak**,
**Kolom List**, **Detail**, dan **Aksi**.

**Baris detail** (`form_details`). Tabel detail wajib punya izin **tulis** — engine
menyisipkan dan menghapus barisnya setiap kali induk disimpan. Tabel detail tidak boleh
sama dengan tabel form induk, dan `min_rows` tidak boleh melebihi `max_rows`.
Menambahkan detail pertama otomatis mengubah `forms.type` menjadi `master_detail`;
menghapus detail terakhir mengembalikannya ke `single`.

Field milik detail dikelola di tab **Field** dengan pemilih lingkup. Field detail
divalidasi terhadap **tabel detailnya**, bukan tabel induk, dan keunikan
`field_name` dihitung per detail — field bernama `qty` boleh ada di induk dan di
setiap detail sekaligus.

**Tombol aksi** (`form_actions`). Dua aturan yang tidak bisa disimpulkan dari tipe data:

| Aturan | Alasan |
|---|---|
| aksi selain GET wajib punya pesan konfirmasi | aksi yang mengubah data mudah terpicu tak sengaja, terutama tombol per baris |
| aksi massal tidak boleh memakai GET | aksi massal bekerja pada banyak baris; lewat GET bisa terpicu dari tautan biasa |

Route yang belum terdaftar menghasilkan **peringatan, bukan penolakan** — route bisa
saja dibuat setelah aksinya didefinisikan.

**Kanvas tata letak** (tahap 9). Halaman `builder/forms/{form}/layout` menggambar field
sebagai blok dalam grid 12 kolom yang sama dengan form sungguhan. Blok diseret untuk
mengatur urutan dan dilebarkan/dipersempit dengan tombol; pratinjau di bawahnya
menghitung pembagian baris memakai aturan Bootstrap.

Kanvas ini **tidak menyimpan apa pun sendiri** — ia hanya cara lain menyunting
`order_no` dan `width` yang sudah ada. Nilai `width` dari klien tetap dibatasi 1–12 di
server, dan id yang bukan milik lingkup yang sedang disunting diabaikan.

## 11j. Bahasa

`APP_LOCALE=id`. Berkas `lang/id/` memuat 111 kunci `validation.php` (lengkap terhadap
bawaan Laravel), plus `auth.php`, `passwords.php`, dan `pagination.php`.

Nama field pada pesan validasi datang dari metadata (`form_fields.label`,
`report_columns.label`, dsb.) lewat `attributes()` di masing-masing FormRequest, sehingga
pesan yang muncul memakai istilah yang dikenal pengguna:
*"Kolom kode produk sudah digunakan."*

## 11k. Test Otomatis

`php artisan test` — **213 test, ~5 detik.** 192 berjalan di SQLite; 21 sisanya butuh MySQL
dan **dilewati otomatis** bila database ujinya belum disiapkan.

| Berkas | Cakupan |
|---|---|
| `tests/Unit/SqlExpressionGuardTest.php` | 26 kasus penyaring ekspresi, tanpa database |
| `tests/Feature/DataSourceResolverTest.php` | whitelist, kolom terblokir, injeksi nama tabel/kolom |
| `tests/Feature/DynamicCrudTest.php` | CRUD, validasi turunan, soft delete, scope per baris, optimistic locking |
| `tests/Feature/ReportEngineTest.php` | alias, filter tiap operator, soft delete join, validator raw |
| `tests/Feature/BuilderGuardTest.php` | penjagaan builder, kepemilikan, versioning |
| `tests/Feature/AuthAndMenuTest.php` | login, user nonaktif, penyaringan sidebar, cakupan role |
| `tests/Feature/QueuedExportTest.php` | ambang antrean, worker, kepemilikan berkas, prune |
| `tests/Feature/TableInspectorTest.php` | **butuh MySQL** — `information_schema`, enum, pemetaan generator |
| `tests/Feature/SeederIdempotencyTest.php` | seeder aman dijalankan berulang, password & setelan tidak tertimpa |
| `tests/Feature/ReportCountMysqlTest.php` | **butuh MySQL** — penghitung baris report beragregat ber-join |
| `tests/Feature/FormActionRendererTest.php` | URL aksi, penyaringan izin, kondisi tampil |
| `tests/Unit/CodeImageGeneratorTest.php` | barcode & QR: SVG sah, deterministik, URL kanonik |
| `tests/Feature/ReportChartTest.php` | label & deret grafik, nilai mentah, alasan bila tak bisa digambar |
| `tests/Feature/DashboardTest.php` | agregat widget, penyaringan izin, widget tak bisa melewati izin report |

**Suite berjalan di SQLite** (bawaan `phpunit.xml`), jadi tidak perlu menyiapkan
database apa pun. `tests/MetadataTestCase.php` membuat tabel bisnis kecil sendiri —
sengaja bukan tabel demo, supaya test tetap lulus walau migrasi demo dihapus.

**Test yang butuh MySQL** mewarisi `tests/MysqlTestCase.php`, yang mengalihkan koneksi
ke database uji terpisah dan **melewati test-nya** bila database itu belum ada — jadi
`php artisan test` tetap hijau di mesin mana pun. Untuk menyalakannya, sekali saja:

```bash
sudo mysql -e "CREATE DATABASE laravel_lowcode_test
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    GRANT ALL ON laravel_lowcode_test.* TO 'lowcode'@'127.0.0.1';"
```

Nama databasenya bisa diganti lewat env `MYSQL_TEST_DATABASE`.

## 11l. Nilai bawaan skema vs masukan kosong

Beberapa kolom metadata bersifat **NOT NULL dengan nilai bawaan**, tapi boleh
dikosongkan pengguna di builder: `form_actions.css_class`, `form_details.min_rows`,
`report_columns.decimal_places`, `report_filters.width`, dan
`reports.export_queue_threshold`.

Laravel mengubah masukan kosong menjadi `null` lewat `ConvertEmptyStringsToNull`,
sehingga `null` itu ikut masuk `INSERT` dan **ditolak database** — pengguna hanya
melihat 500 tanpa penjelasan.

Trait `App\Support\DropsNullDefaults` membuang kunci yang bernilai null sebelum
disimpan, sehingga database memakai nilai bawaannya — yang memang itulah maksud
"boleh dikosongkan". Dipakai di ketiga controller builder yang menulis kolom-kolom
tersebut.

## 11m. Report bertipe grafik

`reports.type = 'chart'` menggambar grafik **di atas tabelnya**, bukan menggantikan —
angka pastinya sering tetap dibutuhkan.

Tidak ada metadata terpisah untuk grafik selain bentuk (`chart_type`) dan batas baris
(`chart_limit`), keduanya ditambahkan migrasi `2026_08_25_100008`:

- **Label** diambil dari kolom pengelompokan; bila tidak ada, dari kolom non-agregat
  pertama yang tampil.
- **Deret nilai** dari kolom berformat angka (`number`, `decimal`, `currency`,
  `percentage`). Kolom teks tidak bisa digambar, jadi tidak ikut.
- **Nilai yang dipakai mentah**, bukan yang sudah diformat — `"Rp 12.500,00"` bukan
  angka.

Report yang sudah masuk akal sebagai ringkasan otomatis masuk akal pula sebagai grafik,
sehingga tidak perlu mendefinisikan ulang apa pun.

**Bila belum bisa digambar**, halaman menampilkan alasannya — kolom label belum ada,
tidak ada kolom angka, atau report bermode `raw` — bukan kanvas kosong yang menyesatkan.

Filter yang sedang berlaku ikut diterapkan pada grafik. Batas baris menjaga grafik tetap
terbaca; bila terpotong, halaman menyebutkannya dan sisanya tetap ada di tabel.
Chart.js hanya dimuat pada report bertipe grafik.

> **`crosstab` belum diimplementasikan.** Nilainya ada di enum `reports.type` sejak awal
> tapi tetap digambar sebagai tabel biasa.

## 11n. Dashboard Builder

Route `builder/dashboard`, dijaga permission `system.dashboard`. Dashboard sebelumnya
Blade statis dengan angka yang di-hardcode; kini metadata-driven seperti form dan report,
lewat tabel `dashboard_widgets` (migrasi `2026_08_25_100009`).

| Jenis widget | Sumber data |
|---|---|
| `stat` | agregat satu tabel: `COUNT`, `SUM`, `AVG`, `MIN`, `MAX`, dengan penyaring opsional |
| `chart` | menumpang report yang sudah ada |
| `table` | N baris teratas dari report yang sudah ada |
| `text` | catatan statis |

**Widget `chart` dan `table` sengaja menumpang report, bukan punya mesin query sendiri.**
Seluruh whitelist, scope per baris, dan permission report otomatis ikut berlaku — dan
yang terpenting, **widget tidak bisa jadi jalan pintas melewati izin report**: siapa pun
yang tidak berhak membuka report-nya juga tidak melihat angkanya di dashboard.

Hanya `stat` yang punya query sendiri, dan itu pun tetap lewat `DataSourceResolver`:
nama tabel dan kolomnya diperiksa, dan baris terhapus tidak ikut dihitung bila tabelnya
memang punya `deleted_at`.

**Satu widget bermasalah tidak mengosongkan dashboard.** Kegagalan dikembalikan sebagai
pesan dan digambar sebagai kartu peringatan pada posisinya sendiri.

Lebar memakai grid 12 kolom yang sama dengan tata letak form, dan urutannya diatur
drag & drop. Chart.js hanya dimuat bila ada widget grafik.

`MetadataSeeder` mengisi dua widget bawaan (jumlah pengguna dan role) — angka yang dulu
di-hardcode, kini bisa disunting atau dibuang.

## 12. Hal yang belum ditangani

Tidak dibahas PDF dan belum ada di kode:













## 13. Catatan keputusan

**Permission custom, bukan Spatie.** PDF bilang "Custom atau Spatie". Skema sudah memilih
custom (`roles`, `permissions`, `role_permissions`, `user_roles`). **Jangan pasang
`spatie/laravel-permission`** — akan membuat set tabel kedua yang tumpang tindih.

**Indeks unik `form_fields_unique_field` tidak menutup semua kasus.** MySQL memperlakukan
NULL sebagai nilai berbeda, sehingga baris dengan `form_detail_id` NULL (field form induk)
masih bisa duplikat `field_name`. Validasi tambahan wajib di FormRequest.

**Cache hanya boleh diisi array dan skalar — bukan objek apa pun.** Laravel 13
memasang `config/cache.php` → `serializable_classes = false`, sehingga setiap objek
yang keluar dari cache berubah menjadi `__PHP_Incomplete_Class`. Ini berlaku untuk
model Eloquent **maupun** `Illuminate\Support\Collection` biasa.

Jangan melonggarkan setelan itu — itu pengerasan yang sengaja dipasang framework.
Polanya: simpan `->all()`, bungkus `collect()` saat dibaca. Lihat
`MenuService::rawTree()` dan `FormRenderer::tableOptions()`.

## 14. Roadmap

| Tahap | Isi | Status |
|---|---|---|
| 1 | Laravel + AdminLTE + Login + Layout + Sidebar dinamis | **Selesai** |
| 2 | CRUD User + Role + Permission + Menu | **Selesai** |
| 3 | Metadata `forms` + `form_fields` | **Selesai** (skema) |
| 4 | Dynamic Form Renderer | **Selesai** |
| 5 | Dynamic CRUD | **Selesai** |
| 6 | Report Engine + Filter + DataTables | **Selesai** |
| 7 | Export Excel / PDF | **Selesai** |
| 8 | Auto-generate Form/CRUD dari tabel | **Selesai** |
| 9 | Drag & drop form builder | **Selesai** |

Di luar roadmap asli dan sudah selesai: **Form Builder** (§11e), **editor kolom list**,
**Report Builder** (§11f), **versioning report**, **halaman log aktivitas** (§11g),
**pengelola Sumber Data** (§11h), **editor detail & aksi** (§11i), dan
**pesan validasi bahasa Indonesia**.

Tahap 3 dikerjakan mendahului tahap 2 — skema paling murah diperbaiki di awal.
**Editor metadata form sudah ada** (lihat §11e) — prasyarat tahap 9 yang sebelumnya
kosong. Pekerjaan berikutnya adalah **tahap 9** (drag & drop form builder): membangun
tata letak visual di atas editor ini. Pengurutan field sudah memakai drag & drop;
yang belum adalah menyeret komponen ke kanvas.

Menu `Sumber Data` dan `Log Aktivitas` di sidebar masih mengarah ke `#`: keduanya
di luar tahap 2. `Form Builder` dan `Report Builder` menyusul di tahap 4–6.

## 15. Paket pihak ketiga

| Paket | Versi | Dipakai untuk |
|---|---|---|
| `maatwebsite/excel` | ^4.0 | ekspor `.xlsx` |
| `barryvdh/laravel-dompdf` | ^3.1 | ekspor PDF |

Keduanya auto-discover, tidak perlu registrasi manual. PhpSpreadsheet membutuhkan
ekstensi PHP `zip`, `gd`, dan `mbstring` — ketiganya sudah terpasang.
