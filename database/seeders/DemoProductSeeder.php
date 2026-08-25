<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Form contoh yang BISA MENYIMPAN, di atas tabel demo dari migrasi
 * 2026_08_25_100001_create_demo_business_tables.
 *
 * Dipakai untuk mencoba Dynamic CRUD (tahap 5). Aman dihapus bersama
 * migrasi demo-nya.
 *
 * Jalankan: php artisan db:seed --class=DemoProductSeeder
 */
class DemoProductSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // --- whitelist tabel demo: kali ini boleh ditulis ---
        foreach ([
            ['demo_products', 'Produk', true],
            ['demo_categories', 'Kategori Produk', false],
        ] as [$table, $label, $writable]) {
            DB::table('data_sources')->updateOrInsert(
                ['connection' => 'mysql', 'table_name' => $table],
                [
                    'label' => $label, 'primary_key' => 'id',
                    'is_readable' => true, 'is_writable' => $writable,
                    'blocked_columns' => null, 'is_active' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]
            );
        }

        // --- permission mengikuti konvensi <prefix>.<action> ---
        foreach (['view', 'create', 'edit', 'delete', 'export', 'print'] as $action) {
            DB::table('permissions')->updateOrInsert(
                ['code' => "product.{$action}"],
                [
                    'name' => 'Produk: '.$action, 'group_name' => 'Demo',
                    'is_system' => false, 'created_at' => $now, 'updated_at' => $now,
                ]
            );
        }

        $superadminId = DB::table('roles')->where('code', 'superadmin')->value('id');
        foreach (DB::table('permissions')->where('code', 'like', 'product.%')->pluck('id') as $pid) {
            DB::table('role_permissions')->updateOrInsert(
                ['role_id' => $superadminId, 'permission_id' => $pid], []
            );
        }

        // --- data kategori contoh ---
        if (DB::table('demo_categories')->count() === 0) {
            DB::table('demo_categories')->insert([
                ['name' => 'Elektronik', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Pakaian', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['name' => 'Makanan', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        // --- definisi form ---
        $old = DB::table('forms')->where('code', 'product')->value('id');
        if ($old) {
            DB::table('form_fields')->where('form_id', $old)->delete();
            DB::table('form_list_columns')->where('form_id', $old)->delete();
            DB::table('forms')->where('id', $old)->delete();
        }

        $formId = DB::table('forms')->insertGetId([
            'code' => 'product',
            'name' => 'Produk',
            'title' => 'Produk',
            'description' => 'Form contoh Dynamic CRUD di atas tabel demo_products.',
            'connection' => 'mysql',
            'table_name' => 'demo_products',
            'primary_key' => 'id',
            'key_type' => 'increment',
            'type' => 'single',
            'layout_columns' => 2,
            'scope_column' => 'branch_code',
            'use_soft_delete' => true,
            'use_audit_column' => true,
            'default_order_column' => 'id',
            'default_order_direction' => 'desc',
            'per_page' => 25,
            'permission_prefix' => 'product',
            'allow_create' => true, 'allow_edit' => true, 'allow_delete' => true,
            'allow_export' => true, 'allow_print' => true,
            'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $fields = [
            ['code', 'Kode Produk', 'text', 6, ['is_required' => true, 'is_unique' => true]],
            ['name', 'Nama Produk', 'text', 6, ['is_required' => true]],
            ['category_id', 'Kategori', 'select2', 6, [
                'data_source_type' => 'table', 'data_source' => 'demo_categories',
                'value_field' => 'id', 'label_field' => 'name',
                'data_filter' => json_encode(['is_active' => 1]), 'data_order_by' => 'name']],
            ['status', 'Status', 'select', 6, ['is_required' => true,
                'data_source_type' => 'enum', 'data_source' => 'demo_products', 'value_field' => 'status']],
            ['price', 'Harga', 'currency', 6, ['is_required' => true, 'default_value' => '0']],
            ['stock', 'Stok', 'number', 6, ['is_required' => true, 'default_value' => '0']],
            ['description', 'Deskripsi', 'textarea', 12, []],
            ['photo', 'Foto', 'image', 6, [
                'upload_path' => 'produk', 'allowed_extensions' => 'jpg,jpeg,png,webp',
                'max_file_size' => 2048]],
            ['is_active', 'Aktif', 'switch', 6, ['default_value' => '1']],
        ];

        $order = 0;
        foreach ($fields as [$name, $label, $type, $width, $extra]) {
            DB::table('form_fields')->insert([
                'form_id' => $formId, 'form_detail_id' => null,
                'field_name' => $name, 'label' => $label, 'input_type' => $type,
                'is_required' => $extra['is_required'] ?? false,
                'is_readonly' => false,
                'is_unique' => $extra['is_unique'] ?? false,
                'default_value' => $extra['default_value'] ?? null,
                'width' => $width, 'order_no' => ++$order,
                'data_source_type' => $extra['data_source_type'] ?? 'none',
                'data_source' => $extra['data_source'] ?? null,
                'value_field' => $extra['value_field'] ?? null,
                'label_field' => $extra['label_field'] ?? null,
                'data_filter' => $extra['data_filter'] ?? null,
                'data_order_by' => $extra['data_order_by'] ?? null,
                'upload_path' => $extra['upload_path'] ?? null,
                'allowed_extensions' => $extra['allowed_extensions'] ?? null,
                'max_file_size' => $extra['max_file_size'] ?? null,
                'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // --- kolom halaman list, termasuk satu kolom hasil join ---
        $listColumns = [
            ['Kode', 'column', 'code', null, null, null, 'text', 'left', '110px', true, true],
            ['Nama Produk', 'column', 'name', null, null, null, 'text', 'left', null, true, true],
            // relation: nama kategori diambil lewat join, bukan menampilkan category_id
            ['Kategori', 'relation', 'category_id', 'demo_categories', 'id', 'name', 'text', 'left', null, false, false],
            ['Harga', 'column', 'price', null, null, null, 'currency', 'right', '140px', true, true],
            ['Stok', 'column', 'stock', null, null, null, 'number', 'right', '80px', false, true],
            ['Status', 'column', 'status', null, null, null, 'badge', 'center', '110px', true, true],
            ['Aktif', 'column', 'is_active', null, null, null, 'boolean', 'center', '80px', false, true],
        ];

        $order = 0;
        foreach ($listColumns as [$label, $src, $col, $relTable, $relKey, $relLabel, $format, $align, $width, $searchable, $sortable]) {
            DB::table('form_list_columns')->insert([
                'form_id' => $formId, 'label' => $label, 'source_type' => $src,
                'column_name' => $col, 'relation_table' => $relTable,
                'relation_key' => $relKey, 'relation_label' => $relLabel,
                'format' => $format, 'align' => $align, 'width' => $width,
                'is_visible' => true, 'is_searchable' => $searchable, 'is_sortable' => $sortable,
                'order_no' => ++$order,
            ]);
        }

        DB::table('menus')->updateOrInsert(
            ['code' => 'demo.product'],
            [
                'parent_id' => null, 'name' => 'Produk', 'icon' => 'fas fa-box',
                'link_type' => 'form', 'target_value' => 'product',
                'permission_code' => 'product.view', 'order_no' => 10,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]
        );

        $this->command?->info("Form 'product' siap. Buka /forms/product");
    }
}
