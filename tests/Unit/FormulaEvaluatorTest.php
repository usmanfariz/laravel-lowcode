<?php

namespace Tests\Unit;

use App\Exceptions\FormulaException;
use App\Support\FormulaEvaluator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FormulaEvaluatorTest extends TestCase
{
    #[Test]
    #[DataProvider('rumusSah')]
    public function menghitung_rumus_yang_sah(string $rumus, float $harapan): void
    {
        $nilai = ['qty' => 3, 'harga' => 2500, 'diskon' => 10, 'kosong' => null];
        $sums = ['items.subtotal' => 7500.0, 'items.qty' => 12.0];

        $this->assertEqualsWithDelta(
            $harapan,
            FormulaEvaluator::evaluate($rumus, $nilai, $sums),
            0.00001,
        );
    }

    public static function rumusSah(): array
    {
        return [
            'angka saja' => ['42', 42.0],
            'desimal' => ['1.5', 1.5],
            'field' => ['qty', 3.0],
            'perkalian' => ['qty * harga', 7500.0],
            'urutan operator' => ['1 + 2 * 3', 7.0],
            'kurung mengubah urutan' => ['(1 + 2) * 3', 9.0],
            'pengurangan berantai' => ['10 - 3 - 2', 5.0],
            'pembagian berantai' => ['100 / 5 / 2', 10.0],
            'negatif' => ['-qty', -3.0],
            'negatif ganda' => ['--qty', 3.0],
            'plus di depan' => ['+qty', 3.0],
            'persen diskon' => ['qty * harga * (1 - diskon / 100)', 6750.0],
            'field kosong dianggap nol' => ['kosong + 5', 5.0],
            'field tak dikenal dianggap nol' => ['tidak_ada + 5', 5.0],
            'agregat detail' => ['sum(items.subtotal)', 7500.0],
            'agregat dalam ekspresi' => ['sum(items.subtotal) * 1.1', 8250.0],
            'dua agregat' => ['sum(items.subtotal) / sum(items.qty)', 625.0],
            'spasi berlebih' => ['   qty   *   harga   ', 7500.0],
            'tanpa spasi' => ['qty*harga', 7500.0],
        ];
    }

    #[Test]
    public function pembagian_nol_menghasilkan_nol_bukan_galat(): void
    {
        // Rumus dihitung ulang tiap ketikan; penyebut yang sesaat kosong itu
        // wajar dan tidak boleh melempar.
        $this->assertSame(0.0, FormulaEvaluator::evaluate('10 / x', ['x' => 0]));
        $this->assertSame(0.0, FormulaEvaluator::evaluate('10 / x', []));
    }

    #[Test]
    #[DataProvider('rumusTidakSah')]
    public function menolak_rumus_yang_tidak_sah(string $rumus): void
    {
        $this->expectException(FormulaException::class);
        FormulaEvaluator::parse($rumus);
    }

    public static function rumusTidakSah(): array
    {
        return [
            'kosong' => [''],
            'hanya spasi' => ['   '],
            'kurung tak tertutup' => ['(1 + 2'],
            'kurung berlebih' => ['1 + 2)'],
            'operator menggantung' => ['1 +'],
            'dua operator' => ['1 * / 2'],
            'sum tanpa titik' => ['sum(items)'],
            'sum tanpa kurung tutup' => ['sum(items.qty'],
            'karakter aneh' => ['qty $ harga'],
            'tanda seru' => ['qty != 1'],
            'string' => ["'teks'"],
            'titik koma' => ['qty; drop table users'],
            'pemanggilan fungsi lain' => ['abs(qty)'],
        ];
    }

    #[Test]
    public function pemanggilan_fungsi_selain_sum_tidak_dikenali_sebagai_fungsi(): void
    {
        // "abs(qty)" terurai jadi field 'abs' diikuti kurung — dan itu ditolak,
        // bukan diam-diam dijalankan sebagai fungsi PHP.
        $this->expectException(FormulaException::class);
        FormulaEvaluator::parse('abs(qty)');
    }

    #[Test]
    public function melaporkan_field_dan_agregat_yang_dipakai(): void
    {
        $rumus = 'qty * harga - sum(items.diskon) + sum(items.diskon)';

        $this->assertSame(['qty', 'harga'], FormulaEvaluator::fieldsUsed($rumus));
        $this->assertSame(['items.diskon'], FormulaEvaluator::aggregatesUsed($rumus));
    }

    #[Test]
    public function sum_tidak_dihitung_sebagai_field_biasa(): void
    {
        $this->assertSame([], FormulaEvaluator::fieldsUsed('sum(items.qty)'));
    }
}
