# Panduan Penggunaan

Panduan ini membawa Anda dari tabel kosong sampai punya CRUD dan laporan yang berjalan,
tanpa menulis satu baris kode pun.

Alurnya selalu sama:

```
daftarkan tabel → generate form → rapikan → atur hak akses → buat laporan
```

---

## 0. Sebelum mulai

Masuk sebagai `admin@example.com` / `Admin#12345`, lalu **ganti password** lewat menu
Sistem → Pengguna.

Siapkan tabel bisnis Anda di database. Aplikasi ini **tidak membuat tabel** — ia bekerja
di atas tabel yang sudah ada. Tabel yang paling mudah dipakai punya:

- primary key `AUTO_INCREMENT`
- kolom `created_at` dan `updated_at`
- kolom `deleted_at` bila ingin soft delete
- kolom `created_by` / `updated_by` bila ingin jejak audit
- foreign key sungguhan (`FOREIGN KEY`) agar relasi terdeteksi otomatis

Tidak ada yang wajib, tapi makin lengkap, makin sedikit yang perlu Anda atur manual.

---

## 1. Daftarkan tabel sebagai sumber data

**Sistem → Sumber Data**

Tanpa langkah ini, engine tidak akan menyentuh tabel Anda sama sekali. Daftar ini adalah
gerbang keamanannya.

1. Buka bagian **Tabel Belum Terdaftar** di bawah — tabel yang ada di database tapi
   belum boleh diakses muncul di sana.
2. Klik nama tabel Anda.
3. Isi **Label** (nama yang enak dibaca), pastikan **Primary Key** benar.
4. Nyalakan **Boleh dibaca**. Nyalakan **Boleh ditulis** hanya jika form nanti akan
   menyimpan data ke tabel ini.
5. Centang kolom yang harus **diblokir**. Kolom bernama `password`, `token`, `secret`
   dan sejenisnya ditandai otomatis — klik **Blokir yang sensitif** untuk mencentang
   semuanya sekaligus.

> **Kolom yang diblokir tidak akan pernah terbaca engine.** Ia tidak muncul sebagai
> field, tidak bisa dipakai kolom daftar, filter, maupun ekspresi, dan tidak ikut
> terbawa saat baris dibaca. Ini pertahanan terakhir bila metadata salah dikonfigurasi.

Kalau tabel Anda punya relasi (`category_id` menunjuk `categories`), **daftarkan juga
tabel tujuannya** — minimal dengan izin baca. Tanpa itu, relasinya akan turun jadi
kotak angka biasa.

---

## 2. Generate CRUD

**Sistem → Form Builder → Generate dari Tabel**

1. Pilih tabel Anda, klik **Generate**.
2. Halaman pratinjau menampilkan kolom yang terdeteksi beserta tebakan jenis inputnya.
   Periksa sebentar — misalnya kolom `catatan` bertipe `VARCHAR(255)` mungkin lebih
   cocok jadi textarea.
3. Isi:
   - **Kode Form** — dipakai di URL (`/forms/produk`)
   - **Prefix Izin** — menghasilkan `produk.view`, `produk.create`, dan seterusnya
   - **Kolom Scope** — kosongkan dulu bila belum butuh pembatasan per baris
4. Hilangkan centang kolom yang tidak perlu jadi field.
5. Klik **Buat Form**.

Anda langsung diarahkan ke form yang sudah berjalan.

### Yang terjadi otomatis

| Terdeteksi | Menjadi |
|---|---|
| `ENUM(...)` | Pilihan dropdown, nilainya dibaca dari definisi kolom |
| Foreign key | Select2 yang menampilkan nama, bukan angka |
| `TINYINT(1)` | Sakelar ya/tidak |
| Nama memuat `email`, `website`, `foto`, `harga` | Input email, URL, unggah gambar, format mata uang |
| `NOT NULL` tanpa default | Wajib diisi |
| Indeks unik | Validasi nilai unik |
| Ada `deleted_at` | Soft delete menyala |
| Ada `created_by` | Kolom audit terisi otomatis |

Permission `<prefix>.view/create/edit/delete/export/print` ikut dibuat dan langsung
diberikan ke superadmin. Menu sidebar juga dibuatkan bila Anda mencentangnya.

