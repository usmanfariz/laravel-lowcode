<?php

namespace App\Services\Form;

use App\Models\FormField;
use App\Services\DataSourceResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Menyiapkan data yang dibutuhkan komponen Blade untuk menggambar satu field.
 *
 * Kelas ini tidak merangkai HTML sendiri — HTML tetap di Blade agar bisa
 * disesuaikan tanpa menyentuh PHP.
 */
class FormRenderer
{
    public function __construct(private readonly DataSourceResolver $sources) {}

    /**
     * Opsi untuk field select/radio/checkbox.
     *
     * @return Collection<int, array{value: mixed, label: string}>
     */
    public function optionsFor(FormField $field): Collection
    {
        return match ($field->data_source_type) {
            'static' => $field->options->map(fn ($o) => [
                'value' => $o->value,
                'label' => $o->label,
            ])->values(),

            'table' => $this->tableOptions($field),

            'enum' => $this->enumOptions($field),

            default => collect(),
        };
    }

    private function tableOptions(FormField $field): Collection
    {
        if (! $field->data_source || ! $field->value_field || ! $field->label_field) {
            return collect();
        }

        // ajax_select memuat opsinya lewat permintaan terpisah saat diketik,
        // jadi tidak perlu diambil semua di awal.
        if ($field->input_type === 'ajax_select') {
            return collect();
        }

        // Field yang bergantung pada field lain baru bisa diisi setelah
        // induknya dipilih; opsinya diambil lewat ajax.
        if ($field->depends_on) {
            return collect();
        }

        // Yang disimpan array biasa, bukan Collection: config
        // cache.serializable_classes default false, sehingga objek apa pun
        // yang keluar dari cache berubah jadi __PHP_Incomplete_Class.
        return collect(Cache::remember(
            'form.options.'.$field->id,
            now()->addMinutes(10),
            fn () => $this->sources->options(
                $field->data_source,
                $field->value_field,
                $field->label_field,
                $field->data_filter,
                $field->data_order_by,
            )->all()
        ));
    }

    /** Nilai enum dibaca dari definisi kolom di tabel sumber. */
    private function enumOptions(FormField $field): Collection
    {
        if (! $field->data_source) {
            return collect();
        }

        $table = $field->data_source;
        $column = $field->value_field ?: $field->field_name;

        return collect(Cache::remember(
            "form.enum.{$table}.{$column}",
            now()->addMinutes(30),
            function () use ($table, $column) {
                $this->sources->assertColumn($table, $column);

                $type = $this->sources->query($table)
                    ->getConnection()
                    ->selectOne(
                        'SELECT COLUMN_TYPE AS t FROM information_schema.columns
                         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
                        [$table, $column]
                    )?->t;

                if (! $type || ! str_starts_with($type, 'enum(')) {
                    return [];
                }

                preg_match_all("/'((?:[^']|'')*)'/", $type, $matches);

                return array_map(fn ($v) => [
                    'value' => str_replace("''", "'", $v),
                    'label' => str_replace("''", "'", $v),
                ], $matches[1]);
            }
        ));
    }

    /** Nilai awal field: old input → nilai baris → default metadata. */
    public function valueFor(FormField $field, array $row = []): mixed
    {
        $name = $field->field_name;

        if (old($name) !== null) {
            return old($name);
        }

        if (array_key_exists($name, $row)) {
            return $row[$name];
        }

        if ($field->data_source_type === 'static') {
            $default = $field->options->firstWhere('is_default', true);
            if ($default) {
                return $default->value;
            }
        }

        return $field->default_value;
    }
}
