<?php

namespace Database\Seeders;

use App\Services\MenuService;
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
            ['system.help',           'Kelola Bantuan',        'Sistem'],
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
            ['system.help', 'Bantuan', 'fas fa-question-circle', 'help-articles.index', 'system.help', 9],
            ['system.settings', 'Pengaturan', 'fas fa-sliders-h', 'settings.index', 'system.setting', 10],
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
        // Kolom tampilan (label, tipe, pilihan, urutan) selalu disegarkan;
        // `value` hanya diisi saat barisnya baru dibuat, supaya setelan yang
        // sudah diubah admin tidak dikembalikan ke bawaan.
        //
        // Urutan tampil mengikuti urutan penulisan di dalam kelompoknya.
        $settings = [
            // ---------------- Aplikasi ----------------
            [
                'group' => 'general', 'key' => 'app_name', 'default' => 'Low-Code Platform',
                'type' => 'string', 'label' => 'Nama Aplikasi', 'public' => true,
                'description' => 'Muncul di judul tab peramban, sidebar, footer, dan halaman masuk.',
            ],
            [
                'group' => 'general', 'key' => 'app_logo', 'type' => 'file',
                'label' => 'Logo Aplikasi', 'public' => true,
                'description' => 'PNG/JPG maksimal 1 MB. Tampil di sidebar dan halaman masuk; kosong berarti memakai ikon bawaan.',
            ],
            [
                'group' => 'general', 'key' => 'app_favicon', 'type' => 'file',
                'label' => 'Favicon', 'public' => true,
                'description' => 'Ikon kecil di tab peramban. Ukuran ideal 32x32 piksel.',
            ],
            [
                'group' => 'general', 'key' => 'date_format', 'default' => 'd/m/Y',
                'type' => 'string', 'input' => 'select', 'label' => 'Format Tanggal',
                'description' => 'Dipakai saat menampilkan kolom tanggal di halaman daftar, report, ekspor, dan cetak.',
                'options' => [
                    'd/m/Y' => '31/12/2026', 'd-m-Y' => '31-12-2026',
                    'Y-m-d' => '2026-12-31', 'd M Y' => '31 Dec 2026',
                ],
            ],
            [
                'group' => 'general', 'key' => 'per_page', 'default' => '25', 'type' => 'integer',
                'label' => 'Baris per Halaman',
                'description' => 'Nilai bawaan untuk form dan report yang tidak menentukan sendiri.',
            ],
            [
                'group' => 'general', 'key' => 'footer_text', 'type' => 'string',
                'label' => 'Teks Footer',
                'description' => 'Tampil di sudut kanan footer. Kosong berarti menampilkan versi Laravel.',
            ],

            // ---------------- Perusahaan ----------------
            [
                'group' => 'company', 'key' => 'company_name', 'type' => 'string',
                'label' => 'Nama Perusahaan', 'public' => true,
                'description' => 'Dipakai di footer dan kop halaman cetak maupun PDF.',
            ],
            [
                'group' => 'company', 'key' => 'company_address', 'type' => 'string',
                'input' => 'textarea', 'label' => 'Alamat',
            ],
            ['group' => 'company', 'key' => 'company_phone', 'type' => 'string', 'label' => 'Telepon'],
            ['group' => 'company', 'key' => 'company_email', 'type' => 'string', 'label' => 'Email'],
            ['group' => 'company', 'key' => 'company_website', 'type' => 'string', 'label' => 'Situs Web'],
            ['group' => 'company', 'key' => 'company_tax_id', 'type' => 'string', 'label' => 'NPWP'],
            [
                'group' => 'company', 'key' => 'company_logo', 'type' => 'file',
                'label' => 'Logo Perusahaan',
                'description' => 'Logo pada kop cetak dan PDF. Kosong berarti memakai logo aplikasi.',
            ],

            // ---------------- Tampilan ----------------
            // Nilainya masuk ke atribut class AdminLTE. Karena isinya dibatasi
            // daftar pilihan di bawah, tidak ada teks bebas yang ikut ke HTML.
            [
                'group' => 'appearance', 'key' => 'sidebar_skin', 'default' => 'sidebar-dark-primary',
                'type' => 'string', 'input' => 'select', 'label' => 'Warna Sidebar',
                'options' => [
                    'sidebar-dark-primary' => 'Gelap - Biru',
                    'sidebar-dark-navy' => 'Gelap - Navy',
                    'sidebar-dark-olive' => 'Gelap - Hijau',
                    'sidebar-dark-maroon' => 'Gelap - Merah',
                    'sidebar-light-primary' => 'Terang - Biru',
                    'sidebar-light-olive' => 'Terang - Hijau',
                ],
            ],
            [
                'group' => 'appearance', 'key' => 'navbar_skin', 'default' => 'navbar-white navbar-light',
                'type' => 'string', 'input' => 'select', 'label' => 'Warna Navbar',
                'options' => [
                    'navbar-white navbar-light' => 'Putih',
                    'navbar-light bg-light' => 'Abu terang',
                    'navbar-dark bg-primary' => 'Biru',
                    'navbar-dark bg-navy' => 'Navy',
                    'navbar-dark bg-dark' => 'Gelap',
                ],
            ],

            // ---------------- Cetak & Ekspor ----------------
            [
                'group' => 'print', 'key' => 'print_show_header', 'default' => '1', 'type' => 'boolean',
                'label' => 'Tampilkan kop perusahaan',
                'description' => 'Memasang logo, nama, dan alamat perusahaan di atas halaman cetak dan PDF.',
            ],
            [
                'group' => 'print', 'key' => 'print_footer_note', 'type' => 'string',
                'label' => 'Catatan Kaki Cetak',
                'description' => 'Satu baris di bawah tabel pada halaman cetak dan PDF.',
            ],

            // ---------------- Keamanan ----------------
            [
                'group' => 'security', 'key' => 'allow_raw_query', 'default' => '0', 'type' => 'boolean',
                'label' => 'Izinkan Raw Query pada Report',
                'description' => 'Membuka mode raw di Report Builder. Biarkan mati kecuali benar-benar diperlukan.',
            ],
        ];

        $urutan = [];

        foreach ($settings as $row) {
            $group = $row['group'];
            $urutan[$group] = ($urutan[$group] ?? 0) + 1;

            $exists = DB::table('settings')->where('key_name', $row['key'])->exists();

            DB::table('settings')->updateOrInsert(
                ['key_name' => $row['key']],
                array_merge([
                    'group_name' => $group,
                    'value_type' => $row['type'],
                    'input_type' => $row['input'] ?? null,
                    'options' => isset($row['options'])
                        ? json_encode($row['options'], JSON_UNESCAPED_UNICODE)
                        : null,
                    'label' => $row['label'],
                    'description' => $row['description'] ?? null,
                    'is_public' => $row['public'] ?? false,
                    'order_no' => $urutan[$group],
                    'created_at' => $now, 'updated_at' => $now,
                ], $exists ? [] : ['value' => $row['default'] ?? null])
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

        // Basis pengetahuan chatbot bantuan. Ikut di sini, bukan di seeder demo:
        // petunjuk cara memakai aplikasi bukan data contoh yang boleh dibuang.
        $this->call(HelpArticleSeeder::class);

        // Kedua cache ini `rememberForever`. Tanpa dibuang di sini, menu dan
        // pengaturan yang baru ditambahkan seeder tidak akan terlihat sampai
        // ada yang menjalankan `cache:clear` — dan tidak ada yang tahu harus.
        setting_flush();
        app(MenuService::class)->flush();
    }
}
