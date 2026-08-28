<?php

namespace App\Services;

use App\Models\HelpArticle;
use App\Models\HelpQuery;
use App\Models\User;
use App\Support\IndonesianText;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Chatbot bantuan — pencocokan pertanyaan ke basis pengetahuan lokal.
 *
 * Tidak ada model bahasa dan tidak ada panggilan keluar. Yang dikerjakan
 * sederhana: pertanyaan dipecah jadi token, tiap artikel diberi skor menurut
 * di bagian mana token itu ditemukan, dan yang tertinggi dijawab.
 *
 * Konsekuensi yang disengaja: bot ini tidak mengarang. Bila tidak ada artikel
 * yang cukup dekat, ia mengaku tidak tahu dan menawarkan pertanyaan terdekat.
 * Petunjuk pemakaian yang salah lebih merugikan daripada tidak ada petunjuk —
 * pengguna akan menurutinya, lalu menyalahkan aplikasinya.
 */
class HelpBot
{
    private const CACHE_KEY = 'help.articles';

    /**
     * Bobot per tempat ditemukannya token.
     *
     * Kemunculan di pertanyaan bernilai jauh lebih tinggi daripada di jawaban:
     * kata "ekspor" di badan artikel tentang dashboard tidak menjadikannya
     * artikel tentang ekspor.
     */
    private const BOBOT = [
        'question' => 6.0,
        'keywords' => 4.0,
        'category' => 2.0,
        'answer' => 1.5,
    ];

    /** Pertanyaan yang tersalin nyaris utuh hampir pasti artikel yang dimaksud. */
    private const BONUS_UTUH = 10.0;

    /**
     * Ambang untuk berani menjawab.
     *
     * Kira-kira setara satu kecocokan kuat di kata kunci, dan setengah token
     * pertanyaan harus tertangkap. Dua-duanya perlu, dan yang kedua yang
     * paling menahan: skor saja bisa dikumpulkan dari satu-dua kata yang
     * kebetulan cocok di pertanyaan panjang yang membahas hal lain.
     */
    private const AMBANG_SKOR = 5.0;

    private const AMBANG_CAKUPAN = 0.5;

    private const MAKS_TERKAIT = 3;

    /** @var array<int, array<string, array<int, string>>> */
    private array $indeksCache = [];

    /**
     * Jawaban untuk satu pertanyaan.
     *
     * @return array{question: string, answered: bool, answer: ?array, related: array, score: float}
     */
    public function jawab(string $pertanyaan, ?User $user = null): array
    {
        $token = IndonesianText::tokens($pertanyaan);
        $utuh = IndonesianText::normalkan($pertanyaan);

        $berperingkat = $this->peringkat($token, $utuh);
        $teratas = $berperingkat->first();

        $terjawab = $teratas !== null
            && $teratas['skor'] >= self::AMBANG_SKOR
            && $teratas['cakupan'] >= self::AMBANG_CAKUPAN
            && $teratas['kena_pertanyaan'];

        // Yang tidak terjawab tetap menawarkan kandidat terdekat. Kalau tidak
        // ada satu pun kandidat, saran bawaanlah yang tampil — layar kosong
        // membuat pengguna berhenti mencoba.
        $terkait = $berperingkat
            ->skip($terjawab ? 1 : 0)
            ->take(self::MAKS_TERKAIT)
            ->values();

        if ($terkait->isEmpty()) {
            $terkait = $this->unggulan()
                ->take(self::MAKS_TERKAIT)
                ->map(fn (HelpArticle $a) => ['artikel' => $a]);
        }

        return [
            'question' => $pertanyaan,
            'answered' => $terjawab,
            'answer' => $terjawab ? $this->bentuk($teratas['artikel'], $user) : null,
            'related' => $terkait
                ->map(fn (array $b) => [
                    'id' => $b['artikel']->id,
                    'question' => $b['artikel']->question,
                    'category' => $b['artikel']->category,
                ])
                ->values()
                ->all(),
            'score' => round($teratas['skor'] ?? 0, 2),
        ];
    }