> **Kalau form baru menampilkan 403,** hampir pasti permission-nya belum diberikan ke
> role Anda. Buka Sistem → Role & Izin dan centang izin yang baru dibuat.

---

## 3. Rapikan lewat Form Builder

**Sistem → Form Builder → pilih form Anda**

Ada enam tab. Hasil generate biasanya perlu sedikit dirapikan di tab **Field** dan
**Kolom List**.

### Tab Pengaturan

Judul halaman, jumlah baris per halaman, urutan default, dan aksi apa saja yang
diizinkan (tambah / ubah / hapus / ekspor / cetak).

**Kode form dan nama tabel tidak bisa diubah** — keduanya menentukan identitas form.
Perlu tabel lain? Buat form baru.

Panel **Riwayat Versi** di sebelah kanan menyimpan setiap perubahan. Salah ubah? Klik
tombol putar-balik pada versi yang diinginkan. Keadaan sekarang ikut disimpan lebih
dulu, jadi pemulihannya sendiri bisa dibatalkan.

Isi **Catatan perubahan** sebelum menyimpan — tiga bulan lagi Anda akan berterima kasih.

### Tab Field

Setiap field bisa diatur label, jenis input, lebar, wajib/tidak, dan sumber pilihannya.

**Sumber pilihan** ada empat:

| Jenis | Untuk apa |
|---|---|
| Tidak ada | Input biasa |
| Opsi statis | Daftar tetap yang Anda ketik sendiri |
| Tabel lain | Ambil dari tabel — isi tabel, kolom nilai, dan kolom label |
| Nilai ENUM kolom | Baca dari definisi `ENUM` di database |

**Pilihan bertingkat** (kabupaten menyaring kecamatan): isi **Bergantung pada Field**
dengan field induknya, dan **Kolom Penyaring** dengan kolom di tabel sumber yang
dicocokkan. Daftar anak akan dimuat ulang setiap induknya berubah.

### Tab Tata Letak

Kanvas visual dengan grid 12 kolom, sama persis dengan yang dipakai form sungguhan.
Seret blok untuk mengatur urutan, tekan **+** dan **−** untuk mengubah lebar. Pratinjau
di bawah menunjukkan pembagian barisnya. Jangan lupa **Simpan Tata Letak**.

### Tab Kolom List

Mengatur halaman daftar — bukan formnya.

Tiga jenis kolom:

- **Kolom tabel ini** — nilai apa adanya
- **Relasi** — tampilkan `kategori.nama`, bukan `kategori_id`. Isi kolom kuncinya
  (biasanya berakhiran `_id`), lalu tabel dan kolom labelnya.
- **Ekspresi SQL** — perhitungan seperti `harga * stok`. Butuh izin `system.raw_query`.

Berantakan? Tombol **Susun Ulang dari Field** mengembalikannya ke susunan otomatis.

### Tab Detail (master-detail)

Untuk satu induk dengan banyak baris anak — faktur dengan item, misalnya.

1. Tabel detailnya harus terdaftar di Sumber Data dengan **izin tulis**.
2. Isi **Kolom Penghubung** — kolom di tabel detail yang menunjuk induknya
   (mis. `faktur_id`).
3. Tambahkan detail, lalu buka tab **Field** dan **ganti lingkup** ke detail tersebut
   untuk menambahkan field-fieldnya.

Tanpa field, baris detail tidak akan menggambar apa pun.

### Tab Aksi

Tombol tambahan di halaman daftar. Tiga posisi:

- **Per baris** — muncul di kolom aksi tiap baris
- **Toolbar** — di kepala kartu
- **Massal** — muncul setelah baris dicentang

**Kondisi tampil** membuat tombol muncul selektif. Misalnya tombol *Setujui* yang hanya
tampil pada baris berstatus draft. (Kondisi diisi lewat kolom `show_condition` berformat
JSON, mis. `{"status": "draft"}`.)

Aturan yang ditegakkan: aksi selain GET **wajib** punya pesan konfirmasi, dan aksi
massal tidak boleh memakai GET.

Form demo `product` sudah punya tiga contoh yang bisa langsung dicoba — Setujui
(per baris, hanya muncul pada status draft), Arsipkan (massal), dan Cetak Label
(toolbar). Endpoint-nya ada di `App\Http\Controllers\Demo\ProductActionController`,
contoh bagaimana aksi semacam itu dipasang di aplikasi Anda sendiri.

