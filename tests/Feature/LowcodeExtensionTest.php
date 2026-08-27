<?php

namespace Tests\Feature;

use App\Contracts\FormActionHandler;
use App\Contracts\FormHook;
use App\Exceptions\ActionFailedException;
use App\Models\Form;
use App\Models\User;
use App\Services\Form\FormRepository;
use App\Services\Form\LowcodeRegistry;
use App\Support\FormHookDefaults;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\MetadataTestCase;

class LowcodeExtensionTest extends MetadataTestCase
{
    private Form $form;

    protected function setUp(): void
    {
        parent::setUp();
        $this->form = $this->makeForm();
        Jejak::$dipanggil = [];
    }

    private function pasangHook(string ...$classes): void
    {
        config(['lowcode.hooks' => [$this->form->code => $classes]]);
    }

    private function simpan(array $input = []): mixed
    {
        return app(FormRepository::class)->create($this->form, array_merge([
            'code' => 'A1', 'name' => 'Item A', 'price' => 1000, 'qty' => 2,
        ], $input), $this->admin);
    }

    // ---------------- Registry ----------------

    #[Test]
    public function kunci_handler_tak_terdaftar_ditolak(): void
    {
        config(['lowcode.handlers' => []]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Handler 'tidak_ada' tidak terdaftar");

        app(LowcodeRegistry::class)->handler('tidak_ada');
    }

    #[Test]
    public function class_yang_tidak_memenuhi_kontrak_ditolak(): void
    {
        // Ini pertahanan intinya: metadata tidak boleh bisa menjalankan
        // class sembarangan, sekalipun kuncinya terdaftar.
        config(['lowcode.handlers' => ['nakal' => BukanHandler::class]]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tidak mengimplementasikan');

        app(LowcodeRegistry::class)->handler('nakal');
    }

    // ---------------- Hook simpan ----------------

    #[Test]
    public function before_save_dapat_mengubah_nilai_yang_disimpan(): void
    {
        $this->pasangHook(HookPenomoran::class);

        $id = $this->simpan(['code' => 'akan-diganti']);

        $this->assertSame('AUTO-001', DB::table('t_items')->find($id)->code);
    }

    #[Test]
    public function after_save_melihat_baris_yang_sudah_tersimpan(): void
    {
        $this->pasangHook(HookPencatat::class);

        $id = $this->simpan();

        $this->assertSame(['afterSave:'.$id], Jejak::$dipanggil);
    }

    #[Test]
    public function hook_yang_gagal_membatalkan_seluruh_penyimpanan(): void
    {
        $this->pasangHook(HookPenolak::class);

        $sebelum = DB::table('t_items')->count();

        try {
            $this->simpan();
            $this->fail('Penyimpanan seharusnya dibatalkan.');
        } catch (ActionFailedException $e) {
            $this->assertSame('Stok tidak cukup.', $e->getMessage());
        }

        // Inti dari hook berada di dalam transaksi: barisnya tidak boleh
        // tertinggal tersimpan saat logika bisnisnya menolak.
        $this->assertSame($sebelum, DB::table('t_items')->count());
    }

    #[Test]
    public function before_delete_dapat_menolak_penghapusan(): void
    {
        $id = $this->simpan();
        $this->pasangHook(HookPenolakHapus::class);

        try {
            app(FormRepository::class)->delete($this->form, $id, $this->admin);
            $this->fail('Penghapusan seharusnya ditolak.');
        } catch (ActionFailedException) {
            // memang diharapkan
        }

        $baris = DB::table('t_items')->find($id);

        $this->assertNotNull($baris, 'baris harus masih ada');
        $this->assertNull($baris->deleted_at, 'baris tidak boleh ikut ter-soft-delete');
    }

    #[Test]
    public function tanpa_hook_terpasang_penyimpanan_berjalan_seperti_biasa(): void
    {
        config(['lowcode.hooks' => []]);

        $id = $this->simpan();

        $this->assertSame('A1', DB::table('t_items')->find($id)->code);
        $this->assertSame([], Jejak::$dipanggil);
    }


    #[Test]
    public function handler_yang_class_nya_bermasalah_dilaporkan_bukan_disembunyikan(): void
    {
        config(['lowcode.handlers' => [
            'baik' => HandlerPosting::class,
            'salah_ketik' => 'App\\Tidak\\Ada',
            'bukan_handler' => BukanHandler::class,
        ]]);

        $registry = app(LowcodeRegistry::class);

        $this->assertSame(['baik'], array_keys($registry->handlers()));

        $rusak = $registry->invalidHandlers();
        $this->assertSame(['salah_ketik', 'bukan_handler'], array_keys($rusak));
        $this->assertStringContainsString('tidak ditemukan', $rusak['salah_ketik']);
        $this->assertStringContainsString('tidak mengimplementasikan', $rusak['bukan_handler']);
    }

    // ---------------- Endpoint aksi ----------------

    private function seedAksi(array $overrides = []): void
    {
        DB::table('form_actions')->insert(array_merge([
            'form_id' => $this->form->id, 'code' => 'posting', 'label' => 'Posting',
            'icon' => null, 'position' => 'row', 'action_type' => 'handler',
            'target_value' => 'posting', 'http_method' => 'POST',
            'permission_code' => null, 'confirm_message' => 'Yakin?',
            'show_condition' => null, 'css_class' => 'btn btn-sm btn-primary',
            'order_no' => 1, 'is_active' => true,
        ], $overrides));

        config(['lowcode.handlers' => ['posting' => HandlerPosting::class]]);
    }

    #[Test]
    public function endpoint_menjalankan_handler_dan_mengembalikan_pesannya(): void
    {
        $id = $this->simpan();
        $this->seedAksi();

        $this->actingAs($this->admin)
            ->postJson("/forms/{$this->form->code}/action/posting", ['ids' => [$id]])
            ->assertOk()
            ->assertJson(['message' => "1 baris diposting."]);
    }

    #[Test]
    public function endpoint_menolak_tanpa_izin(): void
    {
        $id = $this->simpan();
        $this->seedAksi(['permission_code' => 'item.posting']);

        $biasa = $this->makeUser('biasa', ['item.view']);

        $this->actingAs($biasa)
            ->postJson("/forms/{$this->form->code}/action/posting", ['ids' => [$id]])
            ->assertForbidden();
    }

    #[Test]
    public function endpoint_menolak_aksi_bukan_handler(): void
    {
        $this->seedAksi(['action_type' => 'ajax', 'target_value' => '/apa-saja']);

        $this->actingAs($this->admin)
            ->postJson("/forms/{$this->form->code}/action/posting", ['ids' => ['1']])
            ->assertNotFound();
    }

    #[Test]
    public function kegagalan_handler_membatalkan_perubahannya(): void
    {
        $id = $this->simpan();
        $this->seedAksi(['target_value' => 'gagal']);
        config(['lowcode.handlers' => ['gagal' => HandlerGagal::class]]);

        $this->actingAs($this->admin)
            ->postJson("/forms/{$this->form->code}/action/gagal", ['ids' => [$id]])
            ->assertNotFound(); // kode aksinya 'posting', bukan 'gagal'

        // Panggil lewat kode aksi yang benar
        DB::table('form_actions')->where('form_id', $this->form->id)->update(['code' => 'gagal']);

        $this->actingAs($this->admin)
            ->postJson("/forms/{$this->form->code}/action/gagal", ['ids' => [$id]])
            ->assertStatus(422)
            ->assertJson(['message' => 'Sudah pernah diposting.']);

        // Handler sempat menaikkan qty sebelum melempar; transaksi harus
        // mengembalikannya.
        $this->assertSame(2, (int) DB::table('t_items')->find($id)->qty);
    }

    #[Test]
    public function aksi_per_baris_tanpa_id_ditolak(): void
    {
        $this->seedAksi();

        $this->actingAs($this->admin)
            ->postJson("/forms/{$this->form->code}/action/posting", ['ids' => []])
            ->assertStatus(422);
    }
}

// ---------------- Kelas bantu ----------------

class Jejak
{
    /** @var array<int, string> */
    public static array $dipanggil = [];
}

class BukanHandler {}

class HookPenomoran implements FormHook
{
    use FormHookDefaults;

    public function beforeSave(Form $form, array $values, ?array $before, User $user): array
    {
        $values['code'] = 'AUTO-001';

        return $values;
    }
}

class HookPencatat implements FormHook
{
    use FormHookDefaults;

    public function afterSave(Form $form, mixed $id, array $values, ?array $before, User $user): void
    {
        Jejak::$dipanggil[] = 'afterSave:'.$id;
    }
}

class HookPenolak implements FormHook
{
    use FormHookDefaults;

    public function afterSave(Form $form, mixed $id, array $values, ?array $before, User $user): void
    {
        throw new ActionFailedException('Stok tidak cukup.');
    }
}

class HookPenolakHapus implements FormHook
{
    use FormHookDefaults;

    public function beforeDelete(Form $form, mixed $id, array $before, User $user): void
    {
        throw new ActionFailedException('Sudah diposting, tidak boleh dihapus.');
    }
}

class HandlerPosting implements FormActionHandler
{
    public function handle(Form $form, array $ids, User $user): string
    {
        return count($ids).' baris diposting.';
    }
}

class HandlerGagal implements FormActionHandler
{
    public function handle(Form $form, array $ids, User $user): string
    {
        DB::table('t_items')->whereIn('id', $ids)->increment('qty', 5);

        throw new ActionFailedException('Sudah pernah diposting.');
    }
}
