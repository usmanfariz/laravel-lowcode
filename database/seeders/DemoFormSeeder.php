<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Contoh definisi form untuk menguji Dynamic Form Renderer.
 *
 * Sengaja dibangun di atas tabel `menus` — tabel yang sudah ada — supaya tidak
 * perlu membuat tabel bisnis baru, sesuai prinsip di docs/RANCANGAN.md §2.
 * Sumber datanya dibuka hanya untuk dibaca (`is_writable = false`), karena
 * tahap ini baru menggambar form, belum menyimpannya.
 *
 * Jalankan: php artisan db:seed --class=DemoFormSeeder
 */
class DemoFormSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        foreach ([
            ['menus', 'Menu', null],
            ['permissions', 'Permission', null],
        ] as [$table, $label, $blocked]) {
            DB::table('data_sources')->updateOrInsert(
                ['connection' => 'mysql', 'table_name' => $table],
                [
                    'label' => $label, 'primary_key' => 'id',
                    'is_readable' => true, 'is_writable' => false,
                    'blocked_columns' => $blocked, 'is_active' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]
            );
        }

        // Form baru wajib membawa permission-nya sendiri mengikuti konvensi
        // <permission_prefix>.<action> (docs/RANCANGAN.md §6). Tanpa ini,
        // form ter-render 403 walau penggunanya superadmin.
        foreach (['view', 'create', 'edit', 'delete', 'export', 'print'] as $action) {
            DB::table('permissions')->updateOrInsert(
                ['code' => "demo_menu.{$action}"],
                [
                    'name' => 'Demo Menu: '.$action,
                    'group_name' => 'Demo',
                    'is_system' => false,
                    'created_at' => $now, 'updated_at' => $now,
                ]
            );
        }

        // Berikan seluruhnya ke superadmin.
        $superadminId = DB::table('roles')->where('code', 'superadmin')->value('id');
        if ($superadminId) {
            foreach (DB::table('permissions')->where('code', 'like', 'demo_menu.%')->pluck('id') as $permissionId) {
                DB::table('role_permissions')->updateOrInsert(
                    ['role_id' => $superadminId, 'permission_id' => $permissionId], []
                );
            }
        }

        DB::table('forms')->where('code', 'demo_menu')->delete();

        $formId = DB::table('forms')->insertGetId([
            'code' => 'demo_menu',
            'name' => 'Demo Menu',
            'title' => 'Demo Form Renderer',
            'description' => 'Contoh form metadata-driven di atas tabel menus. '
                .'Menguji input teks, angka, switch, select statis, select enum, dan select tabel.',
            'connection' => 'mysql',
            'table_name' => 'menus',
            'primary_key' => 'id',
            'key_type' => 'increment',
            'type' => 'single',
            'layout_columns' => 2,
            'default_order_column' => 'order_no',
            'default_order_direction' => 'asc',
            'per_page' => 25,
            'permission_prefix' => 'demo_menu',
            'allow_create' => true, 'allow_edit' => true, 'allow_delete' => false,
            'allow_export' => false, 'allow_print' => false,
            'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $fields = [
            ['code', 'Kode', 'text', 6, ['is_required' => true, 'is_unique' => true,
                'placeholder' => 'mis. system.users', 'validation' => 'max:100']],
            ['name', 'Nama Menu', 'text', 6, ['is_required' => true]],
            ['icon', 'Ikon', 'text', 6, ['placeholder' => 'fas fa-users',
                'help_text' => 'Nama kelas Font Awesome.']],
            ['order_no', 'Urutan', 'number', 6, ['is_required' => true, 'default_value' => '0']],
            // enum: opsi dibaca dari definisi kolom di MySQL.
            ['link_type', 'Jenis Tautan', 'select', 6, ['is_required' => true,
                'data_source_type' => 'enum', 'data_source' => 'menus', 'value_field' => 'link_type']],
            // table: opsi ditarik dari tabel lain lewat whitelist data_sources.
            ['permission_code', 'Izin', 'select2', 6, [
                'data_source_type' => 'table', 'data_source' => 'permissions',
                'value_field' => 'code', 'label_field' => 'name', 'data_order_by' => 'code',
                'help_text' => 'Kosongkan agar menu terbuka untuk semua yang login.']],
            ['parent_id', 'Menu Induk', 'select2', 6, [
                'data_source_type' => 'table', 'data_source' => 'menus',
                'value_field' => 'id', 'label_field' => 'name', 'data_order_by' => 'order_no']],
            ['target_value', 'Tujuan', 'text', 6, ['placeholder' => 'nama route / URL / kode']],
            // static: opsi disimpan di form_field_options.
            ['open_new_tab', 'Buka di Tab Baru', 'radio', 6, ['data_source_type' => 'static']],
            ['is_active', 'Aktif', 'switch', 6, ['default_value' => '1']],
        ];

        $order = 0;
        foreach ($fields as [$name, $label, $type, $width, $extra]) {
            $fieldId = DB::table('form_fields')->insertGetId([
                'form_id' => $formId,
                'form_detail_id' => null,
                'field_name' => $name,
                'label' => $label,
                'input_type' => $type,
                'is_required' => $extra['is_required'] ?? false,
                'is_readonly' => false,
                'is_unique' => $extra['is_unique'] ?? false,
                'default_value' => $extra['default_value'] ?? null,
                'placeholder' => $extra['placeholder'] ?? null,
                'help_text' => $extra['help_text'] ?? null,
                'width' => $width,
                'order_no' => ++$order,
                'validation' => $extra['validation'] ?? null,
                'data_source_type' => $extra['data_source_type'] ?? 'none',
                'data_source' => $extra['data_source'] ?? null,
                'value_field' => $extra['value_field'] ?? null,
                'label_field' => $extra['label_field'] ?? null,
                'data_order_by' => $extra['data_order_by'] ?? null,
                'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            if ($name === 'open_new_tab') {
                DB::table('form_field_options')->insert([
                    ['form_field_id' => $fieldId, 'value' => '0', 'label' => 'Tidak',
                        'order_no' => 1, 'is_default' => true, 'is_active' => true],
                    ['form_field_id' => $fieldId, 'value' => '1', 'label' => 'Ya',
                        'order_no' => 2, 'is_default' => false, 'is_active' => true],
                ]);
            }
        }

        $this->command?->info("Form 'demo_menu' dibuat dengan {$order} field.");
    }
}
