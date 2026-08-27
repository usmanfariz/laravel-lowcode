<?php

namespace App\Support;

/**
 * Merakit masukan builder menjadi kondisi baris.
 *
 * Editor di layar hanya menawarkan satu pasangan kolom–nilai karena itu yang
 * dibutuhkan hampir semua kasus ("status = posted"). Bentuk JSON-nya sendiri
 * mendukung banyak kunci, dan runtime tetap membacanya — kondisi berkunci
 * banyak hanya tidak bisa disunting lewat editor sederhana ini.
 */
final class ConditionInput
{
    /**
     * @return array<string, mixed>|null null bila tidak ada kondisi
     */
    public static function build(?string $column, ?string $value): ?array
    {
        $column = trim((string) $column);

        if ($column === '') {
            return null;
        }

        $bagian = array_values(array_filter(
            array_map('trim', explode(',', (string) $value)),
            fn (string $v) => $v !== '',
        ));

        if ($bagian === []) {
            return null;
        }

        // Satu nilai disimpan sebagai skalar, bukan larik berisi satu — supaya
        // JSON-nya terbaca sama dengan yang ditulis tangan.
        return [$column => count($bagian) === 1 ? $bagian[0] : $bagian];
    }

    /**
     * Kolom yang dipakai sebuah kondisi, untuk mengisi ulang editor.
     *
     * @param  array<string, mixed>|null  $condition
     */
    public static function column(?array $condition): ?string
    {
        return $condition ? (string) array_key_first($condition) : null;
    }

    /**
     * Nilai kondisi sebagai teks dipisah koma, untuk mengisi ulang editor.
     *
     * @param  array<string, mixed>|null  $condition
     */
    public static function value(?array $condition): string
    {
        if (! $condition) {
            return '';
        }

        $nilai = reset($condition);

        return is_array($nilai) ? implode(', ', $nilai) : (string) $nilai;
    }
}
