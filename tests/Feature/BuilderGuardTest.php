<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Services\Form\FormBuilderService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\MetadataTestCase;

/**
 * Builder menerima masukan dari admin dan menuliskannya ke metadata yang
 * nanti dipercaya engine. Penjagaannya diuji di sini.
 */
class BuilderGuardTest extends MetadataTestCase
{
    private Form $form;

    protected function setUp(): void
    {
        parent::setUp();
        $this->form = $this->makeForm();
    }

    private function fieldPayload(array $overrides = []): array
    {
        return array_merge([
            'field_name' => 'name', 'label' => 'Nama', 'input_type' => 'text',
            'width' => 6, 'order_no' => 9, 'data_source_type' => 'none',
        ], $overrides);
    }

    #[Test]
    public function field_harus_menunjuk_kolom_yang_benar_ada(): void
    {
        $this->actingAs($this->admin)
            ->post("/builder/forms/{$this->form->id}/fields", $this->fieldPayload([
                'field_name' => 'kolom_hantu',
            ]))
            ->assertSessionHasErrors('field_name');

        $this->assertDatabaseMissing('form_fields', ['field_name' => 'kolom_hantu']);
    }

    #[Test]
    public function field_tidak_boleh_menunjuk_kolom_yang_diblokir(): void
    {
        $this->actingAs($this->admin)
            ->post("/builder/forms/{$this->form->id}/fields", $this->fieldPayload([
                'field_name' => 'secret',
            ]))
            ->assertSessionHasErrors('field_name');
    }

    #[Test]
    public function sumber_data_tabel_wajib_lolos_whitelist(): void
    {
        $this->actingAs($this->admin)
            ->post("/builder/forms/{$this->form->id}/fields", $this->fieldPayload([
                'field_name' => 'qty', 'input_type' => 'select2',
                'data_source_type' => 'table', 'data_source' => 't_hidden',
                'value_field' => 'id', 'label_field' => 'name',
            ]))
            ->assertSessionHasErrors('data_source');
    }

    #[Test]
    public function nama_field_harus_unik_dalam_satu_form(): void
    {
        // 'code' sudah dipakai oleh field bawaan makeForm().
        $this->actingAs($this->admin)
            ->post("/builder/forms/{$this->form->id}/fields", $this->fieldPayload([
                'field_name' => 'code',
            ]))
            ->assertSessionHasErrors('field_name');
    }

    #[Test]
    public function kolom_ekspresi_butuh_izin_raw_query(): void
    {
        $biasa = $this->makeUser('biasa', ['system.builder.form']);

        $this->actingAs($biasa)
            ->post("/builder/forms/{$this->form->id}/columns", [
                'label' => 'Nilai', 'source_type' => 'expression',
                'expression' => 'price * qty', 'format' => 'currency',
                'align' => 'right', 'order_no' => 1,
            ])
            ->assertSessionHasErrors('expression');
    }

    #[Test]
    public function kolom_ekspresi_berbahaya_ditolak_walau_punya_izin(): void
    {
        $this->actingAs($this->admin)
            ->post("/builder/forms/{$this->form->id}/columns", [
                'label' => 'Bocor', 'source_type' => 'expression',
                'expression' => '(SELECT secret FROM t_items)', 'format' => 'text',
                'align' => 'left', 'order_no' => 1,
            ])
            ->assertSessionHasErrors('expression');

        $this->assertSame(0, DB::table('form_list_columns')->count());
    }

