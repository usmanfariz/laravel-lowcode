<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Dua report contoh di atas tabel demo:
 *
 *   product_list    — report tabel biasa, join ke kategori, filter lengkap
 *   product_summary — report beragregat: dikelompokkan per kategori
 *
 * Menguji join, agregat, GROUP BY, dan ketiga bentuk nilai filter
 * (tunggal, between, in). Aman dihapus bersama migrasi demo.
 *
 * Jalankan: php artisan db:seed --class=DemoReportSeeder
 */
class DemoReportSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        foreach (['view', 'export', 'print'] as $action) {
            DB::table('permissions')->updateOrInsert(
                ['code' => "report.product.{$action}"],
                [
                    'name' => 'Report Produk: '.$action, 'group_name' => 'Demo',
                    'is_system' => false, 'created_at' => $now, 'updated_at' => $now,
                ]
            );
        }

        $superadminId = DB::table('roles')->where('code', 'superadmin')->value('id');
        foreach (DB::table('permissions')->where('code', 'like', 'report.product.%')->pluck('id') as $pid) {
            DB::table('role_permissions')->updateOrInsert(
                ['role_id' => $superadminId, 'permission_id' => $pid], []
            );
        }

        $this->buildListReport($now);
        $this->buildSummaryReport($now);

        DB::table('menus')->updateOrInsert(
            ['code' => 'demo.report_product'],
            [
                'parent_id' => null, 'name' => 'Report Produk', 'icon' => 'fas fa-chart-bar',
                'link_type' => 'report', 'target_value' => 'product_list',
                'permission_code' => 'report.product.view', 'order_no' => 11,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]
        );

        $this->command?->info('Report siap: /reports/product_list dan /reports/product_summary');
    }

    private function reset(string $code): void
    {
        $id = DB::table('reports')->where('code', $code)->value('id');
        if (! $id) {
            return;
        }
        DB::table('report_joins')->where('report_id', $id)->delete();
        DB::table('report_columns')->where('report_id', $id)->delete();
        DB::table('report_filters')->where('report_id', $id)->delete();
        DB::table('reports')->where('id', $id)->delete();
    }

    private function buildListReport($now): void
    {
        $this->reset('product_list');

        $id = DB::table('reports')->insertGetId([
            'code' => 'product_list', 'name' => 'Daftar Produk',
            'title' => 'Daftar Produk',
            'description' => 'Report tabel dengan join ke kategori dan filter lengkap.',
            'type' => 'table', 'source_type' => 'builder', 'connection' => 'mysql',
            'base_table' => 'demo_products', 'base_alias' => 'p',
            'default_order_column' => 'p.code', 'default_order_direction' => 'asc',
            'per_page' => 25, 'scope_column' => 'branch_code',
            'permission_code' => 'report.product.view',
            'allow_export_excel' => true, 'allow_export_pdf' => true,
            'allow_export_csv' => true, 'allow_print' => true,
            'export_queue_threshold' => 5000, 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        DB::table('report_joins')->insert([
            'report_id' => $id, 'join_type' => 'left',
            'table_name' => 'demo_categories', 'table_alias' => 'k',
            'first_column' => 'k.id', 'operator' => '=', 'second_column' => 'p.category_id',
            'order_no' => 1, 'is_active' => true,
        ]);

        $columns = [
            ['Kode', 'column', 'p.code', null, 'none', 'text', 'left', '110px', true, true, false, false],
            ['Nama Produk', 'column', 'p.name', null, 'none', 'text', 'left', null, true, true, false, false],
            ['Kategori', 'column', 'k.name', null, 'none', 'text', 'left', null, true, true, false, false],
            ['Status', 'column', 'p.status', null, 'none', 'badge', 'center', '110px', true, true, false, false],
            ['Harga', 'column', 'p.price', null, 'none', 'currency', 'right', '150px', false, true, false, true],
            ['Stok', 'column', 'p.stock', null, 'none', 'number', 'right', '90px', false, true, false, true],
        ];
        $this->insertColumns($id, $columns);

        // Tiga bentuk nilai filter sekaligus.
        $filters = [
            ['Status', 'p.status', 'in', 'multi_select', 'static',
                null, null, null, json_encode(['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived']), null, 3],
            ['Kategori', 'p.category_id', '=', 'select2', 'table',
                'demo_categories', 'id', 'name', null, null, 3],
            ['Rentang Harga', 'p.price', 'between', 'number', 'none',
                null, null, null, null, null, 4],
            ['Nama mengandung', 'p.name', 'like', 'text', 'none',
                null, null, null, null, null, 2],
        ];
        $this->insertFilters($id, $filters);
    }

    private function buildSummaryReport($now): void
    {
        $this->reset('product_summary');

        $id = DB::table('reports')->insertGetId([
            'code' => 'product_summary', 'name' => 'Ringkasan per Kategori',
            'title' => 'Ringkasan Produk per Kategori',
            'description' => 'Report beragregat: dikelompokkan per kategori.',
            'type' => 'summary', 'source_type' => 'builder', 'connection' => 'mysql',
            'base_table' => 'demo_products', 'base_alias' => 'p',
            'per_page' => 25, 'scope_column' => 'branch_code',
            'permission_code' => 'report.product.view',
            'allow_export_excel' => true, 'allow_print' => true,
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);

        DB::table('report_joins')->insert([
            'report_id' => $id, 'join_type' => 'left',
            'table_name' => 'demo_categories', 'table_alias' => 'k',
            'first_column' => 'k.id', 'operator' => '=', 'second_column' => 'p.category_id',
            'order_no' => 1, 'is_active' => true,
        ]);

        // is_group_column menandai kolom yang masuk GROUP BY.
        $columns = [
            ['Kategori', 'column', 'k.name', 'kategori', 'none', 'text', 'left', null, false, true, true, false],
            ['Jumlah Produk', 'column', 'p.id', 'jumlah', 'count', 'number', 'right', '130px', false, false, false, true],
            ['Total Stok', 'column', 'p.stock', 'total_stok', 'sum', 'number', 'right', '130px', false, false, false, true],
            ['Harga Rata-rata', 'column', 'p.price', 'rata_harga', 'avg', 'currency', 'right', '160px', false, false, false, false],
            ['Harga Tertinggi', 'column', 'p.price', 'max_harga', 'max', 'currency', 'right', '160px', false, false, false, false],
        ];
        $this->insertColumns($id, $columns);

        $this->insertFilters($id, [
            ['Status', 'p.status', 'in', 'multi_select', 'static',
                null, null, null, json_encode(['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived']), null, 4],
        ]);
    }

    private function insertColumns(int $reportId, array $columns): void
    {
        $order = 0;
        foreach ($columns as [$label, $src, $col, $alias, $agg, $format, $align, $width, $searchable, $sortable, $group, $showTotal]) {
            DB::table('report_columns')->insert([
                'report_id' => $reportId, 'label' => $label, 'source_type' => $src,
                'column_name' => $col, 'expression' => null, 'column_alias' => $alias,
                'aggregate' => $agg, 'format' => $format, 'decimal_places' => 2,
                'align' => $align, 'width' => $width,
                'is_visible' => true, 'is_sortable' => $sortable, 'is_searchable' => $searchable,
                'is_group_column' => $group, 'show_total' => $showTotal,
                'order_no' => ++$order, 'is_active' => true,
            ]);
        }
    }

    private function insertFilters(int $reportId, array $filters): void
    {
        $order = 0;
        foreach ($filters as [$label, $col, $op, $input, $srcType, $src, $valField, $labField, $static, $defaults, $width]) {
            DB::table('report_filters')->insert([
                'report_id' => $reportId, 'label' => $label, 'column_name' => $col,
                'operator' => $op, 'input_type' => $input,
                'data_source_type' => $srcType, 'data_source' => $src,
                'value_field' => $valField, 'label_field' => $labField,
                'data_filter' => null, 'static_options' => $static,
                'default_values' => $defaults, 'is_required' => false,
                'width' => $width, 'order_no' => ++$order, 'is_active' => true,
            ]);
        }
    }
}
