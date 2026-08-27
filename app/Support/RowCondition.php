<?php

namespace App\Support;

/**
 * Mencocokkan isi satu baris dengan sebuah kondisi metadata.
 *
 * Bentuknya {"kolom": "nilai"} atau {"kolom": ["a", "b"]}; semua kunci harus
 * cocok. Perbandingan dilakukan sebagai string supaya 1 dan "1" dari database
 * dianggap sama — persis seperti evaluasi di sisi klien untuk show_condition,
 * dan memang harus persis: kondisi yang sama tidak boleh berarti dua hal
 * berbeda di layar dan di server.
 */
final class RowCondition
{
    /**
     * @param  array<string, mixed>|null  $condition
     * @param  array<string, mixed>  $row
     */
    public static function matches(?array $condition, array $row): bool
    {
        // Kondisi kosong berarti "tanpa syarat". Pemanggil yang memakainya
        // untuk MELARANG sesuatu wajib memeriksa isEmpty() lebih dulu — kalau
        // tidak, tidak adanya kondisi justru mengunci semua baris.
        if (! $condition) {
            return true;
        }

        foreach ($condition as $kolom => $harapan) {
            $nilai = $row[$kolom] ?? null;

            $cocok = is_array($harapan)
                ? in_array((string) $nilai, array_map('strval', $harapan), true)
                : (string) $harapan === (string) $nilai;

            if (! $cocok) {
                return false;
            }
        }

        return true;
    }

    /** @param  array<string, mixed>|null  $condition */
    public static function isEmpty(?array $condition): bool
    {
        return $condition === null || $condition === [];
    }
}
