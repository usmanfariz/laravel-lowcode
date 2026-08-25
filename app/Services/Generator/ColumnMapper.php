<?php

namespace App\Services\Generator;

/**
 * Menurunkan definisi field dari satu kolom tabel.
 *
 * Urutan penentuan input_type: nama kolom lebih dulu, baru tipe data. Kolom
 * `email` bertipe varchar lebih tepat jadi input email daripada teks biasa,
 * dan itu hanya bisa disimpulkan dari namanya.
 */
class ColumnMapper
{
    /** Kolom yang diurus engine sendiri, tidak boleh jadi field. */
    public const MANAGED = [
        'created_at', 'updated_at', 'deleted_at',
        'created_by', 'updated_by', 'deleted_by',
        'remember_token', 'email_verified_at',
    ];

    /** Petunjuk berdasarkan potongan nama kolom, diperiksa berurutan. */
    private const NAME_HINTS = [
        ['email'], 'email',
        ['password', 'sandi'], 'password',
        ['url', 'website', 'situs', 'link'], 'url',
        ['photo', 'foto', 'image', 'gambar', 'avatar', 'logo', 'thumbnail'], 'image',
        ['file', 'berkas', 'dokumen', 'document', 'lampiran', 'attachment'], 'file',
        ['description', 'deskripsi', 'keterangan', 'catatan', 'note', 'notes',
            'alamat', 'address', 'remark'], 'textarea',
        ['price', 'harga', 'amount', 'jumlah_uang', 'nominal', 'total', 'biaya',
            'tarif', 'saldo', 'subtotal', 'grand_total'], 'currency',
        ['percent', 'persen', 'diskon', 'discount', 'ppn', 'pajak'], 'percentage',
        ['phone', 'telepon', 'telp', 'hp', 'whatsapp', 'wa', 'fax'], 'text',
        ['content', 'konten', 'isi', 'body'], 'editor',
    ];

    /**
     * @param  array<string, mixed>  $column  hasil TableInspector::columns()
     * @return array<string, mixed>|null  null bila kolom tidak layak jadi field
     */
    public function toField(array $column, int $order): ?array
    {
        $name = $column['name'];

        if (in_array($name, self::MANAGED, true)) {
            return null;
        }

        // Primary key auto-increment tidak diisi manusia.
        if ($column['is_primary'] && $column['is_auto']) {
            return null;
        }

        $inputType = $this->inputType($column);

        $field = [
            'field_name' => $name,
            'label' => $this->label($column),
            'input_type' => $inputType,
            // NOT NULL tanpa default berarti wajib diisi. Boolean dikecualikan
            // karena switch selalu mengirim nilai.
            'is_required' => ! $column['nullable']
                && $column['default'] === null
                && ! in_array($inputType, ['switch', 'checkbox'], true),
            'is_readonly' => false,
            'is_unique' => $column['is_unique'],
            'default_value' => $this->defaultValue($column, $inputType),
            'help_text' => $column['comment'],
            'width' => in_array($inputType, ['textarea', 'editor'], true) ? 12 : 6,
            'order_no' => $order,
            'validation' => $this->validation($column, $inputType),
            'data_source_type' => 'none',
            'data_source' => null,
            'value_field' => null,
            'label_field' => null,
            'data_order_by' => null,
            'upload_path' => null,
            'allowed_extensions' => null,
            'max_file_size' => null,
        ];

        return array_merge($field, $this->sourceFor($column, $inputType));
    }

    private function inputType(array $column): string
    {
        $name = strtolower($column['name']);
        $type = $column['data_type'];

        if ($column['enum_values'] !== []) {
            return 'select';
        }

        // Kolom foreign key digambar sebagai pemilih, bukan kotak angka.
        if ($column['references'] !== null) {
            return 'select2';
        }

        // tinyint(1) adalah boolean di MySQL.
        if ($type === 'tinyint' && str_contains(strtolower($column['type']), 'tinyint(1)')) {
            return 'switch';
        }

        if ($type === 'boolean' || $type === 'bit') {
            return 'switch';
        }

        // Kolom *_id tanpa constraint tetap kemungkinan besar relasi, tapi
        // tujuannya tidak diketahui — dibiarkan angka agar tidak menebak salah.
        $hint = $this->nameHint($name);

        if ($hint !== null && $this->hintFitsType($hint, $type)) {
            return $hint;
        }

        return match ($type) {
            'text', 'mediumtext', 'longtext', 'tinytext' => 'textarea',
            'int', 'integer', 'bigint', 'smallint', 'mediumint', 'tinyint' => 'number',
            'decimal', 'numeric', 'float', 'double' => 'decimal',
            'date' => 'date',
            'datetime', 'timestamp' => 'datetime',
            'time' => 'time',
            'json' => 'textarea',
            default => $this->varcharType($column),
        };
    }

