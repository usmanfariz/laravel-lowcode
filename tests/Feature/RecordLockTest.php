<?php

namespace Tests\Feature;

use App\Exceptions\RecordLockedException;
use App\Models\Form;
use App\Services\Form\FormRepository;
use App\Support\ConditionInput;
use App\Support\RowCondition;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\MetadataTestCase;

class RecordLockTest extends MetadataTestCase
{
    private Form $form;

    protected function setUp(): void
    {
        parent::setUp();
        $this->form = $this->makeForm();
    }

    private function buat(array $input = []): mixed
    {
        return app(FormRepository::class)->create($this->form, array_merge([
            'code' => 'A1', 'name' => 'Item A', 'price' => 1000, 'qty' => 2,
        ], $input), $this->admin);
    }

    private function kunci(?array $condition, ?string $pesan = null): void
    {
        $this->form->update(['lock_condition' => $condition, 'lock_message' => $pesan]);
        $this->form->refresh();
    }

    // ---------------- Kasus paling berbahaya ----------------

    #[Test]
    public function tanpa_kondisi_tidak_ada_yang_terkunci(): void
    {
        // Kondisi kosong "cocok" dengan baris apa pun. Kalau penjaga tidak
        // memeriksanya lebih dulu, form yang tidak menyetel penguncian akan
        // mengunci SELURUH barisnya.
        $id = $this->buat();
        $this->kunci(null);

        app(FormRepository::class)->update($this->form, $id, ['name' => 'Berubah'], $this->admin);

        $this->assertSame('Berubah', DB::table('t_items')->find($id)->name);
    }

    #[Test]
    public function kondisi_larik_kosong_juga_tidak_mengunci(): void
    {
        $id = $this->buat();
        $this->kunci([]);

        app(FormRepository::class)->update($this->form, $id, ['name' => 'Berubah'], $this->admin);

        $this->assertSame('Berubah', DB::table('t_items')->find($id)->name);
    }

    // ---------------- Penguncian ----------------

    #[Test]
    public function baris_yang_cocok_tidak_bisa_diubah(): void
    {
        $id = $this->buat(['code' => 'TERKUNCI']);
        $this->kunci(['code' => 'TERKUNCI']);

        $this->expectException(RecordLockedException::class);

        app(FormRepository::class)->update($this->form, $id, ['name' => 'Berubah'], $this->admin);
    }

    #[Test]
    public function baris_yang_tidak_cocok_tetap_bisa_diubah(): void
    {
        $id = $this->buat(['code' => 'BEBAS']);
        $this->kunci(['code' => 'TERKUNCI']);

        app(FormRepository::class)->update($this->form, $id, ['name' => 'Berubah'], $this->admin);

        $this->assertSame('Berubah', DB::table('t_items')->find($id)->name);
    }

    #[Test]
    public function baris_yang_cocok_tidak_bisa_dihapus(): void
    {
        $id = $this->buat(['code' => 'TERKUNCI']);
        $this->kunci(['code' => 'TERKUNCI']);

        try {
            app(FormRepository::class)->delete($this->form, $id, $this->admin);
            $this->fail('Penghapusan seharusnya ditolak.');
        } catch (RecordLockedException) {
            // diharapkan
        }

        $this->assertNull(DB::table('t_items')->find($id)->deleted_at);
    }

    #[Test]
    public function kondisi_larik_berarti_salah_satu(): void
    {
        $terkunci = $this->buat(['code' => 'VOID']);
        $bebas = $this->buat(['code' => 'DRAFT']);
        $this->kunci(['code' => ['POSTED', 'VOID']]);

        app(FormRepository::class)->update($this->form, $bebas, ['name' => 'Boleh'], $this->admin);
        $this->assertSame('Boleh', DB::table('t_items')->find($bebas)->name);

        $this->expectException(RecordLockedException::class);
        app(FormRepository::class)->update($this->form, $terkunci, ['name' => 'X'], $this->admin);
    }

    #[Test]
    public function pesan_khusus_dipakai_bila_diisi(): void
    {
        $id = $this->buat(['code' => 'TERKUNCI']);
        $this->kunci(['code' => 'TERKUNCI'], 'Nota sudah diposting.');

        try {
            app(FormRepository::class)->update($this->form, $id, ['name' => 'X'], $this->admin);
            $this->fail('seharusnya ditolak');
        } catch (RecordLockedException $e) {
            $this->assertSame('Nota sudah diposting.', $e->getMessage());
        }
    }

    #[Test]
    public function penguncian_berlaku_lewat_http_bukan_hanya_repository(): void
    {
        // Menyembunyikan tombol saja tidak cukup: form edit tetap bisa dibuka
        // dan disimpan lewat URL langsung.
        $id = $this->buat(['code' => 'TERKUNCI']);
        $this->kunci(['code' => 'TERKUNCI'], 'Sudah diposting.');

        $this->actingAs($this->admin)
            ->put("/forms/{$this->form->code}/{$id}", ['code' => 'TERKUNCI', 'name' => 'Diretas'])
            ->assertSessionHas('error', 'Sudah diposting.');

        $this->assertSame('Item A', DB::table('t_items')->find($id)->name);

        $this->actingAs($this->admin)
            ->delete("/forms/{$this->form->code}/{$id}")
            ->assertSessionHas('error', 'Sudah diposting.');

        $this->assertNull(DB::table('t_items')->find($id)->deleted_at);
    }


    // ---------------- Editor di builder ----------------

