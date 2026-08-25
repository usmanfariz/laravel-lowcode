<?php

namespace Tests\Feature;

use App\Models\DataSource;
use App\Models\Permission;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\MysqlTestCase;

/**
 * Penghitungan baris report beragregat yang ber-join.
 *
 * Test ini WAJIB berjalan di MySQL. SQLite mengizinkan nama kolom ganda di
 * derived table, sehingga `select *` pada subquery penghitung lolos begitu
 * saja di sana — bug yang sama diam-diam tidak terdeteksi. MySQL menolaknya
 * dengan error 1060, dan itulah yang dijaga di sini.
 */
class ReportCountMysqlTest extends MysqlTestCase
{
    private Report $report;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('t_kategori', function (Blueprint $table) {
            $table->id();                     // sengaja: 'id' juga ada di t_item
            $table->string('nama', 100);
        });

        Schema::create('t_item', function (Blueprint $table) {
            $table->id();                     // 'id' kedua — sumber tabrakannya
            $table->string('kode', 50);
            $table->foreignId('kategori_id')->nullable()->constrained('t_kategori');
            $table->integer('qty')->default(0);
        });

        foreach ([['t_item', 'Item'], ['t_kategori', 'Kategori']] as [$table, $label]) {
            DataSource::create([
                'connection' => 'mysql', 'table_name' => $table, 'label' => $label,
                'primary_key' => 'id', 'is_readable' => true, 'is_writable' => true,
                'blocked_columns' => null, 'is_active' => true,
            ]);
        }

        DB::table('t_kategori')->insert([['nama' => 'Alpha'], ['nama' => 'Beta']]);
        DB::table('t_item')->insert([
            ['kode' => 'A', 'kategori_id' => 1, 'qty' => 5],
            ['kode' => 'B', 'kategori_id' => 1, 'qty' => 7],
            ['kode' => 'C', 'kategori_id' => 2, 'qty' => 9],
        ]);

        $this->report = Report::create([
            'code' => 'ringkasan', 'name' => 'Ringkasan', 'type' => 'summary',
            'source_type' => 'builder', 'connection' => 'mysql',
            'base_table' => 't_item', 'base_alias' => 'i', 'use_soft_delete' => false,
            'per_page' => 25, 'default_order_direction' => 'asc', 'is_active' => true,
            'allow_export_excel' => true, 'allow_export_csv' => true,
            'allow_export_pdf' => true, 'allow_print' => true,
        ]);

        DB::table('report_joins')->insert([
            'report_id' => $this->report->id, 'join_type' => 'left',
            'table_name' => 't_kategori', 'table_alias' => 'k',
            'first_column' => 'k.id', 'operator' => '=', 'second_column' => 'i.kategori_id',
            'order_no' => 1, 'is_active' => true,
        ]);

        DB::table('report_columns')->insert([
            [
                'report_id' => $this->report->id, 'label' => 'Kategori',
                'source_type' => 'column', 'column_name' => 'k.nama',
                'aggregate' => 'none', 'format' => 'text', 'align' => 'left',
                'is_visible' => true, 'is_sortable' => false, 'is_searchable' => false,
                'is_group_column' => true, 'show_total' => false, 'order_no' => 1, 'is_active' => true,
            ],
            [
                'report_id' => $this->report->id, 'label' => 'Total Qty',
                'source_type' => 'column', 'column_name' => 'i.qty',
                'aggregate' => 'sum', 'format' => 'number', 'align' => 'right',
                'is_visible' => true, 'is_sortable' => false, 'is_searchable' => false,
                'is_group_column' => false, 'show_total' => true, 'order_no' => 2, 'is_active' => true,
            ],
        ]);

        $this->admin = User::create([
            'username' => 'admin', 'name' => 'Admin', 'email' => 'admin@example.test',
            'password' => 'rahasia123', 'is_active' => true,
        ]);
        $role = Role::create([
            'code' => 'admin', 'name' => 'Admin', 'data_scope' => 'all',
            'is_system' => false, 'is_active' => true,
        ]);
        $izin = Permission::create([
            'code' => 'system.raw_query', 'name' => 'Raw', 'group_name' => 'Sistem', 'is_system' => false,
        ]);
        $role->permissions()->attach($izin->id);
        $this->admin->roles()->attach($role->id);
    }

    #[Test]
    public function endpoint_data_menghitung_jumlah_grup(): void
    {
        $data = $this->actingAs($this->admin)
            ->getJson('/reports/ringkasan/data?draw=1&start=0&length=10')
            ->assertOk()
            ->json();

        $this->assertSame(2, $data['recordsTotal'], 'jumlah grup salah');
        $this->assertSame('12', $data['data'][0]['c1']);
    }

    #[Test]
    public function ekspor_csv_tidak_gagal_karena_nama_kolom_ganda(): void
    {
        // Tanpa perbaikan: SQLSTATE[42S21] Duplicate column name 'id'.
        $this->actingAs($this->admin)
            ->get('/reports/ringkasan/export/csv')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    #[Test]
    public function ekspor_pdf_tidak_gagal_karena_nama_kolom_ganda(): void
    {
        $this->actingAs($this->admin)
            ->get('/reports/ringkasan/export/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    #[Test]
    public function ekspor_excel_tidak_gagal_karena_nama_kolom_ganda(): void
    {
        $this->actingAs($this->admin)
            ->get('/reports/ringkasan/export/xlsx')
            ->assertOk();
    }
}
