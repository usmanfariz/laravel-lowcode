<?php

namespace Tests\Feature;

use App\Models\HelpArticle;
use App\Models\HelpQuery;
use App\Models\User;
use App\Services\HelpBot;
use PHPUnit\Framework\Attributes\Test;
use Tests\MetadataTestCase;

/**
 * Chatbot bantuan.
 *
 * Yang diuji bukan kepintarannya, melainkan dua sifat yang membuatnya bisa
 * dipercaya: ia menemukan artikel yang tepat walau pertanyaannya ditulis
 * dengan kata lain, dan ia mengaku tidak tahu alih-alih menjawab asal.
 */
class HelpBotTest extends MetadataTestCase
{
    private User $pengelola;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pengelola = $this->makeUser('bantuan', ['system.help']);

        $this->artikel('ekspor.excel', 'Ekspor & Cetak', 'Bagaimana cara mengekspor data ke Excel?', [
            'keywords' => 'ekspor, excel, csv, unduh berkas',
            'answer' => 'Tombol Excel ada di halaman daftar. Ekspor mengikuti filter yang aktif.',
            'link_route' => 'exports.index',
            'link_label' => 'Berkas Ekspor',
        ]);

        $this->artikel('izin.403', 'Hak Akses', 'Kenapa form baru saya menampilkan 403?', [
            'keywords' => '403, forbidden, akses ditolak',
            'answer' => 'Permission-nya belum diberikan ke role Anda.',
            'link_route' => 'roles.index',
            'link_label' => 'Buka Role & Izin',
            'permission_code' => 'role.view',
        ]);

