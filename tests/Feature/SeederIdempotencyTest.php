<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MenuService;
use Database\Seeders\MetadataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Seeder metadata harus aman dijalankan berulang.
 *
 * Menambah permission baru di seeder tidak boleh menuntut `migrate:fresh` —
 * di produksi itu berarti membuang seluruh data.
 */
class SeederIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function jumlah(): array
    {
        return [
            'roles' => DB::table('roles')->count(),
            'permissions' => DB::table('permissions')->count(),
            'role_permissions' => DB::table('role_permissions')->count(),
            'menus' => DB::table('menus')->count(),
            'settings' => DB::table('settings')->count(),
            'users' => DB::table('users')->count(),
            'user_roles' => DB::table('user_roles')->count(),
            'data_sources' => DB::table('data_sources')->count(),
            'help_articles' => DB::table('help_articles')->count(),
        ];
    }

    #[Test]
    public function menjalankan_seeder_berulang_tidak_menggandakan_apa_pun(): void
    {
        $this->seed(MetadataSeeder::class);
        $pertama = $this->jumlah();

        $this->seed(MetadataSeeder::class);
        $this->seed(MetadataSeeder::class);

        $this->assertSame($pertama, $this->jumlah(), 'seeder menggandakan baris saat dijalankan ulang');
    }

    #[Test]
    public function password_yang_sudah_diganti_tidak_dikembalikan_ke_bawaan(): void
    {
        $this->seed(MetadataSeeder::class);

        DB::table('users')->where('email', 'admin@example.com')
            ->update(['password' => Hash::make('PasswordBaru#99')]);

        $this->seed(MetadataSeeder::class);

        $user = DB::table('users')->where('email', 'admin@example.com')->first();

        $this->assertTrue(Hash::check('PasswordBaru#99', $user->password));
        $this->assertFalse(Hash::check('Admin#12345', $user->password),
            'seeder mengembalikan password ke bawaan — lubang keamanan di produksi');
    }

    #[Test]
    public function nilai_setelan_yang_sudah_disunting_tidak_ditimpa(): void
    {
        $this->seed(MetadataSeeder::class);

        DB::table('settings')->where('key_name', 'app_name')->update(['value' => 'Perusahaan Saya']);

        $this->seed(MetadataSeeder::class);

        $this->assertSame(
            'Perusahaan Saya',
            DB::table('settings')->where('key_name', 'app_name')->value('value')
        );
    }

    #[Test]
    public function permission_baru_ikut_diberikan_ke_superadmin(): void
    {
        $this->seed(MetadataSeeder::class);

        // Permission yang muncul belakangan, mis. dari generator CRUD.
        DB::table('permissions')->insert([
            'code' => 'produk.view', 'name' => 'Produk: view', 'group_name' => 'Demo',
            'is_system' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->seed(MetadataSeeder::class);

        $superadmin = DB::table('roles')->where('code', 'superadmin')->value('id');
        $permission = DB::table('permissions')->where('code', 'produk.view')->value('id');

        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $superadmin, 'permission_id' => $permission,
        ]);
    }

    #[Test]
    public function menu_baru_dari_seeder_langsung_terlihat_di_sidebar(): void
    {
        // Pohon menu di-cache `rememberForever`. Menjalankan seeder pada
        // aplikasi yang sudah berjalan harus membuang cache itu — kalau tidak,
        // menu barunya tidak muncul dan tidak ada petunjuk kenapa.
        Cache::forever('menu.tree', []);

        $this->seed(MetadataSeeder::class);

        $admin = User::where('email', 'admin@example.com')->first();
        $menus = app(MenuService::class)->treeFor($admin)
            ->flatMap(fn ($menu) => $menu->children->pluck('name'))
            ->all();

        $this->assertContains('Pengaturan', $menus, 'cache menu tidak dibuang oleh seeder');
    }

    #[Test]
    public function seeder_menghasilkan_login_yang_terdokumentasi(): void
    {
        $this->seed(MetadataSeeder::class);

        $user = DB::table('users')->where('email', 'admin@example.com')->first();

        $this->assertNotNull($user, 'user superadmin tidak dibuat');
        $this->assertTrue(Hash::check('Admin#12345', $user->password),
            'password bawaan berbeda dari yang tertulis di README');
        $this->assertTrue((bool) $user->is_active);
    }
}