    /** varchar panjang lebih nyaman sebagai textarea daripada satu baris. */
    private function varcharType(array $column): string
    {
        return ($column['length'] ?? 0) > 255 ? 'textarea' : 'text';
    }

    private function nameHint(string $name): ?string
    {
        $hints = self::NAME_HINTS;

        for ($i = 0; $i < count($hints); $i += 2) {
            foreach ($hints[$i] as $needle) {
                if (str_contains($name, $needle)) {
                    return $hints[$i + 1];
                }
            }
        }

        return null;
    }

    /**
     * Petunjuk nama hanya dipakai bila cocok dengan tipe datanya. Kolom
     * bernama `total_item` bertipe int tetap angka, bukan mata uang.
     */
    private function hintFitsType(string $hint, string $type): bool
    {
        $textual = ['varchar', 'char', 'text', 'mediumtext', 'longtext', 'tinytext'];
        $numeric = ['decimal', 'numeric', 'float', 'double', 'int', 'integer', 'bigint', 'smallint'];

        return match ($hint) {
            'currency', 'percentage' => in_array($type, $numeric, true),
            'email', 'password', 'url', 'image', 'file', 'text' => in_array($type, $textual, true),
            'textarea', 'editor' => in_array($type, $textual, true),
            default => true,
        };
    }

    private function label(array $column): string
    {
        $name = $column['name'];

        // Kolom relasi diberi label tanpa akhiran _id supaya terbaca wajar.
        if ($column['references'] !== null && str_ends_with($name, '_id')) {
            $name = substr($name, 0, -3);
        }

        return ucwords(str_replace('_', ' ', $name));
    }

    private function defaultValue(array $column, string $inputType): ?string
    {
        $default = $column['default'];

        if ($default === null) {
            return null;
        }

        // MySQL mengembalikan default berkutip dan ekspresi seperti CURRENT_TIMESTAMP.
        $default = trim($default, "'");

        if (str_contains(strtoupper($default), 'CURRENT_TIMESTAMP') || $default === 'NULL') {
            return null;
        }

        return $default;
    }

    private function validation(array $column, string $inputType): ?string
    {
        $rules = [];

        if (in_array($inputType, ['text', 'email', 'url', 'password'], true) && $column['length']) {
            $rules[] = 'max:'.$column['length'];
        }

        if (in_array($inputType, ['decimal', 'currency', 'percentage'], true)
            && $column['precision'] !== null && $column['scale'] !== null) {
            $whole = $column['precision'] - $column['scale'];
            $rules[] = 'max:'.(10 ** $whole - 1);
        }

        return $rules === [] ? null : implode('|', $rules);
    }

    /** Sumber opsi untuk kolom enum dan foreign key. */
    private function sourceFor(array $column, string $inputType): array
    {
        if ($column['enum_values'] !== []) {
            return [
                'data_source_type' => 'enum',
                'data_source' => null,   // diisi pemanggil dengan nama tabelnya
                'value_field' => $column['name'],
            ];
        }

        if ($column['references'] !== null) {
            return [
                'data_source_type' => 'table',
                'data_source' => $column['references']['table'],
                'value_field' => $column['references']['column'],
                'label_field' => null,   // ditebak pemanggil dari kolom tabel tujuan
            ];
        }

        if (in_array($inputType, ['image', 'file'], true)) {
            return [
                'allowed_extensions' => $inputType === 'image' ? 'jpg,jpeg,png,webp' : 'pdf,doc,docx,xls,xlsx',
                'max_file_size' => 2048,
            ];
        }

        return [];
    }
}
