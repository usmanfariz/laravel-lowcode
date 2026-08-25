<?php

namespace App\Http\Requests\Builder;

use App\Services\DataSourceResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FormDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $form = $this->route('form');
        $detail = $this->route('detail');

        return [
            'code' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('form_details', 'code')->where('form_id', $form->id)->ignore($detail?->id)],
            'title' => ['required', 'string', 'max:150'],
            'table_name' => ['required', 'string', 'max:150'],
            'primary_key' => ['required', 'string', 'max:100'],
            'foreign_key' => ['required', 'string', 'max:100'],
            'min_rows' => ['nullable', 'integer', 'min:0', 'max:999'],
            'max_rows' => ['nullable', 'integer', 'min:1', 'max:999'],
            'allow_add' => ['nullable', 'boolean'],
            'allow_delete' => ['nullable', 'boolean'],
            'show_total_row' => ['nullable', 'boolean'],
            'order_no' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $table = $this->input('table_name');

                if (! $table) {
                    return;
                }

                try {
                    $resolver = app(DataSourceResolver::class);

                    // Tabel detail akan ditulis engine, jadi izin tulisnya
                    // diperiksa di sini — bukan nanti saat pengguna menyimpan.
                    $resolver->assertWritable($table);

                    foreach (['primary_key', 'foreign_key'] as $key) {
                        if ($this->filled($key)) {
                            $resolver->assertColumn($table, $this->input($key));
                        }
                    }
                } catch (\Throwable $e) {
                    $validator->errors()->add('table_name', $e->getMessage());

                    return;
                }

                if ($this->filled('min_rows') && $this->filled('max_rows')
                    && (int) $this->input('min_rows') > (int) $this->input('max_rows')) {
                    $validator->errors()->add('max_rows',
                        'Baris maksimal tidak boleh lebih kecil dari baris minimal.');
                }

                // Tabel detail sama dengan tabel induk berarti baris induk jadi
                // anak dirinya sendiri.
                if ($table === $this->route('form')->table_name) {
                    $validator->errors()->add('table_name',
                        'Tabel detail tidak boleh sama dengan tabel form induk.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'kode', 'title' => 'judul', 'table_name' => 'tabel detail',
            'primary_key' => 'primary key', 'foreign_key' => 'kolom penghubung',
            'min_rows' => 'baris minimal', 'max_rows' => 'baris maksimal',
            'order_no' => 'urutan',
        ];
    }
}
