<?php

namespace App\Support;

/**
 * Membuang nilai null untuk kolom yang NOT NULL tapi punya default di database.
 *
 * Kolom seperti `form_actions.css_class` boleh dikosongkan pengguna, tapi di
 * skema ia NOT NULL dengan nilai bawaan. Laravel mengubah masukan kosong
 * menjadi null lewat ConvertEmptyStringsToNull, sehingga null itu ikut masuk
 * INSERT dan ditolak database — pengguna hanya melihat 500 tanpa penjelasan.
 *
 * Membuang kuncinya membuat database memakai nilai bawaannya, yang memang
 * itulah maksud "boleh dikosongkan".
 */
trait DropsNullDefaults
{
    /**
     * @param  array<string, mixed>  $values
     * @param  array<int, string>  $keys  kolom yang punya default di skema
     * @return array<string, mixed>
     */
    protected function dropNullDefaults(array $values, array $keys): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $values) && $values[$key] === null) {
                unset($values[$key]);
            }
        }

        return $values;
    }
}
