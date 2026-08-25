<?php

namespace App\Http\Requests\Admin;

use App\Services\Generator\TableInspector;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DataSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $source = $this->route('dataSource');

        return [
            // Tabel tidak bisa diganti: seluruh metadata yang menunjuk sumber
            // ini memakai nama tabelnya, bukan id-nya.
            'table_name' => [
                $source ? 'prohibited' : 'required',
                'string', 'max:150', 'regex:/^[A-Za-z_][A-Za-z0-9_]*$/',
                Rule::unique('data_sources', 'table_name')->where('connection', 'mysql'),
            ],
            'label' => ['required', 'string', 'max:150'],
            'primary_key' => ['required', 'string', 'max:100'],
            'is_readable' => ['nullable', 'boolean'],
            'is_writable' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'blocked_columns' => ['array'],
            'blocked_columns.*' => ['string', 'max:150'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $source = $this->route('dataSource');
                $table = $source?->table_name ?? $this->input('table_name');

                if (! $table) {
                    return;
                }

                $inspector = app(TableInspector::class);
                $columns = $inspector->rawColumns($table);

                if ($columns === []) {
                    $validator->errors()->add('table_name',
                        "Tabel '{$table}' tidak ada di database ini.");

                    return;
                }

                if (! in_array($this->input('primary_key'), $columns, true)) {
                    $validator->errors()->add('primary_key',
                        "Kolom '{$this->input('primary_key')}' tidak ada di tabel {$table}.");
                }

                foreach ($this->input('blocked_columns', []) as $column) {
                    if (! in_array($column, $columns, true)) {
                        $validator->errors()->add('blocked_columns',
                            "Kolom '{$column}' tidak ada di tabel {$table}.");
                        break;
                    }
                }

                // Memblokir primary key membuat sumber ini tidak berguna:
                // engine perlu primary key untuk membaca dan menulis baris.
                if (in_array($this->input('primary_key'), $this->input('blocked_columns', []), true)) {
                    $validator->errors()->add('blocked_columns',
                        'Primary key tidak boleh diblokir.');
                }

                // Menulis tanpa membaca tidak masuk akal: engine selalu membaca
                // baris sebelum memperbaruinya.
                if ($this->boolean('is_writable') && ! $this->boolean('is_readable')) {
                    $validator->errors()->add('is_writable',
                        'Tabel yang boleh ditulis harus boleh dibaca juga.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'table_name.prohibited' => 'Nama tabel tidak dapat diubah. Hapus lalu daftarkan ulang bila perlu.',
            'table_name.unique' => 'Tabel ini sudah terdaftar sebagai sumber data.',
        ];
    }

    public function attributes(): array
    {
        return [
            'table_name' => 'nama tabel', 'label' => 'label',
            'primary_key' => 'primary key', 'blocked_columns' => 'kolom diblokir',
            'is_writable' => 'izin tulis',
        ];
    }
}