**Halaman Cetak Label** memperlihatkan barcode dan QR. Pilihannya lewat query string
`?kode=barcode|qr|keduanya|tanpa`:

| | Isi | Untuk |
|---|---|---|
| Barcode | kode produk (Code 128) | dipindai kasir atau alat stok opname |
| QR | tautan ke halaman ubah produk | dibuka dari ponsel |

QR memakai `APP_URL`, bukan alamat yang sedang Anda buka — supaya label yang dicetak
dari `localhost` tetap bisa dipindai dari perangkat lain. Pastikan `APP_URL` di `.env`
berisi alamat yang benar-benar dapat dijangkau.

---

## 4. Atur hak akses

**Sistem → Role & Izin**

Buat role, centang izinnya, lalu tugaskan ke pengguna di **Sistem → Pengguna**.

### Pembatasan data per baris

Supaya tiap cabang hanya melihat datanya sendiri:

1. Tabel Anda perlu kolom penanda unit — misalnya `kode_cabang`.
2. Di **Form Builder → Pengaturan**, isi **Kolom Scope** dengan kolom itu.
3. Di **Role**, setel **Cakupan Data** ke `Data unit/cabang`.
4. Di **Pengguna**, isi **Nilai Scope** dengan kode cabangnya (mis. `CAB-01`).

Setelahnya:

- Daftar hanya menampilkan baris yang cocok
- Membuka baris unit lain lewat URL langsung menghasilkan **404** — bukan 403, karena
  403 sudah mengakui barisnya ada
- Baris baru **selalu** memakai cakupan pembuatnya; nilai dari request diabaikan

> **Nilai Scope yang kosong menutup semua baris, bukan membuka semua.** Salah
> konfigurasi harus gagal ke arah aman.

Cakupan `Semua data` melewati pembatasan ini sepenuhnya.

---

## 5. Buat laporan

**Sistem → Report Builder → Report Baru**

1. Pilih **tabel dasar** dan beri **alias** pendek (mis. `p`). Alias dipakai menulis
   referensi kolom sebagai `p.harga`.
2. Simpan — Anda langsung diarahkan ke tab Kolom.

### Tab Join

Untuk mengambil data dari tabel lain. Beri alias unik (mis. `k`), lalu tulis kondisinya
`k.id = p.kategori_id`.

Tabel yang di-join wajib terdaftar di Sumber Data.

### Tab Kolom

Tambahkan kolom yang ingin ditampilkan. Pilih dari daftar — hanya kolom pada alias yang
terdaftar yang ditawarkan.

**Untuk laporan ringkasan:**

- Kolom pengelompokan: centang **Kolom pengelompokan (GROUP BY)**
- Kolom hitungan: pilih **Agregat** (`SUM`, `COUNT`, `AVG`, `MIN`, `MAX`)
- Centang **Tampilkan total** pada kolom yang perlu baris total

Kolom beragregat tidak bisa sekaligus jadi kolom pengelompokan, dan tidak bisa ikut
pencarian — keduanya ditolak dengan penjelasan.

**Lupa menandai kolom pengelompokan?** Bila report mencampur kolom agregat dan kolom
biasa tanpa satu pun yang ditandai, kolom biasanya dikelompokkan otomatis — `COUNT(id)`
berdampingan dengan `nama` memang hanya berarti "hitung per nama". Menandainya sendiri
tetap lebih baik: Anda yang menentukan, bukan sistem yang menebak.

### Tab Filter

Setiap filter punya kolom, operator, dan jenis masukan.

| Operator | Nilai yang dibutuhkan |
|---|---|
| `=`, `!=`, `>`, `>=`, `<`, `<=`, `LIKE` | satu |
| `BETWEEN` | dua (satu per baris) |
| `IN`, `NOT IN` | berapa pun |
| `IS NULL`, `IS NOT NULL` | tidak ada |

**Nilai default** diketik satu per baris. Petunjuk di bawah kotaknya menyesuaikan
operator yang dipilih.

**Opsi statis** ditulis `nilai|label` per baris:

```
draft|Konsep
published|Terbit
```

