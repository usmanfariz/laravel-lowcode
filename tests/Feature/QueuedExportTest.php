<?php

namespace Tests\Feature;

use App\Jobs\GenerateExport;
use App\Models\ExportJob;
use App\Models\Form;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\MetadataTestCase;

class QueuedExportTest extends MetadataTestCase
{
    private Form $form;

    protected function setUp(): void
    {
        parent::setUp();
        $this->form = $this->makeForm();

        DB::table('form_list_columns')->insert([
            ['form_id' => $this->form->id, 'label' => 'Kode', 'source_type' => 'column',
                'column_name' => 'code', 'format' => 'text', 'align' => 'left',
                'is_visible' => true, 'is_searchable' => true, 'is_sortable' => true, 'order_no' => 1],
            ['form_id' => $this->form->id, 'label' => 'Nama', 'source_type' => 'column',
                'column_name' => 'name', 'format' => 'text', 'align' => 'left',
                'is_visible' => true, 'is_searchable' => true, 'is_sortable' => true, 'order_no' => 2],
        ]);
    }

    /** created_at bukan kolom fillable, jadi diundur lewat query langsung. */
    private function backdate(int $id, int $days): void
    {
        DB::table('export_jobs')->where('id', $id)->update([
            'created_at' => now()->subDays($days),
        ]);
    }

