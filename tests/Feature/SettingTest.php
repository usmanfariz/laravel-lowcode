<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\MetadataTestCase;

/**
 * Pengaturan aplikasi — halaman yang digambar dari isi tabel `settings`.
 */
class SettingTest extends MetadataTestCase
{
    private User $pengelola;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->pengelola = $this->makeUser('pengaturan', ['system.setting']);

        $this->buat('general', 'app_name', 'Low-Code Platform', 'string');
        $this->buat('general', 'app_logo', null, 'file');
        $this->buat('general', 'per_page', '25', 'integer');
        $this->buat('appearance', 'sidebar_skin', 'sidebar-dark-primary', 'string', [
            'input_type' => 'select',
            'options' => ['sidebar-dark-primary' => 'Gelap', 'sidebar-light-primary' => 'Terang'],
        ]);
        $this->buat('security', 'allow_raw_query', '1', 'boolean');
    }

    private function buat(string $group, string $key, ?string $value, string $type, array $extra = []): Setting
    {
        return Setting::create(array_merge([
            'group_name' => $group, 'key_name' => $key, 'value' => $value,
            'value_type' => $type, 'label' => ucfirst($key), 'is_public' => false,
            'order_no' => 1,
        ], $extra));
    }

    /** Kiriman lengkap; test cukup menimpa yang sedang diuji. */
    private function isian(array $overrides = []): array
    {
        return array_replace_recursive([
            'values' => [
                'app_name' => 'Portal Internal',
                'per_page' => '50',
                'sidebar_skin' => 'sidebar-dark-primary',
                'allow_raw_query' => '1',
            ],
        ], $overrides);
    }

    // ---------------- hak akses ----------------

    #[Test]
    public function tanpa_izin_pengaturan_ditolak(): void
    {
        $this->actingAs($this->admin)->get(route('settings.index'))->assertForbidden();
        $this->actingAs($this->admin)->put(route('settings.update'), $this->isian())->assertForbidden();
    }

    #[Test]
    public function halaman_menampilkan_isian_tiap_pengaturan(): void
    {
        $this->actingAs($this->pengelola)->get(route('settings.index'))
            ->assertOk()
            ->assertSee('values[app_name]', false)
            ->assertSee('files[app_logo]', false)
            ->assertSee('Aplikasi')
            ->assertSee('Keamanan');
    }

    // ---------------- penyimpanan ----------------

    #[Test]
    public function menyimpan_mengubah_nilai_dan_membuang_cache(): void
    {
        // Dibaca lebih dulu supaya nilai lamanya benar-benar masuk cache.
        $this->assertSame('Low-Code Platform', setting('app_name'));

        $this->actingAs($this->pengelola)
            ->put(route('settings.update'), $this->isian())
            ->assertRedirect(route('settings.index'));

        $this->assertSame('Portal Internal', setting('app_name'));
        $this->assertSame(50, setting('per_page'));
    }

    #[Test]
    public function saklar_yang_dimatikan_tersimpan_sebagai_nol(): void
    {
        $this->actingAs($this->pengelola)->put(
            route('settings.update'),
            $this->isian(['values' => ['allow_raw_query' => '0']])
        );

        $this->assertFalse(setting('allow_raw_query'));
    }

    #[Test]
    public function isian_kosong_disimpan_sebagai_null_agar_jatuh_ke_bawaan(): void
    {
        $this->actingAs($this->pengelola)->put(
            route('settings.update'),
            $this->isian(['values' => ['app_name' => '']])
        );

        $this->assertNull(Setting::where('key_name', 'app_name')->value('value'));
        $this->assertSame('Cadangan', setting('app_name', 'Cadangan'));
    }

    #[Test]
    public function kunci_yang_tidak_terdaftar_diabaikan(): void
    {
        $this->actingAs($this->pengelola)->put(
            route('settings.update'),
            $this->isian(['values' => ['kunci_asing' => 'nilai']])
        );

        $this->assertFalse(Setting::where('key_name', 'kunci_asing')->exists());
    }

    #[Test]
    public function kunci_yang_tidak_dikirim_tidak_ikut_terhapus(): void
    {
        $kiriman = $this->isian();
        unset($kiriman['values']['per_page']);

        $this->actingAs($this->pengelola)->put(route('settings.update'), $kiriman);

        $this->assertSame(25, setting('per_page'));
    }

    #[Test]
    public function perubahan_tercatat_di_log_aktivitas(): void
    {
        $this->actingAs($this->pengelola)->put(route('settings.update'), $this->isian());

        $log = DB::table('activity_logs')->where('table_name', 'settings')->first();

        $this->assertNotNull($log);
        $this->assertSame('update', $log->event);
        $this->assertStringContainsString('Portal Internal', $log->new_values);
        $this->assertStringContainsString('Low-Code Platform', $log->old_values);
    }

    // ---------------- validasi ----------------

    #[Test]
    public function pilihan_di_luar_daftar_ditolak(): void
    {
        $this->actingAs($this->pengelola)->put(
            route('settings.update'),
            $this->isian(['values' => ['sidebar_skin' => 'sidebar-jahat']])
        )->assertSessionHasErrors('values.sidebar_skin');

        $this->assertSame('sidebar-dark-primary', setting('sidebar_skin'));
    }

    #[Test]
    public function kiriman_yang_bukan_larik_ditolak_bukan_dijatuhkan(): void
    {
        $this->actingAs($this->pengelola)
            ->put(route('settings.update'), ['values' => 'bukan larik'])
            ->assertSessionHasErrors('values');

        $this->assertSame('Low-Code Platform', setting('app_name'));
    }

    #[Test]
    public function jumlah_baris_nol_ditolak(): void
    {
        $this->actingAs($this->pengelola)->put(
            route('settings.update'),
            $this->isian(['values' => ['per_page' => '0']])
        )->assertSessionHasErrors('values.per_page');
    }

    // ---------------- berkas ----------------

    #[Test]
    public function logo_tersimpan_di_disk_publik(): void
    {
        $this->actingAs($this->pengelola)->put(route('settings.update'), $this->isian([
            'files' => ['app_logo' => UploadedFile::fake()->image('logo.png')],
        ]));

        $path = Setting::where('key_name', 'app_logo')->value('value');

        $this->assertNotNull($path);
        $this->assertStringStartsWith('settings/app_logo-', $path);
        Storage::disk('public')->assertExists($path);
    }

    #[Test]
    public function logo_lama_dibuang_saat_diganti(): void
    {
        $this->actingAs($this->pengelola)->put(route('settings.update'), $this->isian([
            'files' => ['app_logo' => UploadedFile::fake()->image('lama.png')],
        ]));
        $lama = Setting::where('key_name', 'app_logo')->value('value');

        $this->actingAs($this->pengelola)->put(route('settings.update'), $this->isian([
            'files' => ['app_logo' => UploadedFile::fake()->image('baru.png')],
        ]));
        $baru = Setting::where('key_name', 'app_logo')->value('value');

        $this->assertNotSame($lama, $baru);
        Storage::disk('public')->assertMissing($lama);
        Storage::disk('public')->assertExists($baru);
    }

    #[Test]
    public function logo_bisa_dihapus_tanpa_mengunggah_pengganti(): void
    {
        $this->actingAs($this->pengelola)->put(route('settings.update'), $this->isian([
            'files' => ['app_logo' => UploadedFile::fake()->image('logo.png')],
        ]));
        $path = Setting::where('key_name', 'app_logo')->value('value');

        $this->actingAs($this->pengelola)->put(route('settings.update'), $this->isian([
            'remove' => ['app_logo' => '1'],
        ]));

        $this->assertNull(Setting::where('key_name', 'app_logo')->value('value'));
        Storage::disk('public')->assertMissing($path);
    }

    #[Test]
    public function berkas_bukan_gambar_ditolak(): void
    {
        $this->actingAs($this->pengelola)->put(route('settings.update'), $this->isian([
            'files' => ['app_logo' => UploadedFile::fake()->create('skrip.php', 4)],
        ]))->assertSessionHasErrors('files.app_logo');

        $this->assertNull(Setting::where('key_name', 'app_logo')->value('value'));
    }
}