`BETWEEN` yang hanya diisi satu ujung diperlakukan sebagai "minimal sekian" — bukan
diabaikan.

---

## 6. Ekspor dan cetak

Tombol **Excel · CSV · PDF · Cetak** ada di halaman daftar maupun laporan.

**Ekspor mengikuti filter yang sedang aktif** — bukan seluruh tabel. Baris total
dihitung dari seluruh hasil, bukan hanya halaman yang tampil.

Data yang banyak otomatis dikerjakan di latar belakang. Anda diarahkan ke
**Berkas Ekspor** (ikon unduh di pojok kanan atas), dan berkasnya muncul di sana setelah
selesai.

> **Ekspor terantre butuh queue worker berjalan.** Jalankan `php artisan queue:work` —
> tanpa itu, ekspor besar akan mengantre selamanya.

Berkas hanya bisa diunduh oleh yang memesannya, karena isinya sudah tersaring memakai
izin dan cakupan data orang tersebut.

Berkas dibuang otomatis setelah 7 hari. Ingin merapikan lebih cepat? Pakai kotak
**Buang lebih tua dari … hari** di halaman Berkas Ekspor — hanya menyentuh milik Anda.

---

## 7. Menu sidebar

**Sistem → Menu**

Sidebar dibaca dari database. Tiap menu punya **Jenis Tautan**:

| Jenis | Tujuan diisi dengan |
|---|---|
| Route Laravel | nama route |
| URL langsung | alamat lengkap |
| Form dinamis | kode form |
| Report dinamis | kode report |
| Header | (kosong — hanya pembungkus) |

Isi **Izin** agar menu hanya tampil bagi yang berhak. Header yang seluruh anaknya
tersaring akan ikut hilang, jadi tidak ada judul menggantung.

---

## 8. Melacak perubahan

**Sistem → Log Aktivitas**

Setiap tambah, ubah, dan hapus tercatat. Saring berdasarkan pengguna, aksi, tabel, atau
rentang tanggal. Klik ikon mata untuk melihat rincian — nilai lama dan baru
berdampingan, kolom yang berubah disorot.

Tabel log tumbuh tanpa batas. Pakai **Buang log lebih tua dari … hari** secara berkala.

---

## Masalah yang sering muncul

**Form baru menampilkan 403.**
Permission-nya belum diberikan ke role Anda. Sistem → Role & Izin.

**Tabel tidak muncul di generator.**
Belum terdaftar di Sumber Data, atau izin bacanya mati.

**Select relasi kosong.**
Tabel tujuannya belum terdaftar di Sumber Data, atau kolom labelnya salah.

**Perubahan metadata tidak terlihat.**
Seharusnya langsung berlaku. Bila tidak: `php artisan cache:clear`.

**Menyimpan ditolak dengan pesan "sudah diubah orang lain".**
Memang begitu maksudnya — orang lain menyimpan lebih dulu. Muat ulang halaman dan
ulangi perubahan Anda supaya pekerjaan mereka tidak tertimpa.

**Ekspor menggantung di status "Antre".**
Queue worker tidak berjalan. `php artisan queue:work`.

**Berkas ekspor hilang sebelum sempat diunduh.**
Masa simpannya 7 hari. Minta ekspor ulang.

**Kolom ekspresi ditolak.**
Hanya referensi kolom, aritmetika, dan fungsi dasar yang diizinkan. Subquery, kata kunci
SQL, dan komentar ditolak — dan Anda butuh izin `system.raw_query`.

---

## Batas yang perlu diketahui

- **Laporan mode raw** (tulis SQL sendiri) dimatikan secara bawaan. Menyalakannya butuh
  izin `system.raw_query` **dan** setelan `security.allow_raw_query`. Hanya nyalakan
  bila Anda paham konsekuensinya.
- **Kolom relasi di halaman daftar** tidak bisa dicari atau diurutkan langsung —
  keduanya diambil lewat `LEFT JOIN`.
- **Baris detail ditulis ulang seluruhnya** setiap kali induknya disimpan.
- **Ekspor dibatasi 50.000 baris**, berapa pun ambang yang disetel.

Rincian teknis dan alasan di balik keputusan-keputusan ini ada di
[`RANCANGAN.md`](RANCANGAN.md).
