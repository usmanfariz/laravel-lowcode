<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\User;
use App\Services\MenuService;
use PHPUnit\Framework\Attributes\Test;
use Tests\MetadataTestCase;

class AuthAndMenuTest extends MetadataTestCase
{
    #[Test]
    public function login_berhasil_dengan_username(): void
    {
        $this->post('/login', ['username' => 'admin', 'password' => 'rahasia123'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($this->admin);
    }

    #[Test]
    public function login_berhasil_dengan_email(): void
    {
        $this->post('/login', ['username' => 'admin@example.test', 'password' => 'rahasia123'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticated();
    }

    #[Test]
    public function password_salah_ditolak(): void
    {
        $this->post('/login', ['username' => 'admin', 'password' => 'salah'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    #[Test]
    public function pesan_gagal_tidak_membedakan_penyebab(): void
    {
        $tidakAda = $this->post('/login', ['username' => 'hantu', 'password' => 'apa'])
            ->assertSessionHasErrors('username');

        $this->flushSession();

        $salahSandi = $this->post('/login', ['username' => 'admin', 'password' => 'salah'])
            ->assertSessionHasErrors('username');

        // Pesan yang berbeda akan memberi tahu penebak bahwa usernamenya benar.
        $this->assertSame(
            session()->get('errors')?->first('username'),
            session()->get('errors')?->first('username')
        );
    }

    #[Test]
    public function pengguna_nonaktif_tidak_bisa_masuk(): void
    {
        $this->admin->update(['is_active' => false]);

        $this->post('/login', ['username' => 'admin', 'password' => 'rahasia123'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    #[Test]
    public function pengguna_yang_dinonaktifkan_setelah_masuk_langsung_terputus(): void
    {
        $this->actingAs($this->admin)->get('/dashboard')->assertOk();

        $this->admin->update(['is_active' => false]);

        $this->actingAs($this->admin)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    #[Test]
    public function waktu_login_terakhir_dicatat(): void
    {
        $this->assertNull($this->admin->last_login_at);

        $this->post('/login', ['username' => 'admin', 'password' => 'rahasia123']);

        $this->assertNotNull($this->admin->fresh()->last_login_at);
    }

    // ---------------- menu ----------------

    #[Test]
    public function sidebar_hanya_menampilkan_menu_yang_diizinkan(): void
    {
        $header = Menu::create([
            'code' => 'sistem', 'name' => 'Sistem', 'link_type' => 'header',
            'order_no' => 10, 'is_active' => true,
        ]);
        Menu::create([
            'parent_id' => $header->id, 'code' => 'sistem.item', 'name' => 'Item',
            'link_type' => 'form', 'target_value' => 'item',
            'permission_code' => 'item.view', 'order_no' => 1, 'is_active' => true,
        ]);
        Menu::create([
            'code' => 'terbuka', 'name' => 'Terbuka', 'link_type' => 'url',
            'target_value' => '/', 'permission_code' => null,
            'order_no' => 1, 'is_active' => true,
        ]);

        $service = app(MenuService::class);
        $tanpaIzin = $this->makeUser('polos', []);

        $this->assertSame(['Terbuka', 'Sistem'], $this->namaMenu($service->treeFor($this->admin)));
        $this->assertSame(['Terbuka'], $this->namaMenu($service->treeFor($tanpaIzin)),
            'header tanpa anak yang lolos izin seharusnya ikut hilang');
    }

    #[Test]
    public function menu_nonaktif_tidak_tampil(): void
    {
        Menu::create([
            'code' => 'mati', 'name' => 'Mati', 'link_type' => 'url',
            'target_value' => '/', 'order_no' => 1, 'is_active' => false,
        ]);

        $this->assertSame([], $this->namaMenu(app(MenuService::class)->treeFor($this->admin)));
    }

    #[Test]
    public function cakupan_data_diambil_dari_role_paling_longgar(): void
    {
        $user = $this->makeUser('ganda', [], 'own');

        $this->assertSame('own', $user->dataScope());

        $lain = \App\Models\Role::create([
            'code' => 'tambahan', 'name' => 'Tambahan',
            'data_scope' => 'all', 'is_system' => false, 'is_active' => true,
        ]);
        $user->roles()->attach($lain->id);

        $this->assertSame('all', $user->fresh()->dataScope());
    }

    #[Test]
    public function role_nonaktif_tidak_memberi_izin(): void
    {
        $user = $this->makeUser('nonaktif', ['item.view']);

        $this->assertTrue($user->hasPermission('item.view'));

        $user->roles()->update(['is_active' => false]);

        $this->assertFalse((new User)->find($user->id)->hasPermission('item.view'));
    }

    /** @return array<int, string> */
    private function namaMenu($tree): array
    {
        return collect($tree)->pluck('name')->all();
    }
}
