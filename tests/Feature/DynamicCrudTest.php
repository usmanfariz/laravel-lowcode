<?php

namespace Tests\Feature;

use App\Models\Form;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\MetadataTestCase;

class DynamicCrudTest extends MetadataTestCase
{
    private Form $form;

    protected function setUp(): void
    {
        parent::setUp();
        $this->form = $this->makeForm();
    }

    #[Test]
    public function halaman_list_dan_tambah_dapat_dibuka(): void
    {
        $this->actingAs($this->admin)->get('/forms/item')->assertOk();
        $this->actingAs($this->admin)->get('/forms/item/create')->assertOk();
    }

    #[Test]
    public function menyimpan_baris_mengisi_kolom_audit(): void
    {
        $this->actingAs($this->admin)
            ->post('/forms/item', [
                'code' => 'IT-1', 'name' => 'Barang Satu',
                'category_id' => 1, 'price' => 1500, 'qty' => 3,
            ])
            ->assertRedirect();

        $row = DB::table('t_items')->where('code', 'IT-1')->first();

        $this->assertNotNull($row);
        $this->assertSame('Barang Satu', $row->name);
        $this->assertEquals($this->admin->id, $row->created_by);
        $this->assertEquals($this->admin->id, $row->updated_by);
        $this->assertNotNull($row->created_at);
    }

    #[Test]
    public function aturan_validasi_diturunkan_dari_metadata(): void
    {
        DB::table('t_items')->insert(['code' => 'IT-1', 'name' => 'Ada']);

        $this->actingAs($this->admin)
            ->post('/forms/item', [
                'code' => 'IT-1',          // duplikat -> unique
                'name' => '',              // kosong   -> required
                'category_id' => 999,      // tak ada  -> exists
                'qty' => 'bukan angka',    // -> integer
            ])
            ->assertSessionHasErrors(['code', 'name', 'category_id', 'qty']);

        $this->assertSame(1, DB::table('t_items')->count());
    }

    #[Test]
    public function menghapus_memakai_soft_delete_dan_menyembunyikan_baris(): void
    {
        $id = DB::table('t_items')->insertGetId(['code' => 'IT-1', 'name' => 'Hapus']);

        $this->actingAs($this->admin)->delete("/forms/item/{$id}")->assertRedirect();

        $this->assertNotNull(DB::table('t_items')->find($id), 'baris terhapus fisik padahal soft delete aktif');
        $this->assertNotNull(DB::table('t_items')->find($id)->deleted_at);
        $this->actingAs($this->admin)->get("/forms/item/{$id}/edit")->assertNotFound();
    }

    #[Test]
    public function aksi_tercatat_di_log_aktivitas(): void
    {
        $this->actingAs($this->admin)
            ->post('/forms/item', ['code' => 'IT-9', 'name' => 'Dicatat', 'qty' => 1]);

        $log = DB::table('activity_logs')->latest('id')->first();

        $this->assertSame('create', $log->event);
        $this->assertSame('t_items', $log->table_name);
        $this->assertEquals($this->admin->id, $log->user_id);
    }

    #[Test]
    public function perubahan_bersamaan_ditolak_bukan_ditimpa(): void
    {
        $id = DB::table('t_items')->insertGetId([
            'code' => 'IT-1', 'name' => 'Awal',
            'created_at' => now()->subHour(), 'updated_at' => now()->subHour(),
        ]);

        $versiLama = DB::table('t_items')->where('id', $id)->value('updated_at');

        // Orang lain menyimpan lebih dulu.
        DB::table('t_items')->where('id', $id)->update([
            'name' => 'Diubah orang lain', 'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->put("/forms/item/{$id}", [
                'code' => 'IT-1', 'name' => 'Perubahan saya', '__version' => $versiLama,
            ])
            ->assertSessionHas('error');

        $this->assertSame(
            'Diubah orang lain',
            DB::table('t_items')->where('id', $id)->value('name'),
            'pekerjaan orang lain tertimpa'
        );
    }