    #[Test]
    public function kolom_ekspresi_yang_wajar_diterima(): void
    {
        $this->actingAs($this->admin)
            ->post("/builder/forms/{$this->form->id}/columns", [
                'label' => 'Nilai', 'source_type' => 'expression',
                'expression' => 'price * qty', 'format' => 'currency',
                'align' => 'right', 'order_no' => 1, 'is_visible' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('form_list_columns', ['expression' => 'price * qty']);
    }

    #[Test]
    public function urutan_hanya_menyentuh_field_milik_form_ini(): void
    {
        $lain = $this->makeForm(['code' => 'lain', 'permission_prefix' => 'lain']);
        $fieldLain = DB::table('form_fields')->where('form_id', $lain->id)->first();
        $sebelum = $fieldLain->order_no;

        $this->actingAs($this->admin)
            ->post("/builder/forms/{$this->form->id}/fields/reorder", [
                'order' => [$fieldLain->id],
            ])
            ->assertOk();

        $this->assertSame(
            $sebelum,
            DB::table('form_fields')->where('id', $fieldLain->id)->value('order_no'),
            'field milik form lain ikut tergeser'
        );
    }

    #[Test]
    public function kanvas_membatasi_lebar_ke_rentang_yang_sah(): void
    {
        $field = DB::table('form_fields')->where('form_id', $this->form->id)->first();

        $this->actingAs($this->admin)
            ->post("/builder/forms/{$this->form->id}/layout", [
                'items' => [['id' => $field->id, 'width' => 99]],
            ])
            ->assertOk();

        $this->assertSame(12, (int) DB::table('form_fields')->where('id', $field->id)->value('width'));
    }

    #[Test]
    public function tabel_detail_wajib_boleh_ditulis(): void
    {
        $this->actingAs($this->admin)
            ->post("/builder/forms/{$this->form->id}/details", [
                'code' => 'item', 'title' => 'Baris', 'table_name' => 't_categories',
                'primary_key' => 'id', 'foreign_key' => 'id', 'order_no' => 1,
            ])
            ->assertSessionHasErrors('table_name');
    }

    #[Test]
    public function aksi_non_get_wajib_punya_konfirmasi(): void
    {
        $this->actingAs($this->admin)
            ->post("/builder/forms/{$this->form->id}/actions", [
                'code' => 'setuju', 'label' => 'Setujui', 'position' => 'row',
                'action_type' => 'ajax', 'target_value' => '/x',
                'http_method' => 'POST', 'order_no' => 1,
            ])
            ->assertSessionHasErrors('confirm_message');
    }

    #[Test]
    public function aksi_massal_tidak_boleh_get(): void
    {
        $this->actingAs($this->admin)
            ->post("/builder/forms/{$this->form->id}/actions", [
                'code' => 'massal', 'label' => 'Massal', 'position' => 'bulk',
                'action_type' => 'url', 'target_value' => '/x',
                'http_method' => 'GET', 'order_no' => 1,
            ])
            ->assertSessionHasErrors('http_method');
    }

    #[Test]
    public function builder_butuh_izin_khusus(): void
    {
        $biasa = $this->makeUser('biasa', ['item.view']);

        $this->actingAs($biasa)->get('/builder/forms')->assertForbidden();
        $this->actingAs($biasa)->get('/builder/reports')->assertForbidden();
        $this->actingAs($biasa)->get('/data-sources')->assertForbidden();
        $this->actingAs($biasa)->get('/activity-logs')->assertForbidden();
    }

    // ---------------- versioning ----------------

    #[Test]
    public function perubahan_merekam_versi_sebelum_disimpan(): void
    {
        $this->actingAs($this->admin)
            ->post("/builder/forms/{$this->form->id}/fields", $this->fieldPayload([
                'field_name' => 'branch_code', 'label' => 'Cabang',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('form_versions', [
            'form_id' => $this->form->id, 'version' => 1,
        ]);
    }

    #[Test]
    public function memulihkan_versi_mengembalikan_definisi_utuh(): void
    {
        $awal = DB::table('form_fields')->where('form_id', $this->form->id)->count();

        app(FormBuilderService::class)->snapshot($this->form, $this->admin, 'awal');

        DB::table('form_fields')->where('form_id', $this->form->id)->delete();
        $this->form->update(['name' => 'Diubah', 'per_page' => 99]);

        app(FormBuilderService::class)->restore($this->form->fresh(), 1, $this->admin);

        $this->assertSame($awal, DB::table('form_fields')->where('form_id', $this->form->id)->count());
        $this->assertSame('Item', $this->form->fresh()->name);
        $this->assertSame(25, $this->form->fresh()->per_page);
    }

    #[Test]
    public function pemulihan_ikut_direkam_agar_bisa_dibatalkan(): void
    {
        app(FormBuilderService::class)->snapshot($this->form, $this->admin, 'awal');
        app(FormBuilderService::class)->restore($this->form, 1, $this->admin);

        $this->assertSame(2, DB::table('form_versions')->where('form_id', $this->form->id)->count());
    }
}
