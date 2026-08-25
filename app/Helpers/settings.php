<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (! function_exists('setting')) {
    /**
     * Baca satu nilai dari tabel settings, sudah di-cast sesuai value_type.
     *
     * Seluruh tabel dimuat sekali lalu di-cache: jumlah barisnya sedikit dan
     * satu halaman bisa memanggil helper ini berkali-kali.
     */
    function setting(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever('settings.all', function () {
            // Layout dipakai juga sebelum migrasi jalan (mis. saat instalasi),
            // jadi ketiadaan tabel tidak boleh menjatuhkan halaman.
            if (! Schema::hasTable('settings')) {
                return [];
            }

            return DB::table('settings')
                ->get(['key_name', 'value', 'value_type'])
                ->keyBy('key_name')
                ->map(fn ($row) => setting_cast($row->value, $row->value_type))
                ->all();
        });

        return $all[$key] ?? $default;
    }
}

if (! function_exists('setting_cast')) {
    function setting_cast(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };
    }
}

if (! function_exists('setting_flush')) {
    function setting_flush(): void
    {
        Cache::forget('settings.all');
    }
}
