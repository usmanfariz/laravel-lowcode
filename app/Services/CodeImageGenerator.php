<?php

namespace App\Services;

use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Picqer\Barcode\Types\TypeCode128;

/**
 * Menghasilkan barcode dan QR sebagai SVG.
 *
 * SVG dipilih, bukan PNG: tidak butuh ekstensi gambar, tetap tajam saat
 * dicetak berapa pun ukurannya, dan bisa disematkan langsung ke halaman
 * tanpa permintaan berkas terpisah.
 */
class CodeImageGenerator
{
    /**
     * Barcode Code 128 — jenis yang dipahami hampir semua pemindai dan
     * menerima huruf maupun angka.
     *
     * @return string|null null bila nilainya tidak bisa dikodekan
     */
    public function barcodeSvg(string $value, int $height = 40, float $widthFactor = 1.6): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            $barcode = (new TypeCode128)->getBarcode($value);
        } catch (\Throwable) {
            // Nilai di luar jangkauan Code 128 tidak boleh mematikan halaman;
            // labelnya tetap tercetak, hanya tanpa barcode.
            return null;
        }

        $width = $barcode->getWidth() * $widthFactor;
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$width.' '.$height.'" '
            .'width="100%" height="'.$height.'" shape-rendering="crispEdges">';

        $x = 0.0;
        foreach ($barcode->getBars() as $bar) {
            $barWidth = $bar->getWidth() * $widthFactor;

            if ($bar->isBar()) {
                $svg .= '<rect x="'.round($x, 2).'" y="0" width="'.round($barWidth, 2).'" '
                    .'height="'.$height.'" fill="#000"/>';
            }

            $x += $barWidth;
        }

        return $svg.'</svg>';
    }

    /**
     * QR code sebagai SVG.
     *
     * Tingkat koreksi kesalahan M: cukup tahan terhadap label yang tergores
     * tanpa membuat pola terlalu rapat untuk dicetak kecil.
     */
    public function qrSvg(string $value, int $size = 90): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            $writer = new Writer(new ImageRenderer(
                new RendererStyle($size, 1),
                new SvgImageBackEnd
            ));

            $svg = $writer->writeString($value, Encoder::DEFAULT_BYTE_MODE_ECODING, \BaconQrCode\Common\ErrorCorrectionLevel::M());
        } catch (\Throwable) {
            return null;
        }

        // Deklarasi XML tidak boleh muncul di tengah dokumen HTML.
        return trim(preg_replace('/<\?xml[^>]*\?>\s*/', '', $svg));
    }

    /**
     * URL kanonik untuk disematkan ke QR.
     *
     * Sengaja memakai APP_URL, bukan host dari request: label dicetak untuk
     * dipindai belakangan, sering dari perangkat lain. QR yang berisi
     * "127.0.0.1" akan menunjuk perangkat pemindainya sendiri.
     */
    public function canonicalUrl(string $path): string
    {
        $base = rtrim((string) config('app.url'), '/');

        return $base !== ''
            ? $base.'/'.ltrim($path, '/')
            : url($path);
    }
}
