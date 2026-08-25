<?php

namespace Tests;

use App\Models\DataSource;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * Basis untuk test yang butuh metadata.
 *
 * Membuat satu tabel bisnis kecil beserta whitelist-nya, supaya tiap test
 * tidak perlu menyiapkan sendiri. Sengaja tidak memakai tabel demo: test
 * harus tetap lulus walau migrasi demo dihapus.
 */
abstract class MetadataTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createBusinessTable();
        $this->registerDataSources();
        $this->admin = $this->makeUser('admin', ['*']);
    }

    private function createBusinessTable(): void
    {
        Schema::create('t_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->string('secret', 100)->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->integer('qty')->default(0);
            $table->string('branch_code', 50)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('t_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
        });

        Schema::create('t_hidden', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
        });

        DB::table('t_categories')->insert([
            ['name' => 'Alpha'],
            ['name' => 'Beta'],
        ]);
    }

    private function registerDataSources(): void
    {
        // Nama koneksi mengikuti driver yang sedang dipakai test, bukan
        // 'mysql' mati — supaya suite ini jalan di SQLite maupun MySQL.
        $connection = config('database.default');

        DataSource::create([
            'connection' => $connection, 'table_name' => 't_items', 'label' => 'Item',
            'primary_key' => 'id', 'is_readable' => true, 'is_writable' => true,
            'blocked_columns' => ['secret'], 'is_active' => true,
        ]);

        DataSource::create([
            'connection' => $connection, 'table_name' => 't_categories', 'label' => 'Kategori',
            'primary_key' => 'id', 'is_readable' => true, 'is_writable' => false,
            'blocked_columns' => null, 'is_active' => true,
        ]);

        // t_hidden sengaja TIDAK didaftarkan — dipakai menguji whitelist.
    }

    /**
     * @param  array<int, string>  $permissions  '*' berarti seluruh permission
     */
    protected function makeUser(string $username, array $permissions = [], string $scope = 'all', ?string $scopeValue = null): User
    {
        $user = User::create([
            'username' => $username,
            'name' => ucfirst($username),
            'email' => $username.'@example.test',
            'password' => 'rahasia123',
            'is_active' => true,
            'scope_value' => $scopeValue,
        ]);

        $role = Role::create([
            'code' => 'role_'.$username, 'name' => 'Role '.$username,
            'data_scope' => $scope, 'is_system' => false, 'is_active' => true,
        ]);

        $codes = $permissions === ['*']
            ? ['item.view', 'item.create', 'item.edit', 'item.delete', 'item.export',
                'item.print', 'system.raw_query', 'system.builder.form', 'system.builder.report']
            : $permissions;

        foreach ($codes as $code) {
            $permission = Permission::firstOrCreate(
                ['code' => $code],
                ['name' => $code, 'group_name' => 'Test', 'is_system' => false]
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user->roles()->attach($role->id);

        return $user;
    }

    /** Definisi form minimal di atas t_items. */
    protected function makeForm(array $overrides = []): \App\Models\Form
    {
        $form = \App\Models\Form::create(array_merge([
            'code' => 'item', 'name' => 'Item', 'title' => 'Item',
            'connection' => config('database.default'), 'table_name' => 't_items', 'primary_key' => 'id',
            'key_type' => 'increment', 'type' => 'single', 'layout_columns' => 2,
            'use_soft_delete' => true, 'use_audit_column' => true,
            'default_order_column' => 'id', 'default_order_direction' => 'desc',
            'per_page' => 25, 'permission_prefix' => 'item',
            'allow_create' => true, 'allow_edit' => true, 'allow_delete' => true,
            'allow_export' => true, 'allow_print' => true, 'is_active' => true,
        ], $overrides));

        $fields = [
            ['code', 'Kode', 'text', ['is_required' => true, 'is_unique' => true]],
            ['name', 'Nama', 'text', ['is_required' => true]],
            ['category_id', 'Kategori', 'select2', [
                'data_source_type' => 'table', 'data_source' => 't_categories',
                'value_field' => 'id', 'label_field' => 'name']],
            ['price', 'Harga', 'currency', []],
            ['qty', 'Jumlah', 'number', []],
        ];

        $order = 0;
        foreach ($fields as [$name, $label, $type, $extra]) {
            DB::table('form_fields')->insert(array_merge([
                'form_id' => $form->id, 'form_detail_id' => null,
                'field_name' => $name, 'label' => $label, 'input_type' => $type,
                'is_required' => false, 'is_readonly' => false, 'is_unique' => false,
                'width' => 6, 'order_no' => ++$order,
                'data_source_type' => 'none', 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ], $extra));
        }

        return $form->fresh();
    }
}