    /** Satu artikel utuh, dipanggil saat pengguna menekan saran. */
    public function artikel(int $id, ?User $user = null): ?array
    {
        $artikel = $this->semua()->firstWhere('id', $id);

        return $artikel ? $this->bentuk($artikel, $user) : null;
    }

    /**
     * Daftar topik untuk mode telusur — dipakai saat panel baru dibuka dan
     * pengguna belum tahu harus bertanya apa.
     */
    public function topik(): array
    {
        return $this->semua()
            ->groupBy('category')
            ->map(fn (Collection $isi, string $kategori) => [
                'category' => $kategori,
                'questions' => $isi
                    ->map(fn (HelpArticle $a) => ['id' => $a->id, 'question' => $a->question])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /** Pertanyaan pembuka yang ditawarkan sebelum ada yang diketik. */
    public function saranAwal(): array
    {
        return $this->unggulan()
            ->map(fn (HelpArticle $a) => [
                'id' => $a->id,
                'question' => $a->question,
                'category' => $a->category,
            ])
            ->all();
    }

    /** @return Collection<int, HelpArticle> */
    private function unggulan(): Collection
    {
        $unggulan = $this->semua()->where('is_featured', true);

        // Belum ada yang ditandai unggulan? Ambil urutan teratas apa adanya,
        // supaya panel bantuan tidak pernah dibuka dalam keadaan kosong.
        if ($unggulan->isEmpty()) {
            $unggulan = $this->semua();
        }

        return $unggulan->take(6)->values();
    }

    /** Rekam pertanyaan supaya lubang di basis pengetahuan bisa terlihat. */
    public function catat(array $hasil, ?User $user): void
    {
        HelpQuery::create([
            'user_id' => $user?->id,
            'question' => mb_substr($hasil['question'], 0, 255),
            'help_article_id' => $hasil['answer']['id'] ?? null,
            'score' => $hasil['score'],
            'is_answered' => $hasil['answered'],
            'created_at' => now(),
        ]);
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    // ------------------------------------------------------------------

    /**
     * Skor tiap artikel terhadap token pertanyaan, tertinggi lebih dulu.
     *
     * Dua lintasan. Yang pertama mencatat di bagian mana tiap token ditemukan;
     * yang kedua meredam token yang ternyata ditemukan di mana-mana. Tanpa
     * peredaman itu, satu kata umum yang muncul di banyak artikel cukup untuk
     * membuat bot menjawab dengan yakin memakai artikel yang keliru.
     *
     * @param  array<int, string>  $token
     * @return Collection<int, array{artikel: HelpArticle, skor: float, cakupan: float, kena_pertanyaan: bool}>
     */
    private function peringkat(array $token, string $utuh): Collection
    {
        if ($token === []) {
            return collect();
        }

        $artikel = $this->semua();
        $jumlahArtikel = max(1, $artikel->count());

        // Lintasan 1 — bobot mentah per pasangan artikel/token.
        $mentah = $artikel->map(function (HelpArticle $a) use ($token) {
            $indeks = $this->indeks($a);
            $bobot = [];

            foreach ($token as $t) {
                $terbaik = 0.0;

                foreach (self::BOBOT as $bagian => $b) {
                    if ($b > $terbaik && $this->adaDi($indeks[$bagian], $t)) {
                        $terbaik = $b;
                    }
                }

                $bobot[$t] = $terbaik;
            }

            return ['artikel' => $a, 'bobot' => $bobot];
        });

        // Lintasan 2 — seberapa banyak artikel yang mengaku mengenali token ini.
        //
        // Dihitung hanya dari bagian yang menandai isi artikel (pertanyaan,
        // kata kunci, kategori). Badan jawaban tidak ikut: kata "ekspor" yang
        // kebetulan disebut di sepuluh jawaban tidak boleh melemahkan artikel
        // yang memang membahas ekspor.
        $sebaran = [];

        foreach ($token as $t) {
            $sebaran[$t] = $mentah
                ->filter(fn (array $r) => $r['bobot'][$t] >= self::BOBOT['category'])
                ->count();
        }

        return $mentah
            ->map(function (array $r) use ($token, $sebaran, $jumlahArtikel, $utuh) {
                $skor = 0.0;
                $kena = 0;
                $kenaPertanyaan = false;

                foreach ($token as $t) {
                    $bobot = $r['bobot'][$t];

                    if ($bobot <= 0) {
                        continue;
                    }

                    $skor += $bobot * $this->dayaBeda($sebaran[$t], $jumlahArtikel);
                    $kena++;

                    // Hanya kecocokan di pertanyaan atau kata kunci yang
                    // dianggap menandai topik. Kemunculan di kategori atau
                    // badan jawaban menaikkan skor, tapi tidak cukup jadi
                    // alasan untuk menjawab.
                    if ($bobot >= self::BOBOT['keywords']) {
                        $kenaPertanyaan = true;
                    }
                }

                // Pertanyaan yang disalin apa adanya dari daftar saran.
                if ($utuh !== '' && str_contains(IndonesianText::normalkan($r['artikel']->question), $utuh)) {
                    $skor += self::BONUS_UTUH;
                    $kenaPertanyaan = true;
                }

                return [
                    'artikel' => $r['artikel'],
                    'skor' => $skor,
                    'cakupan' => $kena / count($token),
                    'kena_pertanyaan' => $kenaPertanyaan,
                ];
            })
            ->filter(fn (array $b) => $b['skor'] > 0)
            ->sortByDesc('skor')
            ->values();
    }

    /**
     * Daya beda sebuah token: makin banyak artikel yang mengenalinya, makin
     * kecil artinya. Ditahan di 0,15 supaya token yang ada di semua artikel
     * tetap bernilai sedikit — bukan menghapus kecocokan yang memang benar.
     */
    private function dayaBeda(int $sebaran, int $jumlahArtikel): float
    {
        return max(0.15, 1 - $sebaran / $jumlahArtikel);
    }

    /** @param  array<int, string>  $daftar */
    private function adaDi(array $daftar, string $token): bool
    {
        foreach ($daftar as $kata) {
            if (IndonesianText::cocok($token, $kata)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Token per bagian artikel.
     *
     * Disimpan per instansi, bukan statis: satu request bisa mengajukan
     * beberapa pertanyaan, tapi indeks yang hidup melintasi request akan
     * menyimpan isi artikel yang sudah disunting admin.
     *
     * @return array<string, array<int, string>>
     */
    private function indeks(HelpArticle $artikel): array
    {
        return $this->indeksCache[$artikel->id] ??= [
            'question' => IndonesianText::tokens($artikel->question),
            'keywords' => IndonesianText::tokens((string) $artikel->keywords),
            'category' => IndonesianText::tokens($artikel->category),
            'answer' => IndonesianText::tokens($artikel->answer),
        ];
    }

    private function bentuk(HelpArticle $artikel, ?User $user): array
    {
        $url = $artikel->linkVisibleTo($user) ? $artikel->linkUrl() : null;

        return [
            'id' => $artikel->id,
            'category' => $artikel->category,
            'question' => $artikel->question,
            'answer' => $artikel->answer,
            'link' => $url ? [
                'url' => $url,
                'label' => $artikel->link_label ?: 'Buka halaman',
            ] : null,
        ];
    }

    /** @return Collection<int, HelpArticle> */
    private function semua(): Collection
    {
        // Seperti cache menu: yang disimpan array mentah, bukan model. Model
        // ter-serialize gagal dibaca kembali dari proses lain.
        $rows = Cache::rememberForever(self::CACHE_KEY, fn () => DB::table('help_articles')
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('order_no')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all());

        return HelpArticle::hydrate($rows);
    }
}
