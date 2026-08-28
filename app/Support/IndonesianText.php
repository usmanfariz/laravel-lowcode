<?php

namespace App\Support;

/**
 * Pemotong kata untuk pencarian bahasa Indonesia.
 *
 * Bukan pemroses bahasa alami. Tugasnya sempit: membuat "Gimana caranya
 * ngehapus form?" dan "Cara menghapus form" bertemu di token yang sama.
 * Tiga lapis, dari yang paling murah:
 *
 *   1. sinonim  — istilah yang berbeda tapi bermaksud sama disatukan
 *   2. stopword — kata yang muncul di hampir semua pertanyaan dibuang
 *   3. stem     — imbuhan dikupas sampai tersisa kata dasarnya
 *
 * Kupasan imbuhannya sengaja konservatif. Salah kupas menghasilkan kecocokan
 * yang mengejutkan, dan pada basis pengetahuan sebesar ini tidak ada yang
 * perlu dikorbankan demi menangkap satu-dua bentuk kata yang jarang.
 */
class IndonesianText
{
    /**
     * Kata yang muncul di hampir setiap pertanyaan, jadi tidak membedakan apa pun.
     *
     * "tidak" dan "bisa" sengaja TIDAK di sini: "tidak bisa menyimpan" adalah
     * keluhan, "cara menyimpan" adalah pertanyaan, dan keduanya berbeda jawaban.
     *
     * "cara" dan "kenapa" ADA di sini justru karena keduanya kata tanya. Nyaris
     * setiap artikel bantuan memuatnya, jadi mencocokkannya sama saja dengan
     * tidak mencocokkan apa-apa — sementara skornya tetap ikut terhitung dan
     * membuat bot menjawab dengan yakin memakai artikel yang keliru.
     */
    private const STOPWORDS = [
        'yang', 'di', 'ke', 'dari', 'untuk', 'dengan', 'pada', 'dan', 'atau',
        'adalah', 'itu', 'ini', 'saya', 'aku', 'kami', 'kita', 'anda', 'apa',
        'apakah', 'sih', 'dong', 'ya', 'ada', 'agar', 'supaya', 'oleh', 'jadi',
        'kalau', 'bila', 'saat', 'juga', 'saja', 'aja', 'lagi', 'mau', 'ingin',
        'tolong', 'mohon', 'gimana', 'sudah', 'udah', 'akan', 'the', 'a', 'an',
        'how', 'to', 'is', 'do', 'i', 'of', 'in', 'cara', 'kenapa', 'berapa',
    ];

    /**
     * Istilah yang disatukan sebelum dicocokkan.
     *
     * Isinya dua macam: ragam tidak baku yang dipakai orang saat mengetik cepat
     * ("gmn", "gak"), dan padanan Inggris–Indonesia yang sama-sama beredar di
     * layar aplikasi ini ("report" di kode, "laporan" di menu).
     */
    private const SYNONYMS = [
        'gmn' => 'cara', 'bagaimana' => 'cara', 'caranya' => 'cara', 'how' => 'cara',
        'mengapa' => 'kenapa', 'knp' => 'kenapa', 'why' => 'kenapa',
        'gak' => 'tidak', 'ga' => 'tidak', 'nggak' => 'tidak', 'ngga' => 'tidak',
        'tak' => 'tidak', 'engga' => 'tidak', 'kagak' => 'tidak', 'not' => 'tidak',
        'bikin' => 'buat', 'create' => 'buat', 'menambah' => 'tambah', 'add' => 'tambah',
        'sunting' => 'ubah', 'edit' => 'ubah', 'mengubah' => 'ubah', 'update' => 'ubah',
        'delete' => 'hapus', 'remove' => 'hapus',
        'report' => 'laporan', 'reports' => 'laporan',
        'chart' => 'grafik', 'diagram' => 'grafik', 'graph' => 'grafik',
        'permission' => 'izin', 'permissions' => 'izin', 'hak' => 'izin',
        'akses' => 'izin', 'perizinan' => 'izin',
        'user' => 'pengguna', 'users' => 'pengguna', 'akun' => 'pengguna',
        'password' => 'sandi', 'katasandi' => 'sandi', 'pw' => 'sandi',
        'export' => 'ekspor', 'eksport' => 'ekspor', 'download' => 'unduh',
        'print' => 'cetak', 'printing' => 'cetak',
        'error' => 'gagal', 'eror' => 'gagal', 'galat' => 'gagal',
        'login' => 'masuk', 'signin' => 'masuk', 'logout' => 'keluar',
        'table' => 'tabel', 'tables' => 'tabel',
        'column' => 'kolom', 'columns' => 'kolom',
        'row' => 'baris', 'rows' => 'baris', 'record' => 'baris',
        'formula' => 'rumus', 'search' => 'cari', 'filter' => 'saring',
        'setting' => 'pengaturan', 'settings' => 'pengaturan', 'konfigurasi' => 'pengaturan',
        'upload' => 'unggah', 'foto' => 'gambar', 'image' => 'gambar',
        'master' => 'induk', 'parent' => 'induk', 'child' => 'detail',
        'backup' => 'cadangan', 'log' => 'log', 'audit' => 'log',
    ];