    private function seedRows(int $count): void
    {
        $rows = [];
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = ['code' => 'IT-'.$i, 'name' => 'Barang '.$i, 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('t_items')->insert($rows);
    }

    #[Test]
    public function data_sedikit_diunduh_langsung_tanpa_antrean(): void
    {
        Queue::fake();
        $this->seedRows(3);

        $this->actingAs($this->admin)
            ->get('/forms/item/export/csv')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        Queue::assertNothingPushed();
        $this->assertSame(0, ExportJob::count());
    }

    #[Test]
    public function data_melebihi_batas_keras_diantrekan(): void
    {
        Queue::fake();
        $this->seedRows(3);

        // Batas ditekan lewat properti service agar test tidak perlu
        // menyisipkan puluhan ribu baris.
        $this->app->bind(\App\Services\ExportService::class, fn () => new class extends \App\Services\ExportService {
            public function fitsSynchronously(int $rowCount, ?int $threshold): bool
            {
                return $rowCount <= 1;
            }
        });

        $this->actingAs($this->admin)
            ->get('/forms/item/export/xlsx')
            ->assertRedirect(route('exports.index'));

        Queue::assertPushed(GenerateExport::class);

        $job = ExportJob::first();
        $this->assertSame('queued', $job->status);
        $this->assertSame('form', $job->source_type);
        $this->assertSame('item', $job->source_code);
        $this->assertEquals($this->admin->id, $job->user_id);
    }

    #[Test]
    public function cetak_tidak_pernah_diantrekan(): void
    {
        Queue::fake();
        $this->seedRows(3);

        $this->app->bind(\App\Services\ExportService::class, fn () => new class extends \App\Services\ExportService {
            public function fitsSynchronously(int $rowCount, ?int $threshold): bool
            {
                return false;
            }
        });

        // Cetak menghasilkan halaman, bukan berkas — mengantrekannya tidak masuk akal.
        $this->actingAs($this->admin)->get('/forms/item/export/print')->assertOk();

        Queue::assertNothingPushed();
    }

    #[Test]
    public function worker_menghasilkan_berkas_yang_bisa_diunduh(): void
    {
        Storage::fake(GenerateExport::DISK);
        $this->seedRows(3);

        $job = ExportJob::create([
            'user_id' => $this->admin->id, 'source_type' => 'form',
            'source_code' => 'item', 'title' => 'Item', 'format' => 'csv',
            'params' => [], 'status' => 'queued',
        ]);

        (new GenerateExport($job->id))->handle();

        $job->refresh();

        $this->assertSame('done', $job->status);
        $this->assertSame(3, $job->row_count);
        Storage::disk(GenerateExport::DISK)->assertExists($job->file_path);

        $this->actingAs($this->admin)
            ->get(route('exports.download', $job))
            ->assertOk();
    }

    #[Test]
    public function berkas_hanya_bisa_diunduh_pemesannya(): void
    {
        Storage::fake(GenerateExport::DISK);
        $this->seedRows(2);

        $job = ExportJob::create([
            'user_id' => $this->admin->id, 'source_type' => 'form',
            'source_code' => 'item', 'title' => 'Item', 'format' => 'csv',
            'params' => [], 'status' => 'queued',
        ]);
        (new GenerateExport($job->id))->handle();

        // Isi berkas sudah tersaring memakai izin dan cakupan pemesannya,
        // jadi orang lain tidak boleh mengunduhnya.
        $lain = $this->makeUser('lain', ['item.view', 'item.export']);

        $this->actingAs($lain)->get(route('exports.download', $job))->assertForbidden();
    }

    #[Test]
    public function berkas_yang_belum_selesai_tidak_bisa_diunduh(): void
    {
        $job = ExportJob::create([
            'user_id' => $this->admin->id, 'source_type' => 'form',
            'source_code' => 'item', 'title' => 'Item', 'format' => 'csv',
            'params' => [], 'status' => 'queued',
        ]);

        $this->actingAs($this->admin)->get(route('exports.download', $job))->assertNotFound();
    }

    #[Test]
    public function kegagalan_dicatat_bukan_dibiarkan_menggantung(): void
    {
        $job = ExportJob::create([
            'user_id' => $this->admin->id, 'source_type' => 'form',
            'source_code' => 'form_tidak_ada', 'title' => 'Item', 'format' => 'csv',
            'params' => [], 'status' => 'queued',
        ]);

        (new GenerateExport($job->id))->handle();

        $this->assertSame('failed', $job->fresh()->status);
        $this->assertNotNull($job->fresh()->error);
        $this->assertNotNull($job->fresh()->finished_at);
    }

    #[Test]
    public function ekspor_gagal_bisa_diulang(): void
    {
        Queue::fake();

        $job = ExportJob::create([
            'user_id' => $this->admin->id, 'source_type' => 'form',
            'source_code' => 'item', 'title' => 'Item', 'format' => 'csv',
            'params' => [], 'status' => 'failed', 'error' => 'sesuatu',
        ]);

        $this->actingAs($this->admin)->post(route('exports.retry', $job))->assertRedirect();

        $this->assertSame('queued', $job->fresh()->status);
        $this->assertNull($job->fresh()->error);
        Queue::assertPushed(GenerateExport::class);
    }

    #[Test]
    public function perintah_prune_membuang_berkas_dan_barisnya(): void
    {
        Storage::fake(GenerateExport::DISK);
        Storage::disk(GenerateExport::DISK)->put('exports/lama.csv', 'isi');

        $lama = ExportJob::create([
            'user_id' => $this->admin->id, 'source_type' => 'form',
            'source_code' => 'item', 'title' => 'Lama', 'format' => 'csv',
            'params' => [], 'status' => 'done', 'file_path' => 'exports/lama.csv',
        ]);
        $this->backdate($lama->id, 30);

        $baru = ExportJob::create([
            'user_id' => $this->admin->id, 'source_type' => 'form',
            'source_code' => 'item', 'title' => 'Baru', 'format' => 'csv',
            'params' => [], 'status' => 'done',
        ]);

        $this->artisan('exports:prune --days=7')->assertSuccessful();

        $this->assertNull(ExportJob::find($lama->id), 'baris lama masih ada');
        $this->assertNotNull(ExportJob::find($baru->id), 'baris baru ikut terbuang');
        Storage::disk(GenerateExport::DISK)->assertMissing('exports/lama.csv');
    }

    #[Test]
    public function prune_uji_coba_tidak_menghapus_apa_pun(): void
    {
        Storage::fake(GenerateExport::DISK);
        Storage::disk(GenerateExport::DISK)->put('exports/lama.csv', 'isi');

        $job = ExportJob::create([
            'user_id' => $this->admin->id, 'source_type' => 'form',
            'source_code' => 'item', 'title' => 'Lama', 'format' => 'csv',
            'params' => [], 'status' => 'done', 'file_path' => 'exports/lama.csv',
        ]);
        $this->backdate($job->id, 30);

        $this->artisan('exports:prune --days=7 --dry-run')->assertSuccessful();

        $this->assertNotNull(ExportJob::find($job->id));
        Storage::disk(GenerateExport::DISK)->assertExists('exports/lama.csv');
    }

    #[Test]
    public function prune_lewat_halaman_hanya_menyentuh_milik_sendiri(): void
    {
        Storage::fake(GenerateExport::DISK);
        $lain = $this->makeUser('lain', []);

        $punyaSaya = ExportJob::create([
            'user_id' => $this->admin->id, 'source_type' => 'form',
            'source_code' => 'item', 'title' => 'Punya Saya', 'format' => 'csv',
            'params' => [], 'status' => 'done',
        ]);
        $this->backdate($punyaSaya->id, 30);

        $punyaOrangLain = ExportJob::create([
            'user_id' => $lain->id, 'source_type' => 'form',
            'source_code' => 'item', 'title' => 'Punya Orang Lain', 'format' => 'csv',
            'params' => [], 'status' => 'done',
        ]);
        $this->backdate($punyaOrangLain->id, 30);

        $this->actingAs($this->admin)
            ->post(route('exports.prune'), ['days' => 7])
            ->assertRedirect();

        $this->assertNull(ExportJob::find($punyaSaya->id));
        $this->assertNotNull(
            ExportJob::find($punyaOrangLain->id),
            'prune ikut membuang ekspor milik orang lain'
        );
    }

    #[Test]
    public function daftar_hanya_menampilkan_ekspor_milik_sendiri(): void
    {
        $lain = $this->makeUser('lain', []);

        ExportJob::create(['user_id' => $this->admin->id, 'source_type' => 'form',
            'source_code' => 'item', 'title' => 'Punya Admin', 'format' => 'csv',
            'params' => [], 'status' => 'done']);
        ExportJob::create(['user_id' => $lain->id, 'source_type' => 'form',
            'source_code' => 'item', 'title' => 'Punya Orang Lain', 'format' => 'csv',
            'params' => [], 'status' => 'done']);

        $this->actingAs($this->admin)->get('/exports')
            ->assertOk()
            ->assertSee('Punya Admin')
            ->assertDontSee('Punya Orang Lain');
    }
}
