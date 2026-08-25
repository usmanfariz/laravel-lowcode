<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Services\Form\FormActionRenderer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\MetadataTestCase;

class FormActionRendererTest extends MetadataTestCase
{
    private Form $form;

    protected function setUp(): void
    {
        parent::setUp();
        $this->form = $this->makeForm();

        Route::get('uji/tanpa-parameter', fn () => 'ok')->name('uji.tanpa');
        Route::get('uji/{id}/dengan-parameter', fn () => 'ok')->name('uji.dengan');

        // Route yang didaftarkan setelah aplikasi boot belum masuk tabel
        // pencarian nama, sehingga Route::has() mengembalikan false.
        Route::getRoutes()->refreshNameLookups();
    }

    private function seedAction(array $overrides = []): void
    {
        DB::table('form_actions')->insert(array_merge([
            'form_id' => $this->form->id, 'code' => 'aksi', 'label' => 'Aksi',
            'icon' => null, 'position' => 'row', 'action_type' => 'route',
            'target_value' => 'uji.tanpa', 'http_method' => 'GET',
            'permission_code' => null, 'confirm_message' => null,
            'show_condition' => null, 'css_class' => 'btn-default',
            'order_no' => 1, 'is_active' => true,
        ], $overrides));

        $this->form = $this->form->fresh(['actions']);
    }

    private function render(string $position = 'row'): array
    {
        return app(FormActionRenderer::class)->forPosition($this->form, $position, $this->admin);
    }

    #[Test]
    public function route_tanpa_parameter_tidak_kebagian_penanda_id(): void
    {
        $this->seedAction(['target_value' => 'uji.tanpa']);

        // Penanda yang ditempel ke route tanpa parameter berubah jadi query
        // string (`/uji/tanpa-parameter?__ID__`) — URL-nya jadi menyesatkan.
        $this->assertSame(url('uji/tanpa-parameter'), $this->render()[0]['url']);
    }

    #[Test]
    public function route_berparameter_menerima_penanda_id(): void
    {
        $this->seedAction(['target_value' => 'uji.dengan']);

        $this->assertStringContainsString('__ID__', $this->render()[0]['url']);
        $this->assertStringNotContainsString('?', $this->render()[0]['url']);
    }

    #[Test]
    public function route_yang_belum_terdaftar_jadi_pagar(): void
    {
        $this->seedAction(['target_value' => 'route.belum.ada']);

        // Satu aksi salah ketik tidak boleh mematikan seluruh halaman list.
        $this->assertSame('#', $this->render()[0]['url']);
    }

    #[Test]
    public function url_langsung_dipakai_apa_adanya(): void
    {
        $this->seedAction(['action_type' => 'url', 'target_value' => 'https://contoh.test/x']);

        $this->assertSame('https://contoh.test/x', $this->render()[0]['url']);
    }

    #[Test]
    public function aksi_tanpa_izin_tidak_sampai_ke_klien(): void
    {
        // item.edit dimiliki admin (lihat MetadataTestCase) tapi tidak
        // diberikan ke pengguna kedua.
        $this->seedAction(['permission_code' => 'item.edit']);

        $tanpaIzin = $this->makeUser('polos', ['item.view']);

        $this->assertCount(0,
            app(FormActionRenderer::class)->forPosition($this->form, 'row', $tanpaIzin),
            'aksi yang izinnya tidak dimiliki tetap dikirim ke klien');
        $this->assertCount(1, $this->render(), 'aksi hilang dari user yang berhak');
    }

    #[Test]
    public function kolom_kondisi_dikumpulkan_untuk_endpoint_data(): void
    {
        $this->seedAction(['show_condition' => json_encode(['name' => 'X'])]);
        $this->seedAction(['code' => 'aksi2', 'show_condition' => json_encode(['code' => 'Y'])]);

        $columns = app(FormActionRenderer::class)->conditionColumns($this->form);

        sort($columns);
        $this->assertSame(['code', 'name'], $columns);
    }

    #[Test]
    public function aksi_dipisah_menurut_posisinya(): void
    {
        $this->seedAction(['code' => 'baris', 'position' => 'row']);
        $this->seedAction(['code' => 'atas', 'position' => 'toolbar']);
        $this->seedAction(['code' => 'massal', 'position' => 'bulk',
            'http_method' => 'POST', 'confirm_message' => 'Yakin?']);

        $this->assertSame(['baris'], array_column($this->render('row'), 'code'));
        $this->assertSame(['atas'], array_column($this->render('toolbar'), 'code'));
        $this->assertSame(['massal'], array_column($this->render('bulk'), 'code'));
    }
}
