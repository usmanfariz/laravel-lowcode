<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MetadataSeeder extends Seeder
{
    /**
     * Seeder ini idempoten: aman dijalankan berulang tanpa perlu
     * `migrate:fresh`. Baris yang sudah ada diperbarui, bukan disisipkan lagi,
     * sehingga menambah permission baru di sini tidak menuntut reset database.
     *
     * Password superadmin sengaja TIDAK ditimpa ulang — menjalankan seeder
     * lagi tidak boleh mengembalikan password ke bawaan.
     */
    public function run(): void
    {
        $now = now();

        // -----------------------------------------------------------
        // Roles
        // -----------------------------------------------------------
        $roles = [
            ['superadmin', 'Super Admin', 'Akses penuh termasuk builder dan raw query', 'all', true],
            ['admin', 'Administrator', 'Mengelola data dan laporan, tanpa akses builder', 'all', true],
            ['staff', 'Staff', 'Hanya data unit sendiri', 'branch', false],
        ];

        foreach ($roles as [$code, $name, $description, $scope, $isSystem]) {
            DB::table('roles')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $name, 'description' => $description,
                    'data_scope' => $scope, 'is_system' => $isSystem, 'is_active' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]
            );
        }

        $superadminId = DB::table('roles')->where('code', 'superadmin')->value('id');

        // -----------------------------------------------------------
        // Permissions
        // -----------------------------------------------------------
        $permissions = [
            ['system.builder.form',   'Kelola Form Builder',   'Sistem'],
            ['system.builder.report', 'Kelola Report Builder', 'Sistem'],
            ['system.raw_query',      'Gunakan Raw Query',     'Sistem'],
            ['system.data_source',    'Kelola Sumber Data',    'Sistem'],
            ['system.menu',           'Kelola Menu',           'Sistem'],
            ['system.dashboard',      'Atur Dashboard',        'Sistem'],
            ['system.setting',        'Kelola Pengaturan',     'Sistem'],
            ['system.activity_log',   'Lihat Log Aktivitas',   'Sistem'],
            ['user.view',             'Lihat User',            'Pengguna'],
            ['user.create',           'Tambah User',           'Pengguna'],
            ['user.edit',             'Ubah User',             'Pengguna'],
            ['user.delete',           'Hapus User',            'Pengguna'],
            ['role.view',             'Lihat Role',            'Pengguna'],
            ['role.create',           'Tambah Role',           'Pengguna'],
            ['role.edit',             'Ubah Role',             'Pengguna'],
            ['role.delete',           'Hapus Role',            'Pengguna'],
        ];

        foreach ($permissions as [$code, $name, $group]) {
            DB::table('permissions')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $name, 'group_name' => $group,
                    'is_system' => true, 'created_at' => $now, 'updated_at' => $now,
                ]
            );
        }

        // Superadmin memperoleh seluruh permission yang ada, termasuk yang
        // dibuat generator setelah seeder pertama dijalankan.
        foreach (DB::table('permissions')->pluck('id') as $permissionId) {
            DB::table('role_permissions')->updateOrInsert(
                ['role_id' => $superadminId, 'permission_id' => $permissionId], []
            );
        }

        // -----------------------------------------------------------
        // User superadmin
        // Ganti password ini segera setelah instalasi.
        // -----------------------------------------------------------
        $userId = DB::table('users')->where('email', 'admin@example.com')->value('id');

        if ($userId === null) {
            $userId = DB::table('users')->insertGetId([
                'username' => 'superadmin',
                'name' => 'Super Admin',
                'email' => 'admin@example.com',
                'password' => Hash::make('Admin#12345'),
                'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        // Password sengaja tidak ditimpa bila user sudah ada: menjalankan
        // seeder ulang tidak boleh mengembalikannya ke bawaan.

        DB::table('user_roles')->updateOrInsert(
            ['user_id' => $userId, 'role_id' => $superadminId], []
        );

        // -----------------------------------------------------------
        // Whitelist sumber data
        // Tabel sistem sengaja tidak dibuka untuk builder.
        // -----------------------------------------------------------
        foreach ([
            ['users', 'Pengguna', json_encode(['password', 'remember_token'])],
            ['roles', 'Role', null],
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

        // -----------------------------------------------------------
        // Menu awal
        // -----------------------------------------------------------
        DB::table('menus')->updateOrInsert(
            ['code' => 'dashboard'],
            [
                'parent_id' => null, 'name' => 'Dashboard',
                'icon' => 'fas fa-tachometer-alt', 'link_type' => 'route',
                'target_value' => 'dashboard', 'permission_code' => null,
                'order_no' => 1, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]
        );

        DB::table('menus')->updateOrInsert(
            ['code' => 'system'],
            [
                'parent_id' => null, 'name' => 'Sistem',
                'icon' => 'fas fa-cogs', 'link_type' => 'header',
                'target_value' => null, 'permission_code' => 'system.setting',
                'order_no' => 90, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]
        );

        $systemId = DB::table('menus')->where('code', 'system')->value('id');

        $systemMenus = [
            ['system.users', 'Pengguna', 'fas fa-users', 'users.index', 'user.view', 1],
            ['system.roles', 'Role & Izin', 'fas fa-user-shield', 'roles.index', 'role.view', 2],
            ['system.menus', 'Menu', 'fas fa-bars', 'menus.index', 'system.menu', 3],
            ['system.dashboard', 'Atur Dashboard', 'fas fa-th-large', 'builder.dashboard.index', 'system.dashboard', 4],
            ['system.form_builder', 'Form Builder', 'fas fa-wpforms', 'builder.forms.index', 'system.builder.form', 5],
            ['system.report_builder', 'Report Builder', 'fas fa-chart-bar', 'builder.reports.index', 'system.builder.report', 6],
            ['system.data_sources', 'Sumber Data', 'fas fa-database', 'data-sources.index', 'system.data_source', 7],
            ['system.activity_logs', 'Log Aktivitas', 'fas fa-history', 'activity-logs.index', 'system.activity_log', 8],
        ];

        foreach ($systemMenus as [$code, $name, $icon, $target, $permission, $order]) {
            DB::table('menus')->updateOrInsert(
                ['code' => $code],
                [
                    'parent_id' => $systemId, 'name' => $name, 'icon' => $icon,
                    'link_type' => 'route', 'target_value' => $target,
                    'permission_code' => $permission, 'order_no' => $order,
                    'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]
            );
        }

        // -----------------------------------------------------------
        // Pengaturan awal
        // -----------------------------------------------------------
        $settings = [
            ['general', 'app_name', 'Low-Code Platform', 'string', 'Nama Aplikasi', true],
            ['general', 'app_logo', null, 'file', 'Logo Aplikasi', true],
            ['general', 'date_format', 'd/m/Y', 'string', 'Format Tanggal', false],
            ['general', 'per_page', '25', 'integer', 'Baris per Halaman', false],
            ['security', 'allow_raw_query', '0', 'boolean', 'Izinkan Raw Query pada Report', false],
        ];

        foreach ($settings as [$group, $key, $value, $type, $label, $public]) {
            // Nilai yang sudah disetel admin tidak ditimpa; hanya label dan
            // tipenya yang disegarkan.
            $exists = DB::table('settings')->where('key_name', $key)->exists();

            DB::table('settings')->updateOrInsert(
                ['key_name' => $key],
                array_merge([
                    'group_name' => $group, 'value_type' => $type,
                    'label' => $label, 'is_public' => $public,
                    'created_at' => $now, 'updated_at' => $now,
                ], $exists ? [] : ['value' => $value])
            );
        }

        // -----------------------------------------------------------
        // Widget dashboard bawaan
        // Angka yang dulu di-hardcode di Blade, kini jadi metadata yang
        // bisa disunting atau dibuang lewat Atur Dashboard.
        // -----------------------------------------------------------
        $widgets = [
            ['total_pengguna', 'Pengguna', 'users', 'fas fa-users', 'info', 'user.view', 1],
            ['total_role', 'Role', 'roles', 'fas fa-user-shield', 'success', 'role.view', 2],
        ];

        foreach ($widgets as [$code, $title, $table, $icon, $color, $permission, $order]) {
            DB::table('dashboard_widgets')->updateOrInsert(
                ['code' => $code],
                [
                    'title' => $title, 'type' => 'stat',
                    'icon' => $icon, 'color' => $color, 'width' => 3,
                    'source_table' => $table, 'source_column' => null,
                    'aggregate' => 'count', 'format' => 'number',
                    'permission_code' => $permission, 'order_no' => $order,
                    'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]
            );
        }

        setting_flush();
    }
}
