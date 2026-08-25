<?php

namespace Tests\Unit;

use App\Services\SqlExpressionGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Penyaring ekspresi adalah satu-satunya hal yang berdiri antara metadata
 * dan klausa SELECT, jadi diuji paling ketat.
 */
class SqlExpressionGuardTest extends TestCase
{
    private SqlExpressionGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new SqlExpressionGuard;
    }

    #[Test]
    #[DataProvider('ekspresiAman')]
    public function ekspresi_yang_wajar_diloloskan(string $expression): void
    {
        $this->assertSame($expression, $this->guard->assertSafe($expression));
    }

    public static function ekspresiAman(): array
    {
        return [
            'perkalian' => ['p.price * p.stock'],
            'pembagian' => ['ROUND(p.price / 1000, 2)'],
            'agregat' => ['SUM(p.stock)'],
            'agregat bersarang' => ['ROUND(AVG(p.price), 2)'],
            'gabungan string' => ["CONCAT(p.code, ' - ', p.name)"],
            'null coalesce' => ['IFNULL(p.price, 0) + 1'],
            'selisih agregat' => ['SUM(p.stock) - MIN(p.price)'],
            'persen' => ['p.qty * 100 % 7'],
        ];
    }

    #[Test]
    #[DataProvider('ekspresiBerbahaya')]
    public function ekspresi_berbahaya_ditolak(string $expression): void
    {
        $this->expectException(RuntimeException::class);
        $this->guard->assertSafe($expression);
    }

    public static function ekspresiBerbahaya(): array
    {
        return [
            // Whitelist karakter saja meloloskan ini — justru kasus inilah
            // yang membuat blocklist kata kunci ada.
            'subquery' => ['(SELECT password FROM users)'],
            'subquery huruf kecil' => ['(select password from users limit 1)'],
            'union' => ['p.price UNION SELECT 1'],
            'pernyataan kedua' => ['p.price; DROP TABLE users'],
            'sleep' => ['SLEEP(5)'],
            'benchmark' => ['BENCHMARK(1000000, MD5(1))'],
            'load_file' => ["LOAD_FILE('/etc/passwd')"],
            'fungsi di luar whitelist' => ['MD5(p.code)'],
            'komentar blok' => ['p.price /* bocor */'],
            'komentar baris' => ['p.price -- bocor'],
            'komentar versi mysql' => ['p.price /*!50000 x*/'],
            'kurung tidak seimbang buka' => ['(p.price'],
            'kurung tidak seimbang tutup' => ['p.price)'],
            'backtick' => ['`users`.`password`'],
            'kosong' => [''],
            'spasi saja' => ['   '],
        ];
    }

    #[Test]
    public function kata_kunci_di_dalam_literal_tidak_ikut_tertolak(): void
    {
        // 'from' di sini teks biasa, bukan klausa SQL.
        $expression = "CONCAT(p.name, ' from gudang')";

        $this->assertSame($expression, $this->guard->assertSafe($expression));
    }

    #[Test]
    public function label_ikut_di_pesan_kesalahan(): void
    {
        $this->expectExceptionMessageMatches('/kolom laba/');
        $this->guard->assertSafe('(SELECT 1)', 'kolom laba');
    }
}
