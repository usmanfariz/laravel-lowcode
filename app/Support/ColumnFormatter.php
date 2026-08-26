<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Format tampilan satu nilai kolom.
 *
 * Aturan formatnya sama persis di layar, di dashboard, dan di berkas ekspor,
 * jadi logikanya hanya boleh ada di satu tempat. Sebelumnya tersalin di empat
 * berkas dan sempat berbeda-beda diam-diam.
 *
 * Kolomnya sengaja tidak di-type-hint ke satu model: pemanggilnya memakai
 * ReportColumn maupun kolom list form. Yang dibutuhkan hanya properti `format`
 * dan (opsional) `decimal_places`.
 */
final class ColumnFormatter
{
    /**
     * Untuk keluaran HTML: nilainya di-escape, boolean tetap boolean supaya
     * sisi klien bisa menggambarnya sendiri (mis. badge di DataTables).
     */
    public static function html(mixed $value, object $column): mixed
    {
        return self::render($value, $column, plain: false);
    }

    /**
     * Untuk keluaran teks polos: berkas ekspor, dan Blade yang sudah meng-escape
     * sendiri lewat `{{ }}` — memakai html() di sana akan meng-escape dua kali.
     */
    public static function plain(mixed $value, object $column): mixed
    {
        return self::render($value, $column, plain: true);
    }

    private static function render(mixed $value, object $column, bool $plain): mixed
    {
        if ($value === null) {
            return null;
        }

        // Kolom list form tidak punya decimal_places; dua angka di belakang koma
        // adalah perilaku lamanya.
        $decimals = $column->decimal_places ?? 2;

        return match ($column->format) {
            'number' => number_format((float) $value, 0, ',', '.'),
            'decimal' => number_format((float) $value, $decimals, ',', '.'),
            'currency' => 'Rp '.number_format((float) $value, $decimals, ',', '.'),
            'percentage' => number_format((float) $value, $decimals, ',', '.').'%',
            'date' => Carbon::parse($value)->format(setting('date_format', 'd/m/Y')),
            'datetime' => Carbon::parse($value)->format(setting('date_format', 'd/m/Y').' H:i'),
            'boolean' => $plain ? ($value ? 'Ya' : 'Tidak') : (bool) $value,
            default => $plain ? (string) $value : e((string) $value),
        };
    }
}
