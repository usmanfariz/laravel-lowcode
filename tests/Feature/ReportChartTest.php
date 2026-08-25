<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Services\Report\ReportChartBuilder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\MetadataTestCase;

class ReportChartTest extends MetadataTestCase
{
    private Report $report;

    protected function setUp(): void
    {
        parent::setUp();

        $this->report = Report::create([
            'code' => 'grafik', 'name' => 'Grafik', 'type' => 'chart',
            'chart_type' => 'bar', 'chart_limit' => 30,
            'source_type' => 'builder', 'connection' => config('database.default'),
            'base_table' => 't_items', 'base_alias' => 'i', 'use_soft_delete' => true,
            'per_page' => 25, 'default_order_direction' => 'asc', 'is_active' => true,
        ]);

        DB::table('report_joins')->insert([
            'report_id' => $this->report->id, 'join_type' => 'left',
            'table_name' => 't_categories', 'table_alias' => 'k',
            'first_column' => 'k.id', 'operator' => '=', 'second_column' => 'i.category_id',
            'order_no' => 1, 'is_active' => true,
        ]);

        $this->report = $this->report->fresh(['joins', 'columns', 'filters']);
    }

    /** @param array<int, array{string,string,string,string,bool}> $columns */
    private function seedColumns(array $columns): void
    {
        $order = 0;
        foreach ($columns as [$label, $name, $aggregate, $format, $isGroup]) {
            DB::table('report_columns')->insert([
                'report_id' => $this->report->id, 'label' => $label,
                'source_type' => 'column', 'column_name' => $name, 'expression' => null,
                'aggregate' => $aggregate, 'format' => $format, 'align' => 'left',
                'is_visible' => true, 'is_sortable' => false, 'is_searchable' => false,
                'is_group_column' => $isGroup, 'show_total' => false,
                'order_no' => ++$order, 'is_active' => true,
            ]);
        }

        $this->report = $this->report->fresh(['joins', 'columns', 'filters']);
    }

    private function seedData(): void
    {
        DB::table('t_items')->insert([
            ['code' => 'A', 'name' => 'Satu', 'category_id' => 1, 'qty' => 5],
            ['code' => 'B', 'name' => 'Dua', 'category_id' => 1, 'qty' => 7],
            ['code' => 'C', 'name' => 'Tiga', 'category_id' => 2, 'qty' => 9],
        ]);
    }

    private function charts(): ReportChartBuilder
    {
        return app(ReportChartBuilder::class);
    }

    private function ringkasanBaku(): void
    {
        $this->seedColumns([
            ['Kategori', 'k.name', 'none', 'text', true],
            ['Total Qty', 'i.qty', 'sum', 'number', false],
        ]);
        $this->seedData();
    }

    #[Test]
    public function label_diambil_dari_kolom_pengelompokan(): void
    {
        $this->ringkasanBaku();

        $this->assertSame('k.name', $this->charts()->labelColumn($this->report)->column_name);
    }

    #[Test]
    public function hanya_kolom_berformat_angka_yang_jadi_deret(): void
    {
        $this->seedColumns([
            ['Kategori', 'k.name', 'none', 'text', true],
            ['Total', 'i.qty', 'sum', 'number', false],
            ['Catatan', 'i.code', 'max', 'text', false],   // teks: bukan deret
        ]);

        $this->assertSame(['Total'], $this->charts()->valueColumns($this->report)->pluck('label')->all());
    }

    #[Test]
    public function data_memakai_nilai_mentah_bukan_yang_diformat(): void
    {
        $this->ringkasanBaku();

        $data = $this->charts()->data($this->report, $this->admin);

        // "Rp 12.500,00" tidak bisa digambar; nilainya harus angka.
        $this->assertSame(['Alpha', 'Beta'], $data['labels']);
        $this->assertSame([12.0, 9.0], $data['datasets'][0]['data']);
        $this->assertIsFloat($data['datasets'][0]['data'][0]);
    }

