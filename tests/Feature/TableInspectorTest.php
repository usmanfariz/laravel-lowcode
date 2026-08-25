<?php

namespace Tests\Feature;

use App\Models\DataSource;
use App\Services\Generator\ColumnMapper;
use App\Services\Generator\FormGenerator;
use App\Services\Generator\TableInspector;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\MysqlTestCase;

/**
 * Menutup dua bagian yang tidak bisa diuji di SQLite: pembacaan
 * `information_schema` dan opsi dari kolom `ENUM`.
 */
class TableInspectorTest extends MysqlTestCase
{
    private TableInspector $inspector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inspector = app(TableInspector::class);

        Schema::create('t_kategori', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
        });

        Schema::create('t_produk', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique()->comment('Kode unik produk');
            $table->string('nama', 150);
            $table->string('email', 150)->nullable();
            $table->string('website', 255)->nullable();
            $table->text('alamat')->nullable();
            $table->foreignId('kategori_id')->nullable()->constrained('t_kategori');
            $table->decimal('harga', 15, 2)->default(0);
            $table->decimal('diskon_percent', 5, 2)->nullable();
            $table->integer('rating')->nullable();
            $table->enum('status', ['draft', 'terbit', 'arsip'])->default('draft');
            $table->string('foto', 255)->nullable();
            $table->string('kontrak_file', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('tanggal_gabung')->nullable();
            $table->dateTime('kontak_terakhir_at')->nullable();
            $table->string('rahasia', 100)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        foreach ([['t_produk', 'Produk'], ['t_kategori', 'Kategori']] as [$table, $label]) {
            DataSource::create([
                'connection' => 'mysql', 'table_name' => $table, 'label' => $label,
                'primary_key' => 'id', 'is_readable' => true, 'is_writable' => true,
                'blocked_columns' => $table === 't_produk' ? ['rahasia'] : null,
                'is_active' => true,
            ]);
        }
    }

    #[Test]
    public function membaca_kolom_beserta_sifatnya(): void
    {
        $columns = $this->inspector->columns('t_produk')->keyBy('name');

        $this->assertTrue($columns['kode']['is_unique']);
        $this->assertFalse($columns['nama']['is_unique']);
        $this->assertTrue($columns['id']['is_primary']);
        $this->assertTrue($columns['id']['is_auto']);
        $this->assertFalse($columns['nama']['nullable']);
        $this->assertTrue($columns['email']['nullable']);
        $this->assertSame('Kode unik produk', $columns['kode']['comment']);
        $this->assertSame(150, $columns['nama']['length']);
    }

    #[Test]
    public function kolom_yang_diblokir_tidak_ikut_terbaca(): void
    {
        $names = $this->inspector->columns('t_produk')->pluck('name');

        $this->assertContains('nama', $names);
        $this->assertNotContains('rahasia', $names, 'kolom terblokir bocor lewat inspector');
    }

    #[Test]
    public function foreign_key_terdeteksi_lengkap_dengan_tujuannya(): void
    {
        $kolom = $this->inspector->columns('t_produk')->firstWhere('name', 'kategori_id');

        $this->assertSame(['table' => 't_kategori', 'column' => 'id'], $kolom['references']);
    }

    #[Test]
    public function nilai_enum_dibaca_dari_definisi_kolom(): void
    {
        $kolom = $this->inspector->columns('t_produk')->firstWhere('name', 'status');

        $this->assertSame(['draft', 'terbit', 'arsip'], $kolom['enum_values']);
    }

    #[Test]
    public function tabel_di_luar_whitelist_tidak_bisa_diperiksa(): void
    {
        $this->expectException(\App\Exceptions\DataSourceException::class);
        $this->inspector->columns('users');
    }

    #[Test]
    public function daftar_tabel_fisik_melewati_whitelist_dengan_sengaja(): void
    {
        // Halaman pengelola Sumber Data justru butuh melihat yang belum terdaftar.
        $names = $this->inspector->physicalTables()->pluck('name');

        $this->assertContains('t_produk', $names);
        $this->assertContains('users', $names, 'tabel belum terdaftar seharusnya tetap terlihat di sini');
    }

    #[Test]
    public function keberadaan_tabel_bisa_diperiksa(): void
    {
        $this->assertTrue($this->inspector->tableExists('t_produk'));
        $this->assertFalse($this->inspector->tableExists('tabel_yang_tidak_ada'));
        $this->assertFalse($this->inspector->tableExists('bukan nama; DROP TABLE users'));
    }

    // ---------------- pemetaan kolom → field ----------------

    #[Test]
    public function jenis_input_diturunkan_dari_nama_dan_tipe(): void
    {
        $fields = app(FormGenerator::class)->preview('t_produk')->keyBy('field_name');

        $harapan = [
            'kode' => 'text',
            'email' => 'email',
            'website' => 'url',
            'alamat' => 'textarea',
            'kategori_id' => 'select2',
            'harga' => 'currency',
            'diskon_percent' => 'percentage',
            'rating' => 'number',
            'status' => 'select',
            'foto' => 'image',
            'kontrak_file' => 'file',
            'is_active' => 'switch',
            'tanggal_gabung' => 'date',
            'kontak_terakhir_at' => 'datetime',
        ];

        foreach ($harapan as $kolom => $jenis) {
            $this->assertSame($jenis, $fields[$kolom]['input_type'], "pemetaan kolom '{$kolom}' meleset");
        }
    }

    #[Test]
    public function kolom_yang_diurus_engine_tidak_jadi_field(): void
    {
        $names = app(FormGenerator::class)->preview('t_produk')->pluck('field_name');

        foreach (['id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'rahasia'] as $kolom) {
            $this->assertNotContains($kolom, $names, "kolom '{$kolom}' seharusnya tidak jadi field");
        }
    }

    #[Test]
    public function relasi_menebak_kolom_labelnya(): void
    {
        $field = app(FormGenerator::class)->preview('t_produk')->firstWhere('field_name', 'kategori_id');

        $this->assertSame('table', $field['data_source_type']);
        $this->assertSame('t_kategori', $field['data_source']);
        $this->assertSame('id', $field['value_field']);
        $this->assertSame('nama', $field['label_field']);
    }

    #[Test]
    public function kolom_wajib_dan_unik_ikut_terbawa(): void
    {
        $fields = app(FormGenerator::class)->preview('t_produk')->keyBy('field_name');

        $this->assertTrue($fields['kode']['is_required']);
        $this->assertTrue($fields['kode']['is_unique']);
        $this->assertFalse($fields['email']['is_required']);
        $this->assertSame('max:50', $fields['kode']['validation']);
    }

    #[Test]
    public function petunjuk_nama_hanya_berlaku_bila_cocok_tipenya(): void
    {
        Schema::table('t_produk', function (Blueprint $table) {
            // Bernama seperti mata uang tapi bertipe bilangan bulat.
            $table->integer('total_item')->default(0);
        });
        app(\App\Services\DataSourceResolver::class)->flushColumns('t_produk');

        $field = app(FormGenerator::class)->preview('t_produk')->firstWhere('field_name', 'total_item');

        $this->assertSame('number', $field['input_type'],
            'petunjuk nama seharusnya kalah dari tipe data yang tidak cocok');
    }

    #[Test]
    public function generate_menghasilkan_form_yang_langsung_bisa_dipakai(): void
    {
        $user = \App\Models\User::create([
            'username' => 'gen', 'name' => 'Gen', 'email' => 'gen@example.test',
            'password' => 'rahasia123', 'is_active' => true,
        ]);

        $form = app(FormGenerator::class)->generate('t_produk', [
            'code' => 'produk', 'name' => 'Produk', 'title' => 'Produk',
            'permission_prefix' => 'produk', 'scope_column' => null, 'create_menu' => false,
        ], $user);

        // Flag disesuaikan struktur nyata, bukan disetel membabi buta.
        $this->assertTrue($form->use_soft_delete, 'deleted_at ada tapi soft delete tidak menyala');
        $this->assertTrue($form->use_audit_column, 'created_by ada tapi kolom audit tidak menyala');

        $this->assertGreaterThan(0, DB::table('form_fields')->where('form_id', $form->id)->count());
        $this->assertGreaterThan(0, DB::table('form_list_columns')->where('form_id', $form->id)->count());

        // Tanpa permission, form baru selalu 403 bahkan bagi superadmin.
        foreach (['view', 'create', 'edit', 'delete', 'export', 'print'] as $aksi) {
            $this->assertDatabaseHas('permissions', ['code' => "produk.{$aksi}"]);
        }
    }

    #[Test]
    public function opsi_enum_dibaca_renderer_dari_kolomnya(): void
    {
        $user = \App\Models\User::create([
            'username' => 'gen', 'name' => 'Gen', 'email' => 'gen@example.test',
            'password' => 'rahasia123', 'is_active' => true,
        ]);

        $form = app(FormGenerator::class)->generate('t_produk', [
            'code' => 'produk', 'name' => 'Produk', 'title' => 'Produk',
            'permission_prefix' => 'produk', 'scope_column' => null, 'create_menu' => false,
        ], $user);

        $field = $form->fields->firstWhere('field_name', 'status');
        $options = app(\App\Services\Form\FormRenderer::class)->optionsFor($field);

        $this->assertSame(['draft', 'terbit', 'arsip'], $options->pluck('value')->all());
    }

    #[Test]
    public function kolom_yang_diurus_engine_terdaftar_sebagai_konstanta(): void
    {
        // Daftar ini dipakai di beberapa tempat; perubahannya harus disengaja.
        $this->assertContains('created_at', ColumnMapper::MANAGED);
        $this->assertContains('deleted_at', ColumnMapper::MANAGED);
        $this->assertContains('created_by', ColumnMapper::MANAGED);
        $this->assertContains('remember_token', ColumnMapper::MANAGED);
    }
}