    #[Test]
    public function penguncian_bisa_disetel_dan_dimatikan_lewat_builder(): void
    {
        $dasar = [
            'name' => 'Item', 'primary_key' => 'id', 'type' => 'single',
            'layout_columns' => 2, 'default_order_direction' => 'desc', 'per_page' => 25,
        ];

        $this->actingAs($this->admin)
            ->put("/builder/forms/{$this->form->id}", $dasar + [
                'lock_column' => 'code', 'lock_value' => 'POSTED, VOID',
                'lock_message' => 'Sudah final.',
            ])
            ->assertSessionHasNoErrors();

        $this->form->refresh();
        $this->assertSame(['code' => ['POSTED', 'VOID']], $this->form->lock_condition);
        $this->assertSame('Sudah final.', $this->form->lock_message);

        // Mengosongkan kolomnya harus benar-benar mematikan penguncian.
        $this->actingAs($this->admin)
            ->put("/builder/forms/{$this->form->id}", $dasar + ['lock_column' => '', 'lock_value' => ''])
            ->assertSessionHasNoErrors();

        $this->form->refresh();
        $this->assertNull($this->form->lock_condition);
    }

    #[Test]
    public function kolom_penguncian_yang_diblokir_ditolak(): void
    {
        // 'secret' diblokir di data_sources, jadi tidak pernah ikut terbaca —
        // penguncian yang menunjuk ke sana tidak akan pernah berlaku.
        $this->actingAs($this->admin)
            ->put("/builder/forms/{$this->form->id}", [
                'name' => 'Item', 'primary_key' => 'id', 'type' => 'single',
                'layout_columns' => 2, 'default_order_direction' => 'desc', 'per_page' => 25,
                'lock_column' => 'secret', 'lock_value' => 'x',
            ])
            ->assertSessionHasErrors('lock_column');

        $this->form->refresh();
        $this->assertNull($this->form->lock_condition);
    }

    #[Test]
    public function kondisi_tampil_aksi_bisa_disetel_lewat_builder(): void
    {
        $this->actingAs($this->admin)
            ->post("/builder/forms/{$this->form->id}/actions", [
                'code' => 'posting', 'label' => 'Posting', 'position' => 'row',
                'action_type' => 'url', 'target_value' => '/posting',
                'http_method' => 'POST', 'confirm_message' => 'Yakin?',
                'order_no' => 1, 'is_active' => 1,
                'condition_column' => 'code', 'condition_value' => 'DRAFT',
            ])
            ->assertSessionHasNoErrors();

        $aksi = DB::table('form_actions')->where('form_id', $this->form->id)->first();
        $this->assertSame(['code' => 'DRAFT'], json_decode($aksi->show_condition, true));
    }

    #[Test]
    public function kondisi_tampil_aksi_bisa_dikosongkan(): void
    {
        DB::table('form_actions')->insert([
            'form_id' => $this->form->id, 'code' => 'posting', 'label' => 'Posting',
            'position' => 'row', 'action_type' => 'url', 'target_value' => '/x',
            'http_method' => 'POST', 'confirm_message' => 'Yakin?',
            'show_condition' => json_encode(['code' => 'DRAFT']),
            'css_class' => 'btn btn-sm btn-primary', 'order_no' => 1, 'is_active' => 1,
        ]);
        $aksi = DB::table('form_actions')->where('form_id', $this->form->id)->first();

        $this->actingAs($this->admin)
            ->put("/builder/forms/{$this->form->id}/actions/{$aksi->id}", [
                'code' => 'posting', 'label' => 'Posting', 'position' => 'row',
                'action_type' => 'url', 'target_value' => '/x',
                'http_method' => 'POST', 'confirm_message' => 'Yakin?',
                'order_no' => 1, 'is_active' => 1,
                'condition_column' => '', 'condition_value' => '',
            ])
            ->assertSessionHasNoErrors();

        // Harus NULL sungguhan, bukan string "null".
        $this->assertNull(DB::table('form_actions')->find($aksi->id)->show_condition);
    }

    // ---------------- Perakit kondisi ----------------

    #[Test]
    public function perakit_kondisi_menangani_bentuk_masukan(): void
    {
        $this->assertNull(ConditionInput::build('', 'posted'));
        $this->assertNull(ConditionInput::build('status', ''));
        $this->assertNull(ConditionInput::build('status', ' , '));
        $this->assertSame(['status' => 'posted'], ConditionInput::build('status', 'posted'));
        $this->assertSame(['status' => ['posted', 'void']], ConditionInput::build('status', 'posted, void'));

        $this->assertSame('status', ConditionInput::column(['status' => 'posted']));
        $this->assertSame('posted, void', ConditionInput::value(['status' => ['posted', 'void']]));
    }

    #[Test]
    public function pencocokan_membandingkan_sebagai_teks(): void
    {
        // 1 dari MySQL dan "1" dari metadata harus dianggap sama.
        $this->assertTrue(RowCondition::matches(['aktif' => '1'], ['aktif' => 1]));
        $this->assertTrue(RowCondition::matches(['aktif' => 1], ['aktif' => '1']));
        $this->assertFalse(RowCondition::matches(['aktif' => '1'], ['aktif' => 0]));

        // Semua kunci harus cocok.
        $this->assertTrue(RowCondition::matches(['a' => 'x', 'b' => 'y'], ['a' => 'x', 'b' => 'y']));
        $this->assertFalse(RowCondition::matches(['a' => 'x', 'b' => 'y'], ['a' => 'x', 'b' => 'z']));

        // Kolom yang tidak ada di baris tidak boleh dianggap cocok.
        $this->assertFalse(RowCondition::matches(['hilang' => 'x'], ['a' => 'x']));
    }
}
