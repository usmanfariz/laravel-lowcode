<?php

namespace Tests\Feature;

use App\Models\DashboardWidget;
use App\Models\Report;
use App\Services\DashboardService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\MetadataTestCase;

class DashboardTest extends MetadataTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('t_items')->insert([
            ['code' => 'A', 'name' => 'Satu', 'category_id' => 1, 'price' => 1000, 'qty' => 2],
            ['code' => 'B', 'name' => 'Dua', 'category_id' => 1, 'price' => 2000, 'qty' => 3],
            ['code' => 'C', 'name' => 'Tiga', 'category_id' => 2, 'price' => 3000, 'qty' => 4],
        ]);
    }

    private function widget(array $overrides = []): DashboardWidget
    {
        return DashboardWidget::create(array_merge([
            'code' => 'uji', 'title' => 'Uji', 'type' => 'stat',
            'color' => 'info', 'width' => 3,
            'source_table' => 't_items', 'aggregate' => 'count',
            'format' => 'number', 'order_no' => 1, 'is_active' => true,
        ], $overrides));
    }

    private function service(): DashboardService
    {
        return app(DashboardService::class);
    }

    // ---------------- widget angka ----------------

    #[Test]
    public function menghitung_jumlah_baris(): void
    {
        $hasil = $this->service()->resolve($this->widget(), $this->admin);

        $this->assertSame(3, $hasil['value']);
    }

    #[Test]
    public function menjumlahkan_kolom(): void
    {
        $hasil = $this->service()->resolve(
            $this->widget(['aggregate' => 'sum', 'source_column' => 'price']),
            $this->admin
        );

        $this->assertSame(6000.0, $hasil['value']);
    }

    #[Test]
    public function penyaring_ikut_berlaku(): void
    {
        $hasil = $this->service()->resolve(
            $this->widget(['filter' => ['category_id' => 1]]),
            $this->admin
        );

        $this->assertSame(2, $hasil['value']);
    }

    #[Test]
    public function baris_terhapus_tidak_ikut_dihitung(): void
    {
        DB::table('t_items')->where('code', 'A')->update(['deleted_at' => now()]);

        $this->assertSame(2, $this->service()->resolve($this->widget(), $this->admin)['value']);
    }

    #[Test]
    public function tabel_di_luar_whitelist_jadi_pesan_bukan_exception(): void
    {
        // Satu widget bermasalah tidak boleh mengosongkan seluruh dashboard.
        $hasil = $this->service()->resolve($this->widget(['source_table' => 't_hidden']), $this->admin);

        $this->assertArrayHasKey('error', $hasil);
        $this->assertStringContainsString('t_hidden', $hasil['error']);
    }

    #[Test]
    public function kolom_yang_diblokir_ditolak(): void
    {
        $hasil = $this->service()->resolve(
            $this->widget(['aggregate' => 'max', 'source_column' => 'secret']),
            $this->admin
        );

        $this->assertArrayHasKey('error', $hasil);
    }

    #[Test]
    public function format_angka_mengikuti_setelan(): void
    {
        $service = $this->service();

        // Tidak perlu disimpan: pemformatan murni tergantung setelannya.
        $format = fn (string $f) => new DashboardWidget(['format' => $f]);

        $this->assertSame('1.500', $service->formatValue($format('number'), 1500));
        $this->assertSame('Rp 1.500', $service->formatValue($format('currency'), 1500));
        $this->assertSame('1.500,0%', $service->formatValue($format('percentage'), 1500));
        $this->assertSame('1.500,00', $service->formatValue($format('decimal'), 1500));
    }

    // ---------------- widget yang menumpang report ----------------

    private function makeReport(?string $permission = null): Report
    {
        $report = Report::create([
            'code' => 'ringkas', 'name' => 'Ringkas', 'type' => 'chart',
            'chart_type' => 'bar', 'chart_limit' => 30,
            'source_type' => 'builder', 'connection' => config('database.default'),
            'base_table' => 't_items', 'base_alias' => 'i', 'use_soft_delete' => true,
            'per_page' => 25, 'default_order_direction' => 'asc', 'is_active' => true,
            'permission_code' => $permission,
        ]);

        DB::table('report_columns')->insert([
            [
                'report_id' => $report->id, 'label' => 'Kode', 'source_type' => 'column',
                'column_name' => 'i.code', 'aggregate' => 'none', 'format' => 'text',
                'align' => 'left', 'is_visible' => true, 'is_sortable' => false,
                'is_searchable' => false, 'is_group_column' => true, 'show_total' => false,
                'order_no' => 1, 'is_active' => true,
            ],
            [
                'report_id' => $report->id, 'label' => 'Qty', 'source_type' => 'column',
                'column_name' => 'i.qty', 'aggregate' => 'sum', 'format' => 'number',
                'align' => 'right', 'is_visible' => true, 'is_sortable' => false,
                'is_searchable' => false, 'is_group_column' => false, 'show_total' => false,
                'order_no' => 2, 'is_active' => true,
            ],
        ]);

        return $report->fresh(['joins', 'columns', 'filters']);
    }

    #[Test]
    public function widget_grafik_mengambil_data_dari_report(): void
    {
        $this->makeReport();

        $hasil = $this->service()->resolve(
            $this->widget(['type' => 'chart', 'report_code' => 'ringkas']),
            $this->admin
        );

        $this->assertSame(['A', 'B', 'C'], $hasil['chart']['labels']);
        $this->assertSame([2.0, 3.0, 4.0], $hasil['chart']['datasets'][0]['data']);
    }

    #[Test]
    public function widget_tabel_menghormati_batas_baris(): void
    {
        $this->makeReport();

        $hasil = $this->service()->resolve(
            $this->widget(['type' => 'table', 'report_code' => 'ringkas', 'row_limit' => 2]),
            $this->admin
        );

        $this->assertCount(2, $hasil['rows']);
    }

    #[Test]
    public function widget_tidak_bisa_melewati_izin_report(): void
    {
        // Ini penjagaan terpentingnya: widget menumpang report, jadi
        // permission report-nya harus tetap berlaku.
        $this->makeReport('report.rahasia');

        $orangLain = $this->makeUser('lain', ['item.view']);

        $hasil = $this->service()->resolve(
            $this->widget(['type' => 'chart', 'report_code' => 'ringkas']),
            $orangLain
        );

        $this->assertArrayHasKey('error', $hasil);
        $this->assertStringContainsString('report.rahasia', $hasil['error']);
    }

    #[Test]
    public function report_nonaktif_ditolak(): void
    {
        $this->makeReport()->update(['is_active' => false]);

        $hasil = $this->service()->resolve(
            $this->widget(['type' => 'table', 'report_code' => 'ringkas']),
            $this->admin
        );

        $this->assertArrayHasKey('error', $hasil);
    }

    // ---------------- penyaringan & halaman ----------------

    #[Test]
    public function widget_disaring_menurut_izin_penggunanya(): void
    {
        $this->widget(['code' => 'terbuka', 'title' => 'Terbuka', 'permission_code' => null, 'order_no' => 1]);
        $this->widget(['code' => 'tertutup', 'title' => 'Tertutup', 'permission_code' => 'item.delete', 'order_no' => 2]);

        $pembaca = $this->makeUser('pembaca', ['item.view']);

        $this->assertSame(['Terbuka'], $this->service()->widgetsFor($pembaca)->pluck('title')->all());
        $this->assertCount(2, $this->service()->widgetsFor($this->admin));
    }

    #[Test]
    public function widget_nonaktif_tidak_tampil(): void
    {
        $this->widget(['is_active' => false]);

        $this->assertCount(0, $this->service()->widgetsFor($this->admin));
    }

    #[Test]
    public function dashboard_kosong_mengajak_mengaturnya(): void
    {
        $this->actingAs($this->admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Dashboard masih kosong');
    }

    #[Test]
    public function dashboard_menggambar_widget_yang_ada(): void
    {
        $this->widget(['title' => 'Jumlah Item']);

        $this->actingAs($this->admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Jumlah Item')
            ->assertSee('small-box bg-info', false);
    }

    #[Test]
    public function pustaka_grafik_hanya_dimuat_bila_ada_widget_grafik(): void
    {
        $this->widget(['title' => 'Angka Saja']);

        $this->actingAs($this->admin)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('chart.umd.min.js');
    }

    #[Test]
    public function builder_dashboard_butuh_izin_khusus(): void
    {
        $biasa = $this->makeUser('biasa', ['item.view']);

        $this->actingAs($biasa)->get('/builder/dashboard')->assertForbidden();
    }
}
