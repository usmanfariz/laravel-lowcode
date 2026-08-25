<?php

namespace App\Http\Requests\Admin;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Aturan validasi disusun dari isi tabel `settings`.
 *
 * Menambah pengaturan baru cukup dengan menambah barisnya di seeder; berkas ini
 * tidak perlu ikut berubah.
 */
class SettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Ketiganya dibaca sebagai larik di SettingService. Tanpa penjagaan ini,
        // kiriman berupa skalar menjatuhkan permintaan dengan galat 500.
        $rules = [
            'values' => ['nullable', 'array'],
            'files' => ['nullable', 'array'],
            'remove' => ['nullable', 'array'],
        ];

        foreach (Setting::all() as $setting) {
            $key = $setting->key_name;

            if ($setting->isFile()) {
                // SVG sengaja tidak diterima: berkas itu bisa memuat skrip dan
                // disajikan dari origin yang sama dengan aplikasi.
                $rules["files.{$key}"] = ['nullable', 'image', 'mimes:png,jpg,jpeg,gif,webp', 'max:1024'];

                continue;
            }

            $rules["values.{$key}"] = $this->rulesFor($setting);
        }

        return $rules;
    }

    public function attributes(): array
    {
        $labels = [];

        foreach (Setting::all() as $setting) {
            $name = strtolower($setting->label ?: $setting->key_name);
            $labels["values.{$setting->key_name}"] = $name;
            $labels["files.{$setting->key_name}"] = $name;
        }

        return $labels;
    }

    /** @return array<int, mixed> */
    private function rulesFor(Setting $setting): array
    {
        if ($setting->choices() !== []) {
            return ['nullable', Rule::in(array_keys($setting->choices()))];
        }

        return match ($setting->value_type) {
            // Batas atas dipasang longgar; yang penting menolak 0 dan negatif,
            // yang akan merusak pemakaiannya sebagai jumlah baris atau ukuran.
            'integer' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'boolean' => ['nullable', 'boolean'],
            'json' => ['nullable', 'json'],
            default => $setting->resolvedInput() === 'textarea'
                ? ['nullable', 'string', 'max:2000']
                : ['nullable', 'string', 'max:255'],
        };
    }
}
