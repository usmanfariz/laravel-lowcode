<?php

namespace Database\Seeders;

use App\Services\HelpBot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Basis pengetahuan bawaan chatbot bantuan.
 *
 * Isinya disarikan dari docs/TUTORIAL.md. Yang di sini sengaja jauh lebih
 * pendek: chatbot dipakai orang yang sedang tersendat di tengah pekerjaan,
 * bukan yang sedang duduk membaca panduan. Jawaban panjang di balon chat
 * tidak terbaca — untuk itulah ada tautan ke halamannya.
 *
 * Idempoten seperti MetadataSeeder: dijalankan ulang memperbarui artikel
 * bawaan tanpa menyentuh artikel yang ditambahkan sendiri oleh admin.
 */
class HelpArticleSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $urutan = [];

        foreach ($this->artikel() as $row) {
            $kategori = $row['kategori'];
            $urutan[$kategori] = ($urutan[$kategori] ?? 0) + 1;

            DB::table('help_articles')->updateOrInsert(
                ['code' => $row['kode']],
                [
                    'category' => $kategori,
                    'question' => $row['tanya'],
                    'answer' => $row['jawab'],
                    'keywords' => $row['kunci'] ?? null,
                    'link_route' => $row['route'] ?? null,
                    'link_label' => $row['label'] ?? null,
                    'permission_code' => $row['izin'] ?? null,
                    'is_featured' => $row['unggulan'] ?? false,
                    'order_no' => $urutan[$kategori],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        app(HelpBot::class)->flush();
    }

    /**
     * Format jawaban: teks biasa. Baris berawalan "- " menjadi butir, `kode`
     * menjadi teks monospace, **tebal** menjadi tebal. Tidak ada HTML — isinya
     * datang dari layar admin dan diperlakukan sebagai teks tak dipercaya.
     */
    private function artikel(): array
    {
        return [
            // ---------------- Memulai ----------------
            [
                'kode' => 'mulai.alur', 'kategori' => 'Memulai', 'unggulan' => true,
                'tanya' => 'Bagaimana cara memulai membuat CRUD baru?',
                'kunci' => 'mulai, awal, langkah, alur, crud baru, bikin aplikasi, tahapan',
                'jawab' => "Alurnya selalu sama, lima langkah:\n\n"
                    ."1. Daftarkan tabel di **Sistem → Sumber Data**\n"
                    ."2. Buat form lewat **Form Builder → Generate dari Tabel**\n"
                    ."3. Rapikan field dan kolom daftar di Form Builder\n"
                    ."4. Berikan izinnya di **Sistem → Role & Izin**\n"
                    ."5. Buat laporannya di **Report Builder**\n\n"
                    .'Tanpa langkah 1, tabel Anda tidak akan terlihat di generator.',
                'route' => 'generator.index', 'label' => 'Buka Generator', 'izin' => 'system.builder.form',
            ],
            [
                'kode' => 'mulai.buat.tabel', 'kategori' => 'Memulai',
                'tanya' => 'Apakah aplikasi ini bisa membuat tabel database baru?',
                'kunci' => 'buat tabel, create table, migrasi, ddl, struktur database',
                'jawab' => "Tidak. Aplikasi ini bekerja **di atas tabel yang sudah ada** dan hanya membaca strukturnya lewat `information_schema`.\n\n"
                    ."Buat tabelnya lebih dulu di database, baru daftarkan di Sumber Data.\n\n"
                    .'Ini disengaja: koneksi produksi sebaiknya tidak punya hak DDL sama sekali.',
            ],
            [
                'kode' => 'mulai.syarat.tabel', 'kategori' => 'Memulai',
                'tanya' => 'Tabel seperti apa yang paling mudah dipakai?',
                'kunci' => 'syarat tabel, struktur ideal, primary key, timestamps, foreign key',
                'jawab' => "Tidak ada yang wajib, tapi makin lengkap makin sedikit yang perlu diatur manual:\n\n"
                    ."- primary key `AUTO_INCREMENT`\n"
                    ."- kolom `created_at` dan `updated_at`\n"
                    ."- kolom `deleted_at` bila ingin soft delete\n"
                    ."- kolom `created_by` / `updated_by` bila ingin jejak audit\n"
                    .'- `FOREIGN KEY` sungguhan agar relasi terdeteksi otomatis',
            ],
            [
                'kode' => 'mulai.ganti.sandi', 'kategori' => 'Memulai',
                'tanya' => 'Bagaimana cara mengganti password saya?',
                'kunci' => 'password, sandi, ganti kata sandi, ubah password, akun admin, password default, sandi bawaan, lupa password, login pertama',
                'jawab' => "Lewat **Sistem → Pengguna**, buka akun Anda, lalu isi kolom kata sandi. Dikosongkan berarti sandi lama dipertahankan.\n\n"
                    .'Sandi bawaan `Admin#12345` wajib diganti segera setelah pemasangan.',
                'route' => 'users.index', 'label' => 'Buka Pengguna', 'izin' => 'user.view',
            ],

            // ---------------- Sumber Data ----------------
            [
                'kode' => 'sumber.daftar', 'kategori' => 'Sumber Data', 'unggulan' => true,
                'tanya' => 'Bagaimana cara mendaftarkan tabel sebagai sumber data?',
                'kunci' => 'sumber data, whitelist, daftarkan tabel, registrasi tabel, data source',
                'jawab' => "Buka **Sistem → Sumber Data**, lihat bagian *Tabel Belum Terdaftar* di bawah, lalu klik nama tabel Anda.\n\n"
                    ."Isi **Label**, pastikan **Primary Key** benar, nyalakan **Boleh dibaca**, dan nyalakan **Boleh ditulis** hanya bila form akan menyimpan ke tabel itu.\n\n"
                    .'Daftar ini adalah gerbang keamanan engine: tabel yang tidak terdaftar tidak akan disentuh sama sekali.',
                'route' => 'data-sources.index', 'label' => 'Buka Sumber Data', 'izin' => 'system.data_source',
            ],
            [
                'kode' => 'sumber.blokir', 'kategori' => 'Sumber Data',
                'tanya' => 'Apa arti kolom yang diblokir di Sumber Data?',
                'kunci' => 'blokir kolom, blocked columns, kolom sensitif, sembunyikan kolom, password token',
                'jawab' => "Kolom yang diblokir **tidak akan pernah terbaca engine**. Ia tidak muncul sebagai field, tidak bisa dipakai kolom daftar, filter, maupun ekspresi, dan tidak ikut terbawa saat baris dibaca.\n\n"
                    .'Kolom bernama `password`, `token`, `secret` ditandai otomatis — tombol **Blokir yang sensitif** mencentang semuanya sekaligus.',
            ],
            [
                'kode' => 'sumber.relasi', 'kategori' => 'Sumber Data',
                'tanya' => 'Kenapa select relasi saya kosong atau hanya menampilkan angka?',
                'kunci' => 'select kosong, relasi tidak muncul, foreign key angka, dropdown kosong, select2 kosong',
                'jawab' => "Tabel tujuan relasinya belum terdaftar di Sumber Data, atau kolom labelnya salah.\n\n"
                    .'Kalau `kategori_id` menunjuk tabel `kategori`, tabel `kategori` juga harus terdaftar — minimal dengan izin baca. Tanpa itu, relasinya turun jadi kotak angka biasa.',
                'route' => 'data-sources.index', 'label' => 'Buka Sumber Data', 'izin' => 'system.data_source',
            ],

            // ---------------- Form Builder ----------------
            [
                'kode' => 'form.generate', 'kategori' => 'Form Builder', 'unggulan' => true,
                'tanya' => 'Bagaimana cara generate form dari tabel?',
                'kunci' => 'generate, generator, buat form, form otomatis, crud otomatis',
                'jawab' => "**Sistem → Form Builder → Generate dari Tabel**. Pilih tabel, klik **Generate**, lalu isi:\n\n"
                    ."- **Kode Form** — dipakai di URL, misalnya `/forms/produk`\n"
                    ."- **Prefix Izin** — menghasilkan `produk.view`, `produk.create`, dan seterusnya\n"
                    ."- **Kolom Scope** — kosongkan dulu bila belum butuh pembatasan per baris\n\n"
                    .'Hilangkan centang kolom yang tidak perlu jadi field, lalu klik **Buat Form**.',
                'route' => 'generator.index', 'label' => 'Buka Generator', 'izin' => 'system.builder.form',
            ],
            [
                'kode' => 'form.deteksi', 'kategori' => 'Form Builder',
                'tanya' => 'Apa saja yang dideteksi otomatis saat generate?',
                'kunci' => 'deteksi otomatis, enum, tinyint, foreign key, tebakan input, otomatis',
                'jawab' => "Dari struktur kolomnya:\n\n"
                    ."- `ENUM(...)` → dropdown, nilainya dibaca dari definisi kolom\n"
                    ."- foreign key → Select2 yang menampilkan nama, bukan angka\n"
                    ."- `TINYINT(1)` → sakelar ya/tidak\n"
                    ."- nama memuat `email`, `website`, `foto`, `harga` → input yang sesuai\n"
                    ."- `NOT NULL` tanpa default → wajib diisi\n"
                    ."- indeks unik → validasi nilai unik\n"
                    ."- ada `deleted_at` → soft delete menyala\n"
                    .'- ada `created_by` → kolom audit terisi otomatis',
            ],
            [
                'kode' => 'form.ubah.kode', 'kategori' => 'Form Builder',
                'tanya' => 'Bisakah kode form atau nama tabelnya diubah?',
                'kunci' => 'ubah kode form, ganti tabel, rename form, pindah tabel',
                'jawab' => "Tidak bisa. Keduanya menentukan identitas form.\n\n"
                    .'Perlu tabel lain? Buat form baru.',
            ],
            [
                'kode' => 'form.versi', 'kategori' => 'Form Builder',
                'tanya' => 'Saya salah mengubah form, bagaimana mengembalikannya?',
                'kunci' => 'undo, batalkan perubahan, riwayat versi, kembalikan, restore, rollback',
                'jawab' => "Panel **Riwayat Versi** di tab Pengaturan menyimpan setiap perubahan. Klik tombol putar-balik pada versi yang diinginkan.\n\n"
                    .'Keadaan sekarang ikut disimpan lebih dulu, jadi pemulihannya sendiri bisa dibatalkan.',
                'route' => 'builder.forms.index', 'label' => 'Buka Form Builder', 'izin' => 'system.builder.form',
            ],
            [
                'kode' => 'form.field.tambah', 'kategori' => 'Form Builder',
                'tanya' => 'Bagaimana menambah field baru pada form?',
                'kunci' => 'tambah field, field baru, kolom baru di form, input baru, nambah isian',
                'jawab' => "Buka **Form Builder → pilih form → tab Field → Tambah Field**.\n\n"
                    ."Kolomnya harus benar-benar ada di tabel dan tidak sedang diblokir di Sumber Data — daftar pilihan hanya menawarkan yang lolos keduanya.\n\n"
                    .'Untuk form master-detail, ganti dulu **lingkup** ke baris detail yang dituju sebelum menambah field.',
                'route' => 'builder.forms.index', 'label' => 'Buka Form Builder', 'izin' => 'system.builder.form',
            ],
            [
                'kode' => 'form.pilihan', 'kategori' => 'Form Builder',
                'tanya' => 'Bagaimana mengisi pilihan dropdown pada sebuah field?',
                'kunci' => 'dropdown, pilihan, opsi, select, sumber pilihan, enum, combobox',
                'jawab' => "Di tab **Field**, kotak **Sumber pilihan** punya empat jenis:\n\n"
                    ."- **Tidak ada** — input biasa\n"
                    ."- **Opsi statis** — daftar tetap yang Anda ketik sendiri\n"
                    ."- **Tabel lain** — isi tabel, kolom nilai, dan kolom label\n"
                    .'- **Nilai ENUM kolom** — dibaca dari definisi `ENUM` di database',
            ],
            [
                'kode' => 'form.bertingkat', 'kategori' => 'Form Builder',
                'tanya' => 'Bagaimana membuat pilihan bertingkat seperti provinsi dan kota?',
                'kunci' => 'bertingkat, dependent dropdown, cascading, provinsi kota, saring pilihan, induk anak',
                'jawab' => "Di field anaknya, isi **Bergantung pada Field** dengan field induknya, dan **Kolom Penyaring** dengan kolom di tabel sumber yang dicocokkan.\n\n"
                    .'Daftar anak akan dimuat ulang setiap kali induknya berubah.',
            ],
            [
                'kode' => 'form.tata.letak', 'kategori' => 'Form Builder',
                'tanya' => 'Bagaimana mengatur tata letak field pada form?',
                'kunci' => 'tata letak, layout, urutan field, lebar field, grid, susunan',
                'jawab' => "Tab **Tata Letak** adalah kanvas visual dengan grid 12 kolom, sama persis dengan yang dipakai form sungguhan.\n\n"
                    ."Seret blok untuk mengatur urutan, tekan **+** dan **−** untuk mengubah lebar.\n\n"
                    .'Jangan lupa **Simpan Tata Letak** — pratinjau saja tidak menyimpan.',
            ],
            [
                'kode' => 'form.kolom.list', 'kategori' => 'Form Builder',
                'tanya' => 'Bagaimana mengatur kolom yang tampil di halaman daftar?',
                'kunci' => 'kolom list, halaman daftar, tabel daftar, kolom tampil, datatable',
                'jawab' => "Tab **Kolom List** mengatur halaman daftar — bukan formnya. Tiga jenis kolom:\n\n"
                    ."- **Kolom tabel ini** — nilai apa adanya\n"
                    ."- **Relasi** — menampilkan `kategori.nama`, bukan `kategori_id`\n"
                    ."- **Ekspresi SQL** — perhitungan seperti `harga * stok`, butuh izin `system.raw_query`\n\n"
                    .'Berantakan? Tombol **Susun Ulang dari Field** mengembalikannya ke susunan otomatis.',
            ],
            [
                'kode' => 'form.aksi', 'kategori' => 'Form Builder',
                'tanya' => 'Bagaimana menambah tombol aksi di halaman daftar?',
                'kunci' => 'tombol aksi, action, tombol tambahan, toolbar, aksi massal, bulk action',
                'jawab' => "Tab **Aksi**. Tiga posisi: **Per baris**, **Toolbar**, dan **Massal** (muncul setelah baris dicentang).\n\n"
                    ."Jenisnya lima: Route Laravel, URL langsung, Permintaan AJAX, Buka modal, dan **Handler** (menjalankan kode Anda sendiri).\n\n"
                    .'Aturannya: aksi selain GET wajib punya pesan konfirmasi, aksi massal tidak boleh GET, dan aksi handler selalu POST.',
            ],
            [
                'kode' => 'form.aksi.kondisi', 'kategori' => 'Form Builder',
                'tanya' => 'Bagaimana membuat tombol hanya muncul pada baris tertentu?',
                'kunci' => 'tombol kondisional, syarat tombol, tampilkan bila, status draft, kondisi baris',
                'jawab' => "Isi **Tampilkan tombol hanya bila** pada aksinya: pilih kolom, isi nilainya.\n\n"
                    ."Misalnya tombol *Posting* yang hanya tampil pada baris berstatus `draft`. Beberapa nilai dipisah koma berarti \"salah satu\": `draft, revisi`.\n\n"
                    .'Kondisinya dinilai per baris di halaman daftar.',
            ],
            [
                'kode' => 'form.kunci', 'kategori' => 'Form Builder',
                'tanya' => 'Bagaimana mengunci baris agar tidak bisa diubah lagi?',
                'kunci' => 'kunci baris, lock, posted, posting, diposting, terkunci, tidak bisa diubah, kunci data, final, nota terkunci',
                'jawab' => "Kartu **Penguncian** di tab Pengaturan. Pilih kolomnya, isi nilainya, dan baris yang cocok akan menolak setiap perubahan maupun penghapusan.\n\n"
                    ."Beberapa nilai dipisah koma berarti \"salah satu\": `posted, void`. Kosongkan kolomnya untuk mematikan penguncian.\n\n"
                    .'Penguncian ditegakkan di lapisan penyimpanan — membuka form edit lewat URL langsung tetap ditolak.',
            ],

            // ---------------- Master-Detail & Rumus ----------------
            [
                'kode' => 'detail.buat', 'kategori' => 'Master-Detail & Rumus', 'unggulan' => true,
                'tanya' => 'Bagaimana membuat form master-detail seperti faktur dan itemnya?',
                'kunci' => 'master detail, faktur item, form induk anak, baris detail, nota, header detail',
                'jawab' => "Tiga langkah di tab **Detail**:\n\n"
                    ."1. Pastikan tabel detailnya terdaftar di Sumber Data dengan **izin tulis**\n"
                    ."2. Isi **Kolom Penghubung** — kolom di tabel detail yang menunjuk induknya, misalnya `faktur_id`\n"
                    ."3. Tambahkan detailnya, lalu buka tab **Field** dan **ganti lingkup** ke detail tersebut untuk menambahkan field-fieldnya\n\n"
                    .'Tanpa field, baris detail tidak akan menggambar apa pun.',
            ],
            [
                'kode' => 'detail.total', 'kategori' => 'Master-Detail & Rumus',
                'tanya' => 'Bagaimana menampilkan baris total di tabel detail?',
                'kunci' => 'baris total, jumlah, subtotal, footer tabel, grand total, penjumlahan',
                'jawab' => "Dua bagian:\n\n"
                    ."1. Centang **Jumlahkan di baris total** pada field angka di baris detail\n"
                    ."2. Nyalakan **Tampilkan baris total** di tab Detail\n\n"
                    .'Kolom yang ditandai akan dijumlahkan di kaki tabel dan ikut berubah saat baris ditambah, diubah, atau dihapus.',
            ],
            [
                'kode' => 'rumus.cara', 'kategori' => 'Master-Detail & Rumus',
                'tanya' => 'Bagaimana membuat field yang nilainya dihitung otomatis?',
                'kunci' => 'rumus, formula, field terhitung, hitung otomatis, subtotal, kalkulasi',
                'jawab' => "Isi kotak **Rumus** pada field angka di tab Field. Field itu otomatis menjadi hanya-baca. Contoh:\n\n"
                    ."- `qty * harga` di field detail → subtotal per baris\n"
                    ."- `sum(items.subtotal)` di field induk → total seluruh baris detail\n"
                    ."- `sum(items.subtotal) * 0.11` → pajak dari total\n\n"
                    .'Nilai yang tersimpan selalu dihitung ulang di server, jadi angka di layar tidak bisa dipalsukan.',
            ],
            [
                'kode' => 'rumus.batas', 'kategori' => 'Master-Detail & Rumus',
                'tanya' => 'Apa saja yang boleh dipakai di dalam rumus?',
                'kunci' => 'batas rumus, sintaks rumus, if rumus, kondisi rumus, fungsi rumus, aturan formula',
                'jawab' => "Hanya aritmetika: angka, nama field, `+ - * / ( )`, dan `sum(kode_detail.nama_field)`. Tidak ada perbandingan, kondisi, atau fungsi lain.\n\n"
                    ."Aturan yang ditegakkan:\n\n"
                    ."- hanya untuk field angka (`number`, `decimal`, `currency`, `percentage`)\n"
                    ."- `sum()` hanya boleh dipakai field **induk**\n"
                    ."- rumus tidak boleh merujuk dirinya sendiri, dan hanya boleh merujuk field terhitung yang urutannya **lebih awal**\n\n"
                    .'Yang lebih rumit dari itu wilayah hook simpan.',
            ],
            [
                'kode' => 'rumus.bagi.nol', 'kategori' => 'Master-Detail & Rumus',
                'tanya' => 'Apa yang terjadi kalau rumus membagi dengan nol?',
                'kunci' => 'bagi nol, division by zero, pembagian nol, error rumus',
                'jawab' => 'Hasilnya nol, bukan galat. Penyebut yang sesaat kosong saat mengetik itu wajar dan tidak seharusnya menghentikan pengisian form.',
            ],

            // ---------------- Hak Akses ----------------
            [
                'kode' => 'izin.403', 'kategori' => 'Hak Akses', 'unggulan' => true,
                'tanya' => 'Kenapa form baru saya menampilkan 403?',
                'kunci' => '403, forbidden, tidak punya izin, akses ditolak, tidak bisa buka form',
                'jawab' => "Hampir pasti permission-nya belum diberikan ke role Anda.\n\n"
                    .'Buka **Sistem → Role & Izin**, pilih role Anda, dan centang izin yang baru dibuat generator (`<prefix>.view`, `.create`, `.edit`, dan seterusnya).',
                'route' => 'roles.index', 'label' => 'Buka Role & Izin', 'izin' => 'role.view',
            ],
            [
                'kode' => 'izin.atur', 'kategori' => 'Hak Akses',
                'tanya' => 'Bagaimana cara mengatur hak akses pengguna?',
                'kunci' => 'hak akses, role, izin, permission, atur akses, user role',
                'jawab' => "Buat role di **Sistem → Role & Izin**, centang izinnya, lalu tugaskan role itu ke pengguna di **Sistem → Pengguna**.\n\n"
                    .'Satu pengguna bisa memegang lebih dari satu role; izinnya digabung.',
                'route' => 'roles.index', 'label' => 'Buka Role & Izin', 'izin' => 'role.view',
            ],
            [
                'kode' => 'izin.scope', 'kategori' => 'Hak Akses',
                'tanya' => 'Bagaimana membatasi pengguna agar hanya melihat data cabangnya?',
                'kunci' => 'scope, batasi data, per cabang, row level, data unit, filter otomatis, multi cabang',
                'jawab' => "Empat langkah:\n\n"
                    ."1. Tabel Anda perlu kolom penanda unit, misalnya `kode_cabang`\n"
                    ."2. Di **Form Builder → Pengaturan**, isi **Kolom Scope** dengan kolom itu\n"
                    ."3. Di **Role**, setel **Cakupan Data** ke *Data unit/cabang*\n"
                    ."4. Di **Pengguna**, isi **Nilai Scope** dengan kode cabangnya, misalnya `CAB-01`\n\n"
                    .'Baris baru selalu memakai cakupan pembuatnya; nilai dari request diabaikan.',
            ],
            [
                'kode' => 'izin.scope.kosong', 'kategori' => 'Hak Akses',
                'tanya' => 'Apa yang terjadi kalau Nilai Scope pengguna dikosongkan?',
                'kunci' => 'scope kosong, nilai scope, tidak ada data, list kosong, semua data hilang',
                'jawab' => "Nilai Scope yang kosong **menutup semua baris**, bukan membuka semua. Salah konfigurasi harus gagal ke arah yang aman.\n\n"
                    .'Kalau pengguna itu memang harus melihat semuanya, setel Cakupan Data role-nya ke *Semua data* — bukan mengosongkan Nilai Scope.',
            ],
            [
                'kode' => 'izin.404', 'kategori' => 'Hak Akses',
                'tanya' => 'Kenapa membuka baris milik cabang lain menghasilkan 404, bukan 403?',
                'kunci' => '404, not found, baris cabang lain, kenapa 404',
                'jawab' => 'Karena 403 sudah mengakui barisnya ada. Untuk data yang dibatasi per cabang, keberadaan barisnya sendiri termasuk yang tidak boleh diketahui.',
            ],

            // ---------------- Report ----------------
            [
                'kode' => 'report.buat', 'kategori' => 'Report', 'unggulan' => true,
                'tanya' => 'Bagaimana cara membuat laporan baru?',
                'kunci' => 'buat laporan, report baru, laporan, bikin report',
                'jawab' => "**Sistem → Report Builder → Report Baru**.\n\n"
                    ."1. Pilih **tabel dasar** dan beri **alias** pendek, misalnya `p`\n"
                    ."2. Simpan — Anda langsung diarahkan ke tab Kolom\n"
                    ."3. Tambahkan kolom yang ingin ditampilkan\n\n"
                    .'Alias dipakai menulis referensi kolom sebagai `p.harga`.',
                'route' => 'builder.reports.index', 'label' => 'Buka Report Builder', 'izin' => 'system.builder.report',
            ],
            [
                'kode' => 'report.join', 'kategori' => 'Report',
                'tanya' => 'Bagaimana mengambil data dari tabel lain di laporan?',
                'kunci' => 'join, gabung tabel, relasi laporan, tabel lain, left join',
                'jawab' => "Tab **Join**. Beri alias unik, misalnya `k`, lalu tulis kondisinya `k.id = p.kategori_id`.\n\n"
                    .'Tabel yang di-join wajib terdaftar di Sumber Data.',
            ],
            [
                'kode' => 'report.ringkas', 'kategori' => 'Report',
                'tanya' => 'Bagaimana membuat laporan ringkasan dengan GROUP BY?',
                'kunci' => 'group by, ringkasan, agregat, sum count, rekap, pengelompokan, subtotal laporan',
                'jawab' => "Di tab **Kolom**:\n\n"
                    ."- kolom pengelompokan: centang **Kolom pengelompokan (GROUP BY)**\n"
                    ."- kolom hitungan: pilih **Agregat** (`SUM`, `COUNT`, `AVG`, `MIN`, `MAX`)\n"
                    ."- centang **Tampilkan total** pada kolom yang perlu baris total\n\n"
                    .'Kolom beragregat tidak bisa sekaligus jadi kolom pengelompokan, dan tidak bisa ikut pencarian.',
            ],
            [
                'kode' => 'report.grafik', 'kategori' => 'Report',
                'tanya' => 'Bagaimana menampilkan laporan dalam bentuk grafik?',
                'kunci' => 'grafik, chart, diagram, batang, garis, pie, donat, visualisasi',
                'jawab' => "Setel **Tipe** report ke *Grafik* di tab Pengaturan, lalu pilih bentuknya: batang tegak, batang mendatar, garis, area, lingkaran, atau donat.\n\n"
                    ."Tidak ada yang perlu didefinisikan ulang — label diambil dari **kolom pengelompokan**, nilainya dari **kolom berformat angka**.\n\n"
                    .'Grafik muncul di atas tabel, bukan menggantikannya.',
            ],
            [
                'kode' => 'report.grafik.kosong', 'kategori' => 'Report',
                'tanya' => 'Kenapa grafik laporan saya tidak muncul?',
                'kunci' => 'grafik kosong, chart tidak muncul, grafik tidak tampil, chart error',
                'jawab' => "Halaman akan menyebutkan alasannya sendiri. Dua penyebab yang paling sering:\n\n"
                    ."- belum ada kolom yang ditandai sebagai **kolom pengelompokan**\n"
                    .'- kolom nilainya masih berformat **teks** — hanya format Angka, Desimal, Mata uang, dan Persen yang bisa digambar',
            ],
            [
                'kode' => 'report.filter', 'kategori' => 'Report',
                'tanya' => 'Bagaimana menambah filter pada laporan?',
                'kunci' => 'filter, saring, operator, between, like, kriteria, parameter laporan',
                'jawab' => "Tab **Filter**. Setiap filter punya kolom, operator, dan jenis masukan. Nilai yang dibutuhkan per operator:\n\n"
                    ."- `=` `!=` `>` `>=` `<` `<=` `LIKE` — satu nilai\n"
                    ."- `BETWEEN` — dua nilai, satu per baris\n"
                    ."- `IN`, `NOT IN` — berapa pun\n"
                    ."- `IS NULL`, `IS NOT NULL` — tidak butuh nilai\n\n"
                    .'Opsi statis ditulis `nilai|label` per baris. `BETWEEN` yang hanya diisi satu ujung berarti "minimal sekian".',
            ],
            [
                'kode' => 'report.raw', 'kategori' => 'Report',
                'tanya' => 'Bisakah saya menulis SQL sendiri untuk laporan?',
                'kunci' => 'raw query, sql sendiri, query manual, mode raw, custom sql',
                'jawab' => "Bisa, tapi dimatikan secara bawaan. Menyalakannya butuh dua-duanya: izin `system.raw_query` **dan** setelan *Izinkan Raw Query pada Report* di **Pengaturan → Keamanan**.\n\n"
                    .'Hanya nyalakan bila Anda paham konsekuensinya — mode ini melewati pemeriksaan whitelist kolom.',
            ],

            // ---------------- Dashboard ----------------
            [
                'kode' => 'dashboard.widget', 'kategori' => 'Dashboard',
                'tanya' => 'Bagaimana menambah widget di dashboard?',
                'kunci' => 'dashboard, widget, beranda, kartu angka, atur dashboard',
                'jawab' => "**Sistem → Atur Dashboard**. Empat jenis widget:\n\n"
                    ."- **Angka ringkas** — agregat satu tabel\n"
                    ."- **Grafik** — menumpang report yang sudah ada\n"
                    ."- **Tabel ringkas** — N baris teratas dari report\n"
                    ."- **Catatan teks** — teks statis\n\n"
                    .'Lebar memakai grid 12 kolom; seret baris untuk mengubah urutan.',
                'route' => 'builder.dashboard.index', 'label' => 'Atur Dashboard', 'izin' => 'system.dashboard',
            ],
            [
                'kode' => 'dashboard.angka', 'kategori' => 'Dashboard',
                'tanya' => 'Bagaimana mengisi penyaring pada widget angka?',
                'kunci' => 'widget angka, penyaring widget, filter widget, count sum widget',
                'jawab' => "Satu kondisi per baris, format `kolom=nilai`:\n\n"
                    ."```\nstatus=published\n```\n\n"
                    .'`COUNT` tidak butuh kolom; agregat lain wajib. Baris yang sudah dihapus (soft delete) tidak ikut dihitung.',
            ],
            [
                'kode' => 'dashboard.izin', 'kategori' => 'Dashboard',
                'tanya' => 'Apakah widget dashboard bisa menembus izin report?',
                'kunci' => 'izin widget, keamanan dashboard, widget bocor, hak akses dashboard',
                'jawab' => "Tidak. Siapa pun yang tidak berhak membuka report-nya juga tidak melihat angkanya di dashboard.\n\n"
                    .'Isi kolom **Izin** pada widget bila ia hanya untuk sebagian orang. Widget tanpa izin terlihat semua yang login.',
            ],

            // ---------------- Ekspor & Cetak ----------------
            [
                'kode' => 'ekspor.cara', 'kategori' => 'Ekspor & Cetak', 'unggulan' => true,
                'tanya' => 'Bagaimana cara mengekspor data ke Excel atau PDF?',
                'kunci' => 'ekspor, excel, csv, pdf, cetak, download data, unduh',
                'jawab' => "Tombol **Excel · CSV · PDF · Cetak** ada di halaman daftar maupun laporan.\n\n"
                    ."Ekspor mengikuti **filter yang sedang aktif** — bukan seluruh tabel. Baris total dihitung dari seluruh hasil, bukan hanya halaman yang tampil.\n\n"
                    .'Data yang banyak otomatis dikerjakan di latar belakang dan muncul di halaman Berkas Ekspor.',
                'route' => 'exports.index', 'label' => 'Berkas Ekspor',
            ],
            [
                'kode' => 'ekspor.antre', 'kategori' => 'Ekspor & Cetak',
                'tanya' => 'Kenapa ekspor saya menggantung di status Antre?',
                'kunci' => 'antre, queue, ekspor tidak selesai, pending, menggantung, stuck',
                'jawab' => "Queue worker tidak berjalan. Jalankan:\n\n"
                    ."```\nphp artisan queue:work\n```\n\n"
                    .'Tanpa itu, ekspor besar akan mengantre selamanya.',
                'route' => 'exports.index', 'label' => 'Berkas Ekspor',
            ],
            [
                'kode' => 'ekspor.hilang', 'kategori' => 'Ekspor & Cetak',
                'tanya' => 'Berkas ekspor saya hilang sebelum sempat diunduh',
                'kunci' => 'berkas hilang, file ekspor hilang, kadaluarsa, expired, 7 hari',
                'jawab' => "Masa simpannya 7 hari, lalu dibuang otomatis. Minta ekspor ulang.\n\n"
                    .'Berkas juga hanya bisa diunduh oleh yang memesannya — isinya sudah tersaring memakai izin dan cakupan data orang tersebut.',
            ],
            [
                'kode' => 'ekspor.batas', 'kategori' => 'Ekspor & Cetak',
                'tanya' => 'Berapa batas jumlah baris yang bisa diekspor?',
                'kunci' => 'batas ekspor, limit baris, 50000, maksimal ekspor',
                'jawab' => 'Ekspor dibatasi 50.000 baris, berapa pun ambang yang disetel. Untuk data yang lebih besar dari itu, saring dulu lewat filter.',
            ],
            [
                'kode' => 'cetak.kop', 'kategori' => 'Ekspor & Cetak',
                'tanya' => 'Bagaimana mengatur kop perusahaan pada halaman cetak dan PDF?',
                'kunci' => 'kop, header cetak, logo perusahaan, kop surat, footer cetak',
                'jawab' => "Isi identitas perusahaan di **Pengaturan → Perusahaan**, lalu nyalakan *Tampilkan kop perusahaan* di tab **Cetak & Ekspor**.\n\n"
                    .'Bila logo perusahaan dikosongkan, logo aplikasi yang dipakai.',
                'route' => 'settings.index', 'label' => 'Buka Pengaturan', 'izin' => 'system.setting',
            ],

            // ---------------- Menu & Pengaturan ----------------
            [
                'kode' => 'menu.tambah', 'kategori' => 'Menu & Pengaturan',
                'tanya' => 'Bagaimana menambah menu di sidebar?',
                'kunci' => 'menu, sidebar, navigasi, tambah menu, menu tidak muncul',
                'jawab' => "**Sistem → Menu**. Tiap menu punya **Jenis Tautan**:\n\n"
                    ."- **Route Laravel** — diisi nama route\n"
                    ."- **URL langsung** — diisi alamat lengkap\n"
                    ."- **Form dinamis** — diisi kode form\n"
                    ."- **Report dinamis** — diisi kode report\n"
                    ."- **Header** — dikosongkan, hanya pembungkus\n\n"
                    .'Isi **Izin** agar menu hanya tampil bagi yang berhak. Header yang seluruh anaknya tersaring ikut hilang.',
                'route' => 'menus.index', 'label' => 'Buka Menu', 'izin' => 'system.menu',
            ],
            [
                'kode' => 'pengaturan.identitas', 'kategori' => 'Menu & Pengaturan',
                'tanya' => 'Bagaimana mengganti nama aplikasi, logo, dan warna tema?',
                'kunci' => 'nama aplikasi, logo, favicon, warna, tema, branding, tampilan, skin',
                'jawab' => "**Sistem → Pengaturan**. Nama aplikasi, logo, dan favicon ada di tab **Aplikasi**; warna sidebar dan navbar di tab **Tampilan**.\n\n"
                    ."Perubahan langsung berlaku di seluruh halaman.\n\n"
                    .'Berkas gambar yang diterima PNG, JPG, GIF, atau WEBP, maksimal 1 MB. SVG ditolak karena bisa memuat skrip.',
                'route' => 'settings.index', 'label' => 'Buka Pengaturan', 'izin' => 'system.setting',
            ],
            [
                'kode' => 'pengaturan.gelap', 'kategori' => 'Menu & Pengaturan',
                'tanya' => 'Bagaimana mengaktifkan mode gelap?',
                'kunci' => 'mode gelap, dark mode, tema gelap, malam',
                'jawab' => 'Klik ikon bulan di pojok kanan atas, di sebelah nama Anda. Pilihannya tersimpan di peramban masing-masing, jadi tidak memengaruhi pengguna lain.',
            ],

            // ---------------- Log & Pemeliharaan ----------------
            [
                'kode' => 'log.lihat', 'kategori' => 'Log & Pemeliharaan',
                'tanya' => 'Bagaimana melihat siapa yang mengubah sebuah data?',
                'kunci' => 'log aktivitas, audit, siapa mengubah, riwayat perubahan, jejak, history',
                'jawab' => "**Sistem → Log Aktivitas**. Setiap tambah, ubah, dan hapus tercatat.\n\n"
                    ."Saring berdasarkan pengguna, aksi, tabel, atau rentang tanggal. Klik ikon mata untuk melihat rinciannya — nilai lama dan baru berdampingan, kolom yang berubah disorot.\n\n"
                    .'Tabel log tumbuh tanpa batas; pakai **Buang log lebih tua dari … hari** secara berkala.',
                'route' => 'activity-logs.index', 'label' => 'Buka Log Aktivitas', 'izin' => 'system.activity_log',
            ],
            [
                'kode' => 'log.bentrok', 'kategori' => 'Log & Pemeliharaan',
                'tanya' => 'Kenapa muncul pesan "sudah diubah orang lain" saat menyimpan?',
                'kunci' => 'bentrok, konflik, sudah diubah orang lain, gagal simpan, conflict, tertimpa',
                'jawab' => "Memang begitu maksudnya — orang lain menyimpan lebih dulu sejak Anda membuka form ini.\n\n"
                    .'Muat ulang halaman dan ulangi perubahan Anda supaya pekerjaan mereka tidak tertimpa.',
            ],
            [
                'kode' => 'log.cache', 'kategori' => 'Log & Pemeliharaan',
                'tanya' => 'Perubahan metadata saya tidak terlihat di halaman',
                'kunci' => 'cache, tidak berubah, perubahan tidak muncul, refresh, clear cache',
                'jawab' => "Seharusnya langsung berlaku. Bila tidak:\n\n"
                    ."```\nphp artisan cache:clear\n```\n\n"
                    .'Menu, pengaturan, dan basis pengetahuan chatbot memakai cache permanen yang dibuang otomatis setiap kali disimpan lewat layar.',
            ],

            // ---------------- Menyambung Kode ----------------
            [
                'kode' => 'kode.handler', 'kategori' => 'Menyambung Kode',
                'tanya' => 'Bagaimana menjalankan kode sendiri dari sebuah tombol?',
                'kunci' => 'handler, kode sendiri, custom code, tombol jalankan kode, ekstensi, php',
                'jawab' => "Tulis class yang mengimplementasikan `App\\Contracts\\FormActionHandler`, lalu daftarkan kuncinya di `config/lowcode.php`:\n\n"
                    ."```\n'handlers' => ['posting_stok' => App\\Lowcode\\Handlers\\PostingStok::class],\n```\n\n"
                    ."Kuncinya lalu muncul sebagai pilihan di **Form Builder → Aksi** saat jenis aksinya *Handler*.\n\n"
                    .'Nama class tidak pernah datang dari database — metadata hanya menyimpan kuncinya.',
            ],
            [
                'kode' => 'kode.hook', 'kategori' => 'Menyambung Kode',
                'tanya' => 'Bagaimana menjalankan kode setiap kali form disimpan?',
                'kunci' => 'hook, before save, after save, nomor otomatis, nomor nota, penomoran, nomor dokumen, posting stok, trigger, event simpan',
                'jawab' => "Tulis class yang mengimplementasikan `App\\Contracts\\FormHook`, lalu kunci per kode form di `config/lowcode.php`:\n\n"
                    ."```\n'hooks' => ['nota_penjualan' => [App\\Lowcode\\Hooks\\NomorNota::class]],\n```\n\n"
                    ."Titik yang tersedia: `beforeSave`, `afterSave`, `beforeDelete`, `afterDelete`. Semuanya berjalan di dalam transaksi yang sama dengan penulisan barisnya.\n\n"
                    .'Hook sengaja tidak bisa dipilih dari builder: aturan bisnis yang wajib jalan tidak seharusnya bisa dimatikan lewat layar admin.',
            ],

            [
                'kode' => 'masalah.generator', 'kategori' => 'Masalah Umum',
                'tanya' => 'Kenapa tabel saya tidak muncul di generator?',
                'kunci' => 'tabel tidak muncul, generator kosong, tabel tidak ada, tidak terdaftar, pilihan tabel kosong',
                'jawab' => "Tabelnya belum terdaftar di **Sumber Data**, atau izin bacanya mati.\n\n"
                    .'Buka Sistem → Sumber Data, cari tabel itu di bagian *Tabel Belum Terdaftar*, daftarkan, lalu nyalakan **Boleh dibaca**.',
                'route' => 'data-sources.index', 'label' => 'Buka Sumber Data', 'izin' => 'system.data_source',
            ],
            [
                'kode' => 'masalah.ekspresi', 'kategori' => 'Masalah Umum',
                'tanya' => 'Kenapa kolom ekspresi SQL saya ditolak?',
                'kunci' => 'ekspresi ditolak, sql ditolak, expression, rumus sql, subquery ditolak',
                'jawab' => "Hanya referensi kolom, aritmetika, dan fungsi dasar yang diizinkan. Subquery, kata kunci SQL, dan komentar ditolak.\n\n"
                    .'Selain itu Anda butuh izin `system.raw_query`.',
            ],
            [
                'kode' => 'masalah.metadata', 'kategori' => 'Masalah Umum',
                'tanya' => 'Kenapa perubahan saya di builder tidak terlihat di halaman?',
                'kunci' => 'perubahan tidak terlihat, builder tidak berubah, masih lama, belum berubah',
                'jawab' => "Seharusnya langsung berlaku. Bila tidak, buang cache-nya:\n\n"
                    ."```\nphp artisan cache:clear\n```\n\n"
                    .'Kalau setelah itu masih sama, periksa apakah form atau kolomnya masih bertanda aktif.',
            ],

            // ---------------- Chatbot ----------------
            [
                'kode' => 'bantuan.chatbot', 'kategori' => 'Bantuan',
                'tanya' => 'Bagaimana chatbot bantuan ini bekerja?',
                'kunci' => 'chatbot, bot, asisten, bantuan, tanya jawab, ai, chatgpt, openai, model bahasa, privasi, cara kerja bot',
                'jawab' => "Ia mencocokkan pertanyaan Anda dengan basis pengetahuan yang tersimpan di database aplikasi ini. Tidak ada model bahasa dan tidak ada panggilan ke layanan luar — pertanyaan Anda tidak pernah meninggalkan server ini.\n\n"
                    ."Karena itu ia tidak mengarang: bila tidak ada jawaban yang cukup dekat, ia mengatakannya dan menawarkan pertanyaan terdekat.\n\n"
                    .'Pertanyaan yang tidak terjawab tercatat, supaya admin bisa melengkapi jawabannya.',
            ],
            [
                'kode' => 'bantuan.privasi', 'kategori' => 'Bantuan',
                'tanya' => 'Apakah aplikasi ini memakai ChatGPT atau AI?',
                'kunci' => 'chatgpt, openai, ai, kecerdasan buatan, llm, model bahasa, kirim ke luar, privasi, data keluar, internet',
                'jawab' => "Tidak. Chatbot bantuan ini mencocokkan pertanyaan dengan artikel yang tersimpan di database aplikasi ini sendiri.\n\n"
                    ."Tidak ada model bahasa, tidak ada kunci API, dan tidak ada panggilan ke layanan luar. Pertanyaan Anda tidak pernah meninggalkan server ini.\n\n"
                    .'Bagian aplikasi yang lain pun sama: seluruhnya berjalan di server Anda sendiri.',
            ],
            [
                'kode' => 'bantuan.tambah', 'kategori' => 'Bantuan',
                'tanya' => 'Bagaimana menambah jawaban baru ke chatbot?',
                'kunci' => 'tambah jawaban, kelola bantuan, artikel bantuan, isi chatbot, knowledge base',
                'jawab' => "**Sistem → Bantuan**. Tambahkan pertanyaan, jawabannya, dan kata kunci tambahan — sinonim, singkatan, dan bunyi pesan galat yang biasanya disalin pengguna.\n\n"
                    .'Halaman yang sama menampilkan **pertanyaan yang tidak terjawab**, urut dari yang paling sering ditanyakan. Di situlah daftar pekerjaan Anda.',
                'route' => 'help-articles.index', 'label' => 'Kelola Bantuan', 'izin' => 'system.help',
            ],
        ];
    }
}