    /**
     * Awalan yang boleh dikupas, beserta huruf pengganti bila terjadi peluluhan.
     *
     * "menyimpan" → "simpan" butuh 'meny' dipulihkan jadi 's'; tanpa aturan itu
     * yang tersisa "impan" dan tidak cocok dengan apa pun.
     *
     * Urutannya penting: yang terpanjang diuji lebih dulu supaya 'meng' tidak
     * keburu tertangkap sebagai 'me'.
     */
    private const PREFIXES = [
        'meny' => 's', 'peny' => 's',
        'meng' => '', 'peng' => '',
        'mem' => '', 'pem' => '', 'men' => '', 'pen' => '',
        'ber' => '', 'ter' => '', 'per' => '',
        'me' => '', 'di' => '', 'ke' => '', 'se' => '', 'pe' => '',
    ];

    /**
     * Akhiran yang dikupas. "-in" masuk karena itulah bentuk "-kan" dalam
     * ragam sehari-hari — "daftarin", "hapusin", "tambahin" — dan justru
     * ragam itulah yang diketik orang ke kotak chat.
     */
    private const SUFFIXES = ['nya', 'kan', 'an', 'in'];

    /** Panjang minimal sisa kata setelah dikupas. Di bawah ini kupasannya dibatalkan. */
    private const MIN_STEM = 4;

    /**
     * Pecah kalimat menjadi token siap banding.
     *
     * @return array<int, string>
     */
    public static function tokens(string $text): array
    {
        $bersih = preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($text, 'UTF-8'));
        $kata = preg_split('/\s+/', trim((string) $bersih), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $token = [];

        foreach ($kata as $k) {
            $k = self::SYNONYMS[$k] ?? $k;

            if (mb_strlen($k) < 2 || in_array($k, self::STOPWORDS, true)) {
                continue;
            }

            $token[] = $k;
        }

        return array_values(array_unique($token));
    }

    /** Bentuk dasar sebuah kata, sejauh yang bisa diduga tanpa kamus. */
    public static function stem(string $kata): string
    {
        $kata = self::SYNONYMS[$kata] ?? $kata;

        foreach (self::PREFIXES as $awalan => $pengganti) {
            if (! str_starts_with($kata, $awalan)) {
                continue;
            }

            $sisa = $pengganti.mb_substr($kata, mb_strlen($awalan));

            if (mb_strlen($sisa) >= self::MIN_STEM) {
                $kata = $sisa;
            }

            break;
        }

        foreach (self::SUFFIXES as $akhiran) {
            if (str_ends_with($kata, $akhiran)) {
                $sisa = mb_substr($kata, 0, -mb_strlen($akhiran));

                if (mb_strlen($sisa) >= self::MIN_STEM) {
                    return $sisa;
                }
            }
        }

        return $kata;
    }

    /**
     * Apakah dua kata dianggap kata yang sama.
     *
     * Pencocokan sebagian ("hapus" di dalam "menghapus") dibatasi kata sepanjang
     * empat huruf ke atas. Di bawah itu terlalu banyak kata pendek yang saling
     * bersarang dan mencocokkan hal yang tidak berhubungan.
     */
    public static function cocok(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        if (self::stem($a) === self::stem($b)) {
            return true;
        }

        $panjang = min(mb_strlen($a), mb_strlen($b));

        return $panjang >= self::MIN_STEM
            && (str_contains($a, $b) || str_contains($b, $a));
    }

    /** Bentuk kalimat yang dipakai untuk mencari kecocokan utuh. */
    public static function normalkan(string $text): string
    {
        $bersih = preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($text, 'UTF-8'));

        return trim(preg_replace('/\s+/', ' ', (string) $bersih) ?? '');
    }
}
