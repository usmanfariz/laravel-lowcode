<?php

namespace Tests\Unit;

use App\Services\CodeImageGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CodeImageGeneratorTest extends TestCase
{
    private CodeImageGenerator $codes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->codes = new CodeImageGenerator;
    }

    #[Test]
    #[DataProvider('nilaiWajar')]
    public function barcode_menghasilkan_svg_yang_sah(string $value): void
    {
        $svg = $this->codes->barcodeSvg($value);

        $this->assertNotNull($svg);
        $this->assertStringStartsWith('<svg', $svg);
        $this->assertNotFalse(simplexml_load_string($svg), 'SVG barcode tidak well-formed');
        $this->assertGreaterThan(10, substr_count($svg, '<rect'), 'batang barcode terlalu sedikit');
    }

    public static function nilaiWajar(): array
    {
        return [
            'kode produk' => ['PRD-001'],
            'angka' => ['1234567890'],
            'huruf besar' => ['ABCDEF'],
            'campuran' => ['SKU-42/A'],
            'satu karakter' => ['X'],
        ];
    }

    #[Test]
    public function barcode_berbeda_untuk_nilai_berbeda(): void
    {
        $this->assertNotSame(
            $this->codes->barcodeSvg('PRD-001'),
            $this->codes->barcodeSvg('PRD-002'),
            'barcode tidak benar-benar mengkodekan nilainya'
        );
    }

    #[Test]
    public function nilai_kosong_menghasilkan_null_bukan_kesalahan(): void
    {
        // Satu produk tanpa kode tidak boleh mematikan seluruh halaman label.
        $this->assertNull($this->codes->barcodeSvg(''));
        $this->assertNull($this->codes->barcodeSvg('   '));
        $this->assertNull($this->codes->qrSvg(''));
    }

    #[Test]
    public function qr_menghasilkan_svg_yang_sah_tanpa_deklarasi_xml(): void
    {
        $svg = $this->codes->qrSvg('https://contoh.test/produk/1');

        $this->assertNotNull($svg);
        // Deklarasi XML di tengah dokumen HTML membuat markup-nya tidak sah.
        $this->assertStringNotContainsString('<?xml', $svg);
        $this->assertStringStartsWith('<svg', $svg);
        $this->assertNotFalse(simplexml_load_string($svg), 'SVG QR tidak well-formed');
    }

    #[Test]
    public function qr_berbeda_untuk_nilai_berbeda(): void
    {
        $this->assertNotSame(
            $this->codes->qrSvg('https://contoh.test/1'),
            $this->codes->qrSvg('https://contoh.test/2'),
        );
    }

    #[Test]
    public function qr_deterministik_untuk_nilai_sama(): void
    {
        $this->assertSame(
            $this->codes->qrSvg('PRD-001'),
            $this->codes->qrSvg('PRD-001'),
        );
    }

    #[Test]
    public function url_kanonik_memakai_app_url_bukan_host_request(): void
    {
        config(['app.url' => 'https://erp.contoh.test']);

        // Label dicetak untuk dipindai belakangan, sering dari perangkat lain;
        // QR berisi host request akan menunjuk perangkat pemindainya sendiri.
        $this->assertSame(
            'https://erp.contoh.test/forms/product/1/edit',
            $this->codes->canonicalUrl('forms/product/1/edit'),
        );
    }

    #[Test]
    public function url_kanonik_menormalkan_garis_miring(): void
    {
        config(['app.url' => 'https://erp.contoh.test/']);

        $this->assertSame(
            'https://erp.contoh.test/forms/product/1/edit',
            $this->codes->canonicalUrl('/forms/product/1/edit'),
        );
    }

    #[Test]
    public function url_kanonik_jatuh_ke_url_helper_bila_app_url_kosong(): void
    {
        config(['app.url' => '']);

        $this->assertSame(url('forms/product/1/edit'), $this->codes->canonicalUrl('forms/product/1/edit'));
    }
}