    #[Test]
    public function menyimpan_dengan_versi_terbaru_tetap_berhasil(): void
    {
        $id = DB::table('t_items')->insertGetId([
            'code' => 'IT-1', 'name' => 'Awal',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $versi = DB::table('t_items')->where('id', $id)->value('updated_at');

        $this->actingAs($this->admin)
            ->put("/forms/item/{$id}", [
                'code' => 'IT-1', 'name' => 'Perubahan saya', '__version' => $versi,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Perubahan saya', DB::table('t_items')->where('id', $id)->value('name'));
    }

    #[Test]
    public function form_edit_membawa_penanda_versi(): void
    {
        $id = DB::table('t_items')->insertGetId([
            'code' => 'IT-1', 'name' => 'Ada', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get("/forms/item/{$id}/edit")
            ->assertOk()
            ->assertSee('name="__version"', false);
    }

    // ---------------- scope per baris ----------------

    #[Test]
    public function pengguna_bercakupan_cabang_hanya_melihat_barisnya(): void
    {
        $this->form->update(['scope_column' => 'branch_code']);

        DB::table('t_items')->insert([
            ['code' => 'A', 'name' => 'Cabang 1', 'branch_code' => 'CAB-01'],
            ['code' => 'B', 'name' => 'Cabang 2', 'branch_code' => 'CAB-02'],
        ]);

        $staff = $this->makeUser('staff', ['item.view', 'item.edit'], 'branch', 'CAB-01');

        $data = $this->actingAs($staff)->getJson('/forms/item/data?draw=1&start=0&length=10')->json();

        $this->assertSame(1, $data['recordsTotal']);
        $this->assertSame('A', $data['data'][0]['c0']);
    }

    #[Test]
    public function baris_di_luar_cakupan_tidak_bisa_dibuka_lewat_url(): void
    {
        $this->form->update(['scope_column' => 'branch_code']);

        $lain = DB::table('t_items')->insertGetId([
            'code' => 'B', 'name' => 'Unit Lain', 'branch_code' => 'CAB-02',
        ]);

        $staff = $this->makeUser('staff', ['item.view', 'item.edit'], 'branch', 'CAB-01');

        // 404, bukan 403: 403 mengakui barisnya ada.
        $this->actingAs($staff)->get("/forms/item/{$lain}/edit")->assertNotFound();
    }

    #[Test]
    public function baris_baru_memakai_cakupan_pembuatnya(): void
    {
        $this->form->update(['scope_column' => 'branch_code']);
        $staff = $this->makeUser('staff', ['item.view', 'item.create'], 'branch', 'CAB-01');

        $this->actingAs($staff)->post('/forms/item', [
            'code' => 'IT-5', 'name' => 'Milik Cabang',
            // Mencoba menitipkan ke unit lain lewat request.
            'branch_code' => 'CAB-99',
        ]);

        $this->assertSame('CAB-01', DB::table('t_items')->where('code', 'IT-5')->value('branch_code'));
    }

    #[Test]
    public function cakupan_tanpa_scope_value_menutup_semua_baris(): void
    {
        $this->form->update(['scope_column' => 'branch_code']);
        DB::table('t_items')->insert(['code' => 'A', 'name' => 'Ada', 'branch_code' => 'CAB-01']);

        // scope_value sengaja null: salah konfigurasi harus gagal ke arah aman.
        $staff = $this->makeUser('staff', ['item.view'], 'branch', null);

        $data = $this->actingAs($staff)->getJson('/forms/item/data?draw=1&start=0&length=10')->json();

        $this->assertSame(0, $data['recordsTotal']);
    }

    // ---------------- berkas unggahan ----------------

    #[Test]
    public function berkas_dibiarkan_saat_soft_delete(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Storage::disk('public')->put('u/berkas.png', 'x');

        $this->tambahFieldBerkas();
        $id = DB::table('t_items')->insertGetId([
            'code' => 'IT-1', 'name' => 'Ada', 'secret' => 'u/berkas.png',
        ]);

        $this->actingAs($this->admin)->delete("/forms/item/{$id}");

        // Baris masih bisa dikembalikan, jadi berkasnya harus ikut utuh.
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists('u/berkas.png');
    }

    #[Test]
    public function berkas_ikut_dibuang_saat_dihapus_permanen(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Storage::disk('public')->put('u/berkas.png', 'x');

        $this->form->update(['use_soft_delete' => false]);
        $this->tambahFieldBerkas();

        $id = DB::table('t_items')->insertGetId([
            'code' => 'IT-1', 'name' => 'Ada', 'secret' => 'u/berkas.png',
        ]);

        $this->actingAs($this->admin)->delete("/forms/item/{$id}");

        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing('u/berkas.png');
    }

    /** Field bertipe berkas yang menunjuk kolom teks bebas. */
    private function tambahFieldBerkas(): void
    {
        // 'secret' dilepas dari daftar blokir agar bisa dipakai sebagai kolom berkas.
        \App\Models\DataSource::where('table_name', 't_items')->update(['blocked_columns' => null]);
        app(\App\Services\DataSourceResolver::class)->flushColumns('t_items');

        DB::table('form_fields')->insert([
            'form_id' => $this->form->id, 'form_detail_id' => null,
            'field_name' => 'secret', 'label' => 'Lampiran', 'input_type' => 'image',
            'is_required' => false, 'is_readonly' => false, 'is_unique' => false,
            'width' => 6, 'order_no' => 99, 'data_source_type' => 'none',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->form = $this->form->fresh(['fields', 'details']);
    }

    // ---------------- izin ----------------

    #[Test]
    public function izin_ditegakkan_per_aksi(): void
    {
        $pembaca = $this->makeUser('pembaca', ['item.view']);

        $this->actingAs($pembaca)->get('/forms/item')->assertOk();
        $this->actingAs($pembaca)->get('/forms/item/create')->assertForbidden();
        $this->actingAs($pembaca)->post('/forms/item', ['code' => 'X', 'name' => 'X'])->assertForbidden();
    }

    #[Test]
    public function form_yang_melarang_hapus_menolak_penghapusan(): void
    {
        $this->form->update(['allow_delete' => false]);
        $id = DB::table('t_items')->insertGetId(['code' => 'IT-1', 'name' => 'Tetap']);

        $this->actingAs($this->admin)->delete("/forms/item/{$id}")->assertForbidden();
        $this->assertNull(DB::table('t_items')->find($id)->deleted_at);
    }

    #[Test]
    public function tamu_diarahkan_ke_login(): void
    {
        $this->get('/forms/item')->assertRedirect('/login');
    }

    #[Test]
    public function form_tak_dikenal_menghasilkan_404(): void
    {
        $this->actingAs($this->admin)->get('/forms/tidak_ada')->assertNotFound();
    }
}