    #[Test]
    public function filter_ikut_berlaku_pada_grafik(): void
    {
        $this->ringkasanBaku();

        $filterId = DB::table('report_filters')->insertGetId([
            'report_id' => $this->report->id, 'label' => 'Kategori',
            'column_name' => 'i.category_id', 'operator' => '=', 'input_type' => 'number',
            'data_source_type' => 'none', 'is_required' => false,
            'width' => 3, 'order_no' => 1, 'is_active' => true,
        ]);
        $this->report = $this->report->fresh(['joins', 'columns', 'filters']);

        $data = $this->charts()->data($this->report, $this->admin, [$filterId => ['1']]);

        $this->assertSame(['Alpha'], $data['labels']);
        $this->assertSame([12.0], $data['datasets'][0]['data']);
    }

    #[Test]
    public function batas_baris_dihormati_dan_penanda_terpotong_diberikan(): void
    {
        $this->seedColumns([
            ['Kode', 'i.code', 'none', 'text', true],
            ['Qty', 'i.qty', 'sum', 'number', false],
        ]);
        $this->seedData();

        $this->report->update(['chart_limit' => 2]);
        $this->report = $this->report->fresh(['joins', 'columns', 'filters']);

        $data = $this->charts()->data($this->report, $this->admin);

        $this->assertCount(2, $data['labels']);
        $this->assertTrue($data['truncated'], 'penanda terpotong tidak diberikan');
    }

    // ---------------- kapan grafik tidak bisa digambar ----------------

    #[Test]
    public function tanpa_kolom_angka_memberi_alasan_yang_jelas(): void
    {
        $this->seedColumns([
            ['Kategori', 'k.name', 'none', 'text', true],
            ['Kode', 'i.code', 'max', 'text', false],
        ]);

        $this->assertStringContainsString('kolom angka', $this->charts()->reasonUnavailable($this->report));
    }

    #[Test]
    public function tanpa_kolom_label_memberi_alasan_yang_jelas(): void
    {
        $this->seedColumns([
            ['Total', 'i.qty', 'sum', 'number', false],
        ]);

        $this->assertStringContainsString('label', $this->charts()->reasonUnavailable($this->report));
    }

    #[Test]
    public function report_yang_siap_tidak_memberi_alasan(): void
    {
        $this->ringkasanBaku();

        $this->assertNull($this->charts()->reasonUnavailable($this->report));
    }

    #[Test]
    public function mode_raw_belum_didukung(): void
    {
        $this->ringkasanBaku();
        $this->report->update(['source_type' => 'raw']);

        $this->assertStringContainsString('raw', $this->charts()->reasonUnavailable($this->report->fresh()));
    }

    // ---------------- halaman & endpoint ----------------

    #[Test]
    public function halaman_report_chart_memuat_kanvas_dan_pustakanya(): void
    {
        $this->ringkasanBaku();

        $this->actingAs($this->admin)->get('/reports/grafik')
            ->assertOk()
            ->assertSee('chart-report')
            ->assertSee('chart.umd.min.js')
            // Tabelnya tetap ada — grafik melengkapi, bukan menggantikan.
            ->assertSee('tbl-report');
    }

    #[Test]
    public function report_bertipe_tabel_tidak_memuat_pustaka_grafik(): void
    {
        $this->ringkasanBaku();
        $this->report->update(['type' => 'table']);

        $this->actingAs($this->admin)->get('/reports/grafik')
            ->assertOk()
            ->assertDontSee('chart.umd.min.js');
    }

    #[Test]
    public function endpoint_grafik_mengembalikan_label_dan_deret(): void
    {
        $this->ringkasanBaku();

        $json = $this->actingAs($this->admin)->getJson('/reports/grafik/chart')
            ->assertOk()
            ->json();

        $this->assertSame(['Alpha', 'Beta'], $json['labels']);
        $this->assertSame('Total Qty', $json['datasets'][0]['label']);
        $this->assertFalse($json['truncated']);
    }

    #[Test]
    public function endpoint_grafik_menjelaskan_kenapa_tidak_bisa(): void
    {
        $this->seedColumns([['Kategori', 'k.name', 'none', 'text', true]]);

        $this->actingAs($this->admin)->getJson('/reports/grafik/chart')
            ->assertStatus(422)
            ->assertJsonStructure(['error']);
    }

    #[Test]
    public function endpoint_grafik_menghormati_izin_report(): void
    {
        $this->ringkasanBaku();
        $this->report->update(['permission_code' => 'report.khusus']);

        $orangLain = $this->makeUser('lain', ['item.view']);

        $this->actingAs($orangLain)->getJson('/reports/grafik/chart')->assertForbidden();
    }
}
