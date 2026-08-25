<?php

namespace App\Http\Requests\Builder;

use App\Services\DataSourceResolver;
use App\Services\Report\ReportQueryBuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:150'],
            'column_name' => ['required', 'string', 'max:150'],
            'operator' => ['required', Rule::in([
                '=', '!=', '>', '>=', '<', '<=', 'like', 'not_like',
                'between', 'in', 'not_in', 'is_null', 'is_not_null',
            ])],
            'input_type' => ['required', Rule::in([
                'text', 'number', 'date', 'date_range', 'datetime',
                'select', 'select2', 'multi_select', 'checkbox', 'radio',
            ])],
            'data_source_type' => ['required', Rule::in(['none', 'static', 'table'])],
            'data_source' => ['nullable', 'string', 'max:150'],
            'value_field' => ['nullable', 'string', 'max:100'],
            'label_field' => ['nullable', 'string', 'max:100'],
            'static_options_text' => ['nullable', 'string', 'max:2000'],
            'default_values_text' => ['nullable', 'string', 'max:1000'],
            'is_required' => ['nullable', 'boolean'],
            'width' => ['required', 'integer', 'min:2', 'max:12'],
            'order_no' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                try {
                    app(ReportQueryBuilder::class)->qualify($this->route('report'), $this->input('column_name'));
                } catch (\Throwable $e) {
                    $validator->errors()->add('column_name', $e->getMessage());
                }

                $this->checkSource($validator);
                $this->checkDefaults($validator);
            },
        ];
    }

    private function checkSource(Validator $validator): void
    {
        if ($this->input('data_source_type') !== 'table') {
            return;
        }

        foreach (['data_source', 'value_field', 'label_field'] as $key) {
            if (! $this->filled($key)) {
                $validator->errors()->add($key, 'Wajib diisi untuk sumber data tabel.');
            }
        }

        if ($validator->errors()->has('data_source')) {
            return;
        }

        try {
            $resolver = app(DataSourceResolver::class);
            $resolver->assertReadable($this->input('data_source'));
            $resolver->assertColumn($this->input('data_source'), $this->input('value_field'));
            $resolver->assertColumn($this->input('data_source'), $this->input('label_field'));
        } catch (\Throwable $e) {
            $validator->errors()->add('data_source', $e->getMessage());
        }
    }

    /**
     * Jumlah nilai default harus cocok dengan operatornya. `between` butuh
     * dua nilai; operator null tidak menerima nilai sama sekali.
     */
    private function checkDefaults(Validator $validator): void
    {
        $values = $this->defaultValues();
        $operator = $this->input('operator');

        if ($values === []) {
            return;
        }

        if (in_array($operator, ['is_null', 'is_not_null'], true)) {
            $validator->errors()->add('default_values_text',
                'Operator ini tidak menerima nilai.');

            return;
        }

        if ($operator === 'between' && count($values) !== 2) {
            $validator->errors()->add('default_values_text',
                'Operator between butuh tepat dua nilai (satu per baris).');

            return;
        }

        if (! in_array($operator, ['between', 'in', 'not_in'], true) && count($values) > 1) {
            $validator->errors()->add('default_values_text',
                'Operator ini hanya menerima satu nilai.');
        }
    }

    /**
     * Nilai default diketik satu per baris di textarea, lalu disimpan sebagai
     * larik JSON — lihat docs/RANCANGAN.md §7.
     *
     * @return array<int, string>
     */
    public function defaultValues(): array
    {
        return $this->splitLines($this->input('default_values_text'));
    }

    /** @return array<string, string>|null nilai => label */
    public function staticOptions(): ?array
    {
        if ($this->input('data_source_type') !== 'static') {
            return null;
        }

        $options = [];

        foreach ($this->splitLines($this->input('static_options_text')) as $line) {
            // Format "nilai|label"; tanpa pemisah, nilai dipakai sebagai label.
            [$value, $label] = array_pad(explode('|', $line, 2), 2, null);
            $options[trim($value)] = trim($label ?? $value);
        }

        return $options ?: null;
    }

    /** @return array<int, string> */
    private function splitLines(?string $text): array
    {
        if (! $text) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $text)),
            fn ($line) => $line !== ''
        ));
    }

    public function attributes(): array
    {
        return [
            'label' => 'label', 'column_name' => 'kolom', 'operator' => 'operator',
            'input_type' => 'jenis masukan', 'data_source' => 'sumber data',
            'default_values_text' => 'nilai default', 'width' => 'lebar', 'order_no' => 'urutan',
        ];
    }
}
