<?php

namespace Tests\Feature;

use App\Exceptions\DataSourceException;
use App\Models\DataSource;
use App\Services\DataSourceResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\MetadataTestCase;

class DataSourceResolverTest extends MetadataTestCase
{
    private DataSourceResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(DataSourceResolver::class);
    }

    #[Test]
    public function tabel_dalam_whitelist_boleh_dibaca(): void
    {
        $this->assertSame('t_items', $this->resolver->assertReadable('t_items')->table_name);
    }

    #[Test]
    public function tabel_di_luar_whitelist_ditolak(): void
    {
        $this->expectException(DataSourceException::class);
        $this->resolver->assertReadable('t_hidden');
    }

    #[Test]
    public function tabel_sistem_tidak_pernah_otomatis_masuk_whitelist(): void
    {
        // users ada di database tapi tidak didaftarkan — engine tidak boleh
        // menyentuhnya hanya karena tabelnya ada.
        $this->expectException(DataSourceException::class);
        $this->resolver->assertReadable('users');
    }

    #[Test]
    public function tabel_nonaktif_ditolak(): void
    {
        DataSource::where('table_name', 't_items')->update(['is_active' => false]);

        $this->expectException(DataSourceException::class);
        $this->resolver->assertReadable('t_items');
    }

    #[Test]
    public function tabel_baca_saja_tidak_boleh_ditulis(): void
    {
        $this->expectExceptionMessage("Tabel 't_categories' tidak diizinkan ditulis.");
        $this->resolver->assertWritable('t_categories');
    }

    #[Test]
    #[DataProvider('namaTabelJahat')]
    public function injeksi_nama_tabel_ditolak(string $table): void
    {
        $this->expectException(DataSourceException::class);
        $this->resolver->assertReadable($table);
    }

    public static function namaTabelJahat(): array
    {
        return [
            'pernyataan kedua' => ['t_items; DROP TABLE users'],
            'backtick' => ['t_items` WHERE 1=1 -- '],
            'spasi' => ['t_items t'],
            'titik' => ['db.t_items'],
            'kosong' => [''],
        ];
    }

    #[Test]
    public function blocked_columns_tidak_ikut_terbaca(): void
    {
        $columns = $this->resolver->allowedColumns('t_items');

        $this->assertContains('code', $columns);
        $this->assertNotContains('secret', $columns, 'kolom yang diblokir bocor ke daftar kolom');
    }

    #[Test]
    public function kolom_yang_diblokir_ditolak(): void
    {
        $this->expectExceptionMessage("Kolom 'secret' pada tabel 't_items' diblokir.");
        $this->resolver->assertColumn('t_items', 'secret');
    }

    #[Test]
    public function kolom_yang_tidak_ada_ditolak(): void
    {
        $this->expectExceptionMessage("Kolom 'tidak_ada' tidak ada pada tabel 't_items'.");
        $this->resolver->assertColumn('t_items', 'tidak_ada');
    }

    #[Test]
    public function injeksi_nama_kolom_ditolak(): void
    {
        $this->expectException(DataSourceException::class);
        $this->resolver->assertColumn('t_items', 'id) UNION SELECT secret FROM t_items --');
    }

    #[Test]
    public function opsi_hanya_menarik_kolom_yang_diizinkan(): void
    {
        $options = $this->resolver->options('t_categories', 'id', 'name');

        $this->assertCount(2, $options);
        $this->assertSame(['value', 'label'], array_keys($options->first()));
        $this->assertSame('Alpha', $options->first()['label']);
    }

    #[Test]
    public function opsi_menolak_kolom_yang_diblokir(): void
    {
        $this->expectException(DataSourceException::class);
        $this->resolver->options('t_items', 'id', 'secret');
    }

    #[Test]
    public function perubahan_blocked_columns_berlaku_setelah_cache_dibuang(): void
    {
        $this->assertContains('name', $this->resolver->allowedColumns('t_items'));

        DataSource::where('table_name', 't_items')->update(['blocked_columns' => ['secret', 'name']]);
        $this->resolver->flushColumns('t_items');

        // Resolver menyimpan hasil per-request, jadi instance baru dipakai
        // untuk memastikan yang diuji adalah cache, bukan memoisasi.
        $fresh = new DataSourceResolver;

        $this->assertNotContains('name', $fresh->allowedColumns('t_items'));
    }
}