        $this->artikel('sumber.daftar', 'Sumber Data', 'Bagaimana cara mendaftarkan tabel sebagai sumber data?', [
            'keywords' => 'whitelist, daftarkan tabel, data source',
            'answer' => 'Buka Sistem lalu Sumber Data, klik nama tabel di bagian belum terdaftar.',
            'is_featured' => true,
        ]);
    }

    private function artikel(string $kode, string $kategori, string $tanya, array $extra = []): HelpArticle
    {
        return HelpArticle::create(array_merge([
            'code' => $kode,
            'category' => $kategori,
            'question' => $tanya,
            'answer' => 'Jawaban.',
            'order_no' => 0,
            'is_active' => true,
            'is_featured' => false,
        ], $extra));
    }

    private function tanya(string $pertanyaan, ?User $sebagai = null): array
    {
        return $this->actingAs($sebagai ?? $this->admin)
            ->postJson(route('help.ask'), ['question' => $pertanyaan])
            ->assertOk()
            ->json();
    }

    // ---------------- akses ----------------

    #[Test]
    public function tamu_tidak_bisa_bertanya(): void
    {
        $this->postJson(route('help.ask'), ['question' => 'apa saja'])->assertUnauthorized();
        $this->get(route('help.topics'))->assertRedirect(route('login'));
    }

    #[Test]
    public function semua_yang_login_boleh_bertanya_tanpa_permission_khusus(): void
    {
        $polos = $this->makeUser('polos', []);

        $this->actingAs($polos)
            ->postJson(route('help.ask'), ['question' => 'cara mengekspor data ke excel'])
            ->assertOk()
            ->assertJsonPath('answered', true);
    }

    #[Test]
    public function panel_bantuan_ikut_tergambar_di_setiap_halaman(): void
    {
        // Panel dipasang di layout, bukan per halaman. Test ini menjaga agar
        // pemasangannya tidak lepas tanpa ada yang sadar — termasuk saat
        // pembuatan URL artikel gagal karena batasan route.
        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="lc-bot"', false)
            ->assertSee('js/lc-chatbot.js', false)
            ->assertSee('LcChatbot.init', false);
    }

    // ---------------- pencocokan ----------------

    #[Test]
    public function pertanyaan_yang_ditulis_persis_menemukan_artikelnya(): void
    {
        $hasil = $this->tanya('Bagaimana cara mengekspor data ke Excel?');

        $this->assertTrue($hasil['answered']);
        $this->assertSame('ekspor.excel', HelpArticle::find($hasil['answer']['id'])->code);
    }

    #[Test]
    public function pertanyaan_dengan_kata_lain_tetap_menemukan_artikelnya(): void
    {
        // "gimana" → cara, "download" → unduh, dan "mendaftarkan" dikupas
        // jadi "daftar" supaya bertemu kata kunci "daftarkan tabel".
        $bentuk = [
            'gimana caranya export data' => 'ekspor.excel',
            'cara download data ke excel' => 'ekspor.excel',
            'cara mendaftarkan tabel' => 'sumber.daftar',
            'form saya kena 403' => 'izin.403',
        ];

        foreach ($bentuk as $pertanyaan => $kode) {
            $hasil = $this->tanya($pertanyaan);

            $this->assertTrue($hasil['answered'], "\"{$pertanyaan}\" tidak terjawab");
            $this->assertSame($kode, HelpArticle::find($hasil['answer']['id'])->code,
                "\"{$pertanyaan}\" menemukan artikel yang salah");
        }
    }

    #[Test]
    public function pertanyaan_di_luar_pengetahuan_dijawab_tidak_tahu(): void
    {
        $hasil = $this->tanya('berapa harga langganan bulanan produk ini');

        $this->assertFalse($hasil['answered']);
        $this->assertNull($hasil['answer']);
    }

    #[Test]
    public function satu_kata_yang_kebetulan_cocok_tidak_cukup_untuk_menjawab(): void
    {
        // "filter" hanya muncul di badan artikel ekspor. Cakupan token yang
        // rendah harus menahan bot dari menjawab dengan percaya diri.
        $hasil = $this->tanya('apakah saya perlu memasang antivirus di server kantor pusat');

        $this->assertFalse($hasil['answered']);
    }

    #[Test]
    public function jawaban_menawarkan_pertanyaan_terkait(): void
    {
        $hasil = $this->tanya('cara mengekspor data');

        $this->assertTrue($hasil['answered']);
        $this->assertIsArray($hasil['related']);
        $this->assertNotContains(
            $hasil['answer']['id'],
            array_column($hasil['related'], 'id'),
            'artikel yang sudah dijawab tidak boleh diulang sebagai saran'
        );
    }

    #[Test]
    public function yang_tidak_terjawab_tetap_menawarkan_sesuatu(): void
    {
        $hasil = $this->tanya('bagaimana cara memasak rendang');

        $this->assertFalse($hasil['answered']);
        $this->assertNotEmpty($hasil['related'], 'saran bawaan harus tetap muncul');
    }

    #[Test]
    public function artikel_nonaktif_tidak_pernah_dijawab(): void
    {
        HelpArticle::where('code', 'ekspor.excel')->update(['is_active' => false]);
        app(HelpBot::class)->flush();

        $hasil = $this->tanya('Bagaimana cara mengekspor data ke Excel?');

        $this->assertFalse($hasil['answered']);
    }

    // ---------------- tombol tautan ----------------

    #[Test]
    public function tombol_tautan_disembunyikan_dari_yang_tidak_berhak(): void
    {
        $pemegangIzin = $this->makeUser('punya_role', ['role.view']);
        $tanpaIzin = $this->makeUser('tanpa_izin', []);

        $berhak = $this->tanya('Kenapa form baru saya menampilkan 403?', $pemegangIzin);
        $tidak = $this->tanya('Kenapa form baru saya menampilkan 403?', $tanpaIzin);

        $this->assertNotNull($berhak['answer']['link']);
        $this->assertNull($tidak['answer']['link'], 'tautan bocor ke pengguna tanpa izin');
    }

    #[Test]
    public function jawabannya_sendiri_tetap_terbaca_walau_tautannya_disembunyikan(): void
    {
        $hasil = $this->tanya('Kenapa form baru saya menampilkan 403?', $this->makeUser('lain', []));

        $this->assertTrue($hasil['answered']);
        $this->assertStringContainsString('Permission', $hasil['answer']['answer']);
    }

    #[Test]
    public function route_yang_belum_ada_tidak_menggagalkan_jawaban(): void
    {
        HelpArticle::where('code', 'ekspor.excel')->update(['link_route' => 'route.yang.tidak.ada']);
        app(HelpBot::class)->flush();

        $hasil = $this->tanya('Bagaimana cara mengekspor data ke Excel?');

        $this->assertTrue($hasil['answered']);
        $this->assertNull($hasil['answer']['link']);
    }

    // ---------------- riwayat ----------------

    #[Test]
    public function pertanyaan_tanpa_jawaban_tercatat(): void
    {
        $this->tanya('bagaimana cara mencetak stiker gudang');

        $this->assertDatabaseHas('help_queries', [
            'question' => 'bagaimana cara mencetak stiker gudang',
            'is_answered' => false,
            'help_article_id' => null,
        ]);
    }

    #[Test]
    public function pertanyaan_yang_terjawab_tercatat_beserta_artikelnya(): void
    {
        $this->tanya('Bagaimana cara mengekspor data ke Excel?');

        $catatan = HelpQuery::latest('id')->first();

        $this->assertTrue($catatan->is_answered);
        $this->assertSame('ekspor.excel', $catatan->article->code);
        $this->assertSame($this->admin->id, $catatan->user_id);
    }

    // ---------------- topik & artikel ----------------

    #[Test]
    public function daftar_topik_dikelompokkan_per_kategori(): void
    {
        $data = $this->actingAs($this->admin)->getJson(route('help.topics'))->assertOk()->json();

        $this->assertSame(['sumber.daftar'], array_map(
            fn (array $s) => HelpArticle::find($s['id'])->code,
            $data['featured']
        ), 'hanya artikel unggulan yang ditawarkan lebih dulu');

        $this->assertEqualsCanonicalizing(
            ['Ekspor & Cetak', 'Hak Akses', 'Sumber Data'],
            array_column($data['topics'], 'category')
        );
    }

    #[Test]
    public function artikel_bisa_dibuka_langsung_lewat_id(): void
    {
        $id = HelpArticle::where('code', 'sumber.daftar')->value('id');

        $this->actingAs($this->admin)->getJson(route('help.article', $id))
            ->assertOk()
            ->assertJsonPath('answered', true)
            ->assertJsonPath('answer.id', $id);
    }

    #[Test]
    public function artikel_nonaktif_tidak_bisa_dibuka_lewat_id(): void
    {
        $id = HelpArticle::where('code', 'sumber.daftar')->value('id');
        HelpArticle::where('id', $id)->update(['is_active' => false]);
        app(HelpBot::class)->flush();

        $this->actingAs($this->admin)->getJson(route('help.article', $id))->assertNotFound();
    }

    // ---------------- pengelolaan ----------------

    #[Test]
    public function halaman_kelola_butuh_izin_system_help(): void
    {
        $this->actingAs($this->makeUser('bukan_pengelola', []))
            ->get(route('help-articles.index'))
            ->assertForbidden();

        $this->actingAs($this->pengelola)->get(route('help-articles.index'))->assertOk();
    }

    #[Test]
    public function artikel_baru_langsung_bisa_dijawab_bot(): void
    {
        // Basis pengetahuan di-cache permanen. Menyimpan lewat layar harus
        // membuangnya, kalau tidak jawaban baru tidak muncul sampai ada yang
        // menjalankan cache:clear — dan tidak ada yang tahu harus.
        $this->assertFalse($this->tanya('cara mengatur backup harian')['answered']);

        $this->actingAs($this->pengelola)->post(route('help-articles.store'), [
            'code' => 'pemeliharaan.backup',
            'category' => 'Log & Pemeliharaan',
            'question' => 'Bagaimana cara mengatur backup harian?',
            'answer' => 'Pakai penjadwal di server.',
            'keywords' => 'backup, cadangan',
            'order_no' => 0,
            'is_active' => '1',
        ])->assertRedirect(route('help-articles.index'));

        $this->assertTrue($this->tanya('cara mengatur backup harian')['answered']);
    }

    #[Test]
    public function artikel_yang_dihapus_berhenti_dijawab(): void
    {
        $artikel = HelpArticle::where('code', 'ekspor.excel')->first();

        $this->actingAs($this->pengelola)
            ->delete(route('help-articles.destroy', $artikel))
            ->assertRedirect(route('help-articles.index'));

        $this->assertFalse($this->tanya('Bagaimana cara mengekspor data ke Excel?')['answered']);
    }

    #[Test]
    public function kode_artikel_tidak_boleh_kembar(): void
    {
        $this->actingAs($this->pengelola)->post(route('help-articles.store'), [
            'code' => 'ekspor.excel',
            'category' => 'Umum',
            'question' => 'Pertanyaan lain',
            'answer' => 'Jawaban lain.',
            'order_no' => 0,
        ])->assertSessionHasErrors('code');
    }

    #[Test]
    public function pertanyaan_tak_terjawab_muncul_di_halaman_kelola(): void
    {
        $this->tanya('bagaimana cara memasang mesin absensi');
        $this->tanya('bagaimana cara memasang mesin absensi');

        $this->actingAs($this->pengelola)->get(route('help-articles.index'))
            ->assertOk()
            ->assertSee('bagaimana cara memasang mesin absensi')
            ->assertSee('2×');
    }

    #[Test]
    public function riwayat_lama_bisa_dibuang(): void
    {
        $this->tanya('pertanyaan lama sekali');
        HelpQuery::query()->update(['created_at' => now()->subDays(200)]);

        $this->actingAs($this->pengelola)
            ->post(route('help-articles.prune'), ['days' => 90])
            ->assertRedirect();

        $this->assertSame(0, HelpQuery::count());
    }

    // ---------------- masukan ----------------

    #[Test]
    public function pertanyaan_kosong_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('help.ask'), ['question' => ''])
            ->assertStatus(422);
    }

    #[Test]
    public function html_di_dalam_pertanyaan_tidak_dieksekusi_di_halaman_kelola(): void
    {
        $this->tanya('<script>alert(1)</script> cara aneh');

        $this->actingAs($this->pengelola)->get(route('help-articles.index'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
