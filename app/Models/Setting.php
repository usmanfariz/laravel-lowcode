<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'group_name', 'key_name', 'value', 'value_type', 'input_type',
    'options', 'label', 'description', 'is_public', 'order_no',
])]
class Setting extends Model
{
    /**
     * Kelompok yang dikenal halaman Pengaturan, sekaligus urutan tabnya.
     *
     * Kelompok di luar daftar ini tetap tampil — di tab paling kanan dengan
     * judul apa adanya — supaya menambah pengaturan tidak wajib menyentuh kode.
     */
    public const GROUPS = [
        'general' => ['label' => 'Aplikasi', 'icon' => 'fas fa-cube'],
        'company' => ['label' => 'Perusahaan', 'icon' => 'fas fa-building'],
        'appearance' => ['label' => 'Tampilan', 'icon' => 'fas fa-palette'],
        'print' => ['label' => 'Cetak & Ekspor', 'icon' => 'fas fa-print'],
        'security' => ['label' => 'Keamanan', 'icon' => 'fas fa-shield-alt'],
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_public' => 'boolean',
        ];
    }

    /** Nilai yang sudah di-cast sesuai value_type. */
    public function typedValue(): mixed
    {
        return setting_cast($this->value, $this->value_type);
    }

    /**
     * Bentuk isian di halaman Pengaturan.
     *
     * `input_type` menang bila diisi; selebihnya diturunkan dari value_type
     * agar pengaturan baru cukup ditulis tipenya saja.
     */
    public function resolvedInput(): string
    {
        return $this->input_type ?: match ($this->value_type) {
            'integer' => 'number',
            'boolean' => 'switch',
            'json' => 'textarea',
            'file' => 'image',
            default => 'text',
        };
    }

    public function isFile(): bool
    {
        return $this->value_type === 'file';
    }

    /** @return array<string, string> pilihan untuk input select */
    public function choices(): array
    {
        return $this->options ?: [];
    }

    /** Isian panjang memakan satu baris penuh; sisanya cukup setengah. */
    public function columnClass(): string
    {
        return in_array($this->resolvedInput(), ['textarea', 'image'], true) ? 'col-12' : 'col-md-6';
    }

    public function groupLabel(): string
    {
        return self::GROUPS[$this->group_name]['label'] ?? ucfirst($this->group_name);
    }

    public function groupIcon(): string
    {
        return self::GROUPS[$this->group_name]['icon'] ?? 'fas fa-sliders-h';
    }
}
