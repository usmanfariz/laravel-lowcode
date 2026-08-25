<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Services\Report\ReportQueryBuilder;
use App\Services\Report\ReportService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\MetadataTestCase;

class ReportEngineTest extends MetadataTestCase
{
    private Report $report;

    protected function setUp(): void
    {
        parent::setUp();

        $this->report = Report::create([
            'code' => 'item_summary', 'name' => 'Ringkasan Item', 'type' => 'summary',
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

    private function builder(): ReportQueryBuilder
    {
        return app(ReportQueryBuilder::class);
    }

    #[Test]
    public function referensi_kolom_pada_alias_terdaftar_diterima(): void
    {
        $this->assertSame('i.code', $this->builder()->qualify($this->report, 'i.code'));
        $this->assertSame('k.name', $this->builder()->qualify($this->report, 'k.name'));
    }

    #[Test]
    public function referensi_tanpa_alias_dianggap_milik_tabel_utama(): void
    {
        $this->assertSame('i.code', $this->builder()->qualify($this->report, 'code'));
    }

    #[Test]
    public function alias_yang_tidak_terdaftar_ditolak(): void
    {
        $this->expectExceptionMessage("Alias 'users' tidak dikenal");
        $this->builder()->qualify($this->report, 'users.password');
    }

    #[Test]
    public function kolom_yang_diblokir_ditolak_walau_aliasnya_benar(): void
    {
        $this->expectException(\App\Exceptions\DataSourceException::class);
        $this->builder()->qualify($this->report, 'i.secret');
    }

    #[Test]
    public function kolom_yang_tidak_ada_ditolak(): void
    {
        $this->expectException(\App\Exceptions\DataSourceException::class);
        $this->builder()->qualify($this->report, 'i.tidak_ada');
    }

    #[Test]
    public function baris_terhapus_tidak_ikut_terbaca(): void
    {
        DB::table('t_items')->insert([
            ['code' => 'A', 'name' => 'Aktif', 'deleted_at' => null],
            ['code' => 'B', 'name' => 'Terhapus', 'deleted_at' => now()],
        ]);

        $count = $this->builder()->base($this->report, $this->admin)->count();

        $this->assertSame(1, $count, 'report memuat baris yang sudah dihapus');
    }

    #[Test]
    public function cakupan_per_baris_menyaring_report(): void
    {
        $this->report->update(['scope_column' => 'i.branch_code']);
        $this->report = $this->report->fresh(['joins', 'columns', 'filters']);

        DB::table('t_items')->insert([
            ['code' => 'A', 'name' => 'Satu', 'branch_code' => 'CAB-01'],
            ['code' => 'B', 'name' => 'Dua', 'branch_code' => 'CAB-02'],
        ]);

        $staff = $this->makeUser('staff', [], 'branch', 'CAB-01');

        $this->assertSame(1, $this->builder()->base($this->report, $staff)->count());
        $this->assertSame(2, $this->builder()->base($this->report, $this->admin)->count());
    }

    #[Test]
    public function baris_terhapus_pada_tabel_join_ikut_disaring(): void
    {
        // t_categories diberi deleted_at agar bisa diuji soft delete-nya.
        \Illuminate\Support\Facades\Schema::table('t_categories', function ($table) {
            $table->softDeletes();
        });
        app(\App\Services\DataSourceResolver::class)->flushColumns('t_categories');

        DB::table('t_categories')->where('id', 1)->update(['deleted_at' => now()]);
        DB::table('t_items')->insert([
            ['code' => 'A', 'name' => 'Kategori terhapus', 'category_id' => 1],
            ['code' => 'B', 'name' => 'Kategori aktif', 'category_id' => 2],
        ]);

        $rows = $this->builder()->base($this->report, $this->admin)
            ->select(['i.code', 'k.name as kategori'])
            ->orderBy('i.code')
            ->get();

        // LEFT JOIN: baris induknya tetap ada, hanya kategorinya yang kosong.
        $this->assertCount(2, $rows, 'baris induk ikut hilang — LEFT JOIN berubah jadi INNER');
        $this->assertNull($rows[0]->kategori, 'kategori terhapus masih terbawa');
        $this->assertSame('Beta', $rows[1]->kategori);
    }

    // ---------------- filter ----------------

    #[Test]
    public function operator_between_memakai_dua_nilai(): void
    {
        $this->seedFilter('i.price', 'between');
        DB::table('t_items')->insert([
            ['code' => 'A', 'name' => 'Murah', 'price' => 100],
            ['code' => 'B', 'name' => 'Sedang', 'price' => 500],
            ['code' => 'C', 'name' => 'Mahal', 'price' => 5000],
        ]);

        $query = $this->builder()->base($this->report, $this->admin);
        $this->builder()->applyFilters($query, $this->report, [$this->filterId => ['100', '1000']]);

        $this->assertSame(2, $query->count());
    }

    #[Test]
    public function operator_between_dengan_satu_ujung_menjadi_minimal(): void
    {
        $this->seedFilter('i.price', 'between');
        DB::table('t_items')->insert([
            ['code' => 'A', 'name' => 'Murah', 'price' => 100],
            ['code' => 'B', 'name' => 'Mahal', 'price' => 5000],
        ]);

        $query = $this->builder()->base($this->report, $this->admin);
        $this->builder()->applyFilters($query, $this->report, [$this->filterId => ['1000']]);

        $this->assertSame(1, $query->count());
    }

    #[Test]
    public function operator_in_menerima_banyak_nilai(): void
    {
        $this->seedFilter('i.code', 'in');
        DB::table('t_items')->insert([
            ['code' => 'A', 'name' => 'Satu'],
            ['code' => 'B', 'name' => 'Dua'],
            ['code' => 'C', 'name' => 'Tiga'],
        ]);

        $query = $this->builder()->base($this->report, $this->admin);
        $this->builder()->applyFilters($query, $this->report, [$this->filterId => ['A', 'C']]);

        $this->assertSame(2, $query->count());
    }

    #[Test]
    public function filter_wajib_tanpa_nilai_melempar_kesalahan(): void
    {
        $this->seedFilter('i.code', '=', required: true);

        $query = $this->builder()->base($this->report, $this->admin);

        $this->expectException(RuntimeException::class);
        $this->builder()->applyFilters($query, $this->report, []);
    }

    #[Test]
    public function nilai_default_dipakai_saat_request_kosong(): void
    {
        $this->seedFilter('i.code', '=', defaults: ['A']);
        DB::table('t_items')->insert([
            ['code' => 'A', 'name' => 'Satu'],
            ['code' => 'B', 'name' => 'Dua'],
        ]);

        $query = $this->builder()->base($this->report, $this->admin);
        $this->builder()->applyFilters($query, $this->report, []);

        $this->assertSame(1, $query->count());
    }

    // ---------------- pengelompokan ----------------

    /** @param array<int, array{string,string,string,bool}> $columns label,kolom,agregat,grup */
    private function seedColumns(array $columns): void
    {
        $order = 0;
        foreach ($columns as [$label, $name, $aggregate, $isGroup]) {
            DB::table('report_columns')->insert([
                'report_id' => $this->report->id, 'label' => $label,
                'source_type' => str_contains($name, '(') ? 'expression' : 'column',
                'column_name' => str_contains($name, '(') ? null : $name,
                'expression' => str_contains($name, '(') ? $name : null,
                'aggregate' => $aggregate, 'format' => 'text', 'align' => 'left',
                'is_visible' => true, 'is_sortable' => false, 'is_searchable' => false,
                'is_group_column' => $isGroup, 'show_total' => false,
                'order_no' => ++$order, 'is_active' => true,
            ]);
        }

        $this->report = $this->report->fresh(['joins', 'columns', 'filters']);
    }

    private function seedTigaBarisDuaKategori(): void
    {
        DB::table('t_items')->insert([
            ['code' => 'A', 'name' => 'Satu', 'category_id' => 1],
            ['code' => 'B', 'name' => 'Dua', 'category_id' => 1],
            ['code' => 'C', 'name' => 'Tiga', 'category_id' => 2],
        ]);
    }

    #[Test]
    public function campuran_agregat_dan_biasa_tanpa_penanda_dikelompokkan_sendiri(): void
    {
        // COUNT(id) berdampingan dengan k.name hanya punya satu tafsir yang
        // masuk akal: hitung per nama. Tanpa GROUP BY, MySQL dengan
        // only_full_group_by menolaknya.
        $this->seedColumns([
            ['Jumlah', 'i.id', 'count', false],
            ['Kategori', 'k.name', 'none', false],
        ]);
        $this->seedTigaBarisDuaKategori();

        $grup = $this->builder()->groupColumns($this->report);

        $this->assertCount(1, $grup);
        $this->assertSame('k.name', $grup->first()->column_name);

        $data = $this->actingAs($this->admin)
            ->getJson('/reports/item_summary/data?draw=1&start=0&length=10')->json();

        $this->assertSame(2, $data['recordsTotal'], 'dua kategori seharusnya jadi dua baris');
    }

    #[Test]
    public function seluruhnya_agregat_tetap_satu_baris_ringkasan(): void
    {
        // Ini sah dan memang perilaku yang diharapkan: satu baris ringkasan.
        $this->seedColumns([
            ['Jumlah', 'i.id', 'count', false],
            ['Total', 'i.qty', 'sum', false],
        ]);
        $this->seedTigaBarisDuaKategori();

        $this->assertCount(0, $this->builder()->groupColumns($this->report));

        $data = $this->actingAs($this->admin)
            ->getJson('/reports/item_summary/data?draw=1&start=0&length=10')->json();

        $this->assertSame(1, $data['recordsTotal']);
    }

    #[Test]
    public function penanda_eksplisit_mengalahkan_pengelompokan_otomatis(): void
    {
        $this->seedColumns([
            ['Jumlah', 'i.id', 'count', false],
            ['Kategori', 'k.name', 'none', true],
            ['Kode', 'i.code', 'none', false],
        ]);
        $this->seedTigaBarisDuaKategori();

        $grup = $this->builder()->groupColumns($this->report);

        // Hanya yang ditandai, bukan seluruh kolom non-agregat.
        $this->assertCount(1, $grup);
        $this->assertSame('k.name', $grup->first()->column_name);
    }

    #[Test]
    public function ekspresi_beragregat_tidak_ikut_dikelompokkan(): void
    {
        // SUM(i.qty * 2) punya kolom `aggregate` bernilai 'none', tapi jelas
        // hasil agregasi. Mengelompokkan berdasarkan SUM tidak masuk akal.
        $this->seedColumns([
            ['Nilai', 'SUM(i.qty * 2)', 'none', false],
            ['Kategori', 'k.name', 'none', false],
        ]);
        $this->seedTigaBarisDuaKategori();

        $grup = $this->builder()->groupColumns($this->report);

        $this->assertCount(1, $grup);
        $this->assertSame('k.name', $grup->first()->column_name,
            'ekspresi agregat ikut masuk GROUP BY');
    }

    #[Test]
    public function report_tanpa_agregat_sama_sekali_tidak_dikelompokkan(): void
    {
        $this->seedColumns([
            ['Kode', 'i.code', 'none', false],
            ['Kategori', 'k.name', 'none', false],
        ]);
        $this->seedTigaBarisDuaKategori();

        $this->assertCount(0, $this->builder()->groupColumns($this->report));

        $data = $this->actingAs($this->admin)
            ->getJson('/reports/item_summary/data?draw=1&start=0&length=10')->json();

        $this->assertSame(3, $data['recordsTotal'], 'baris ikut terkelompok padahal tak ada agregat');
    }

    #[Test]
    public function kolom_tersembunyi_tidak_ikut_menentukan_pengelompokan(): void
    {
        $this->seedColumns([
            ['Jumlah', 'i.id', 'count', false],
            ['Kategori', 'k.name', 'none', false],
        ]);
        DB::table('report_columns')->where('report_id', $this->report->id)
            ->where('label', 'Kategori')->update(['is_visible' => false]);
        $this->report = $this->report->fresh(['joins', 'columns', 'filters']);

        // Hanya kolom tampil yang jadi bagian SELECT, jadi hanya itu pula yang
        // relevan bagi GROUP BY.
        $this->assertCount(0, $this->builder()->groupColumns($this->report));
    }

    // ---------------- jumlah baris ----------------

    #[Test]
    public function menghitung_baris_report_beragregat_pada_report_ber_join(): void
    {
        // Report dengan join DAN group by: subquery penghitungnya dulu memakai
        // "select *", sehingga dua kolom "id" bertabrakan di derived table.
        $this->seedGroupColumn();

        DB::table('t_items')->insert([
            ['code' => 'A', 'name' => 'Satu', 'category_id' => 1],
            ['code' => 'B', 'name' => 'Dua', 'category_id' => 1],
            ['code' => 'C', 'name' => 'Tiga', 'category_id' => 2],
        ]);

        $data = $this->actingAs($this->admin)
            ->getJson('/reports/item_summary/data?draw=1&start=0&length=10')
            ->assertOk()
            ->json();

        // Dua kategori berbeda -> dua baris hasil pengelompokan.
        $this->assertSame(2, $data['recordsTotal']);
    }

    #[Test]
    public function ekspor_report_beragregat_ber_join_tidak_gagal(): void
    {
        $this->seedGroupColumn();

        DB::table('t_items')->insert([
            ['code' => 'A', 'name' => 'Satu', 'category_id' => 1],
            ['code' => 'B', 'name' => 'Dua', 'category_id' => 2],
        ]);

        // Jalur ekspor memanggil penghitung baris untuk memutuskan sinkron
        // atau antre — di situlah regresinya dulu muncul.
        $this->actingAs($this->admin)
            ->get('/reports/item_summary/export/csv')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    /** Tambahkan kolom pengelompokan dan satu kolom agregat. */
    private function seedGroupColumn(): void
    {
        DB::table('report_columns')->insert([
            [
                'report_id' => $this->report->id, 'label' => 'Kategori',
                'source_type' => 'column', 'column_name' => 'k.name',
                'aggregate' => 'none', 'format' => 'text', 'align' => 'left',
                'is_visible' => true, 'is_sortable' => false, 'is_searchable' => false,
                'is_group_column' => true, 'show_total' => false,
                'order_no' => 1, 'is_active' => true,
            ],
            [
                'report_id' => $this->report->id, 'label' => 'Jumlah',
                'source_type' => 'column', 'column_name' => 'i.id',
                'aggregate' => 'count', 'format' => 'number', 'align' => 'right',
                'is_visible' => true, 'is_sortable' => false, 'is_searchable' => false,
                'is_group_column' => false, 'show_total' => true,
                'order_no' => 2, 'is_active' => true,
            ],
        ]);

        $this->report = $this->report->fresh(['joins', 'columns', 'filters']);
    }

    // ---------------- mode raw ----------------

    #[Test]
    #[DataProvider('queryAman')]
    public function query_select_tunggal_diterima(string $sql): void
    {
        $this->assertTrue(app(ReportService::class)->isSelectOnly($sql));
    }

    public static function queryAman(): array
    {
        return [
            ['SELECT * FROM t_items'],
            ['select id, name from t_items where code = "A"'],
            ['  SELECT 1  '],
            ['SELECT * FROM t_items;'],
        ];
    }

    #[Test]
    #[DataProvider('queryBerbahaya')]
    public function query_selain_select_ditolak(string $sql): void
    {
        $this->assertFalse(app(ReportService::class)->isSelectOnly($sql));
    }

    public static function queryBerbahaya(): array
    {
        return [
            'pernyataan kedua' => ['SELECT * FROM t_items; DROP TABLE users'],
            'delete' => ['DELETE FROM users'],
            'update' => ['UPDATE users SET password = 1'],
            'insert' => ['INSERT INTO users VALUES (1)'],
            'information_schema' => ['SELECT * FROM information_schema.tables'],
            'tabel mysql' => ['SELECT * FROM mysql.user'],
            'into outfile' => ['SELECT * FROM users INTO OUTFILE "/tmp/x"'],
            'load_file' => ['SELECT LOAD_FILE("/etc/passwd")'],
            'kosong' => [''],
        ];
    }

    // ------------------------------------------------------------------

    private int $filterId;

    private function seedFilter(string $column, string $operator, bool $required = false, ?array $defaults = null): void
    {
        $this->filterId = DB::table('report_filters')->insertGetId([
            'report_id' => $this->report->id, 'label' => 'Uji',
            'column_name' => $column, 'operator' => $operator, 'input_type' => 'text',
            'data_source_type' => 'none', 'default_values' => $defaults ? json_encode($defaults) : null,
            'is_required' => $required, 'width' => 3, 'order_no' => 1, 'is_active' => true,
        ]);

        $this->report = $this->report->fresh(['joins', 'columns', 'filters']);
    }
}
