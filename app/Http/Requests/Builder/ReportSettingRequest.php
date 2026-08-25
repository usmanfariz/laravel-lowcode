<?php

namespace App\Http\Requests\Builder;

use App\Services\DataSourceResolver;
use App\Services\Report\ReportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReportSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $report = $this->route('report');

        return [
            // Kode dan tabel dasar hanya bisa ditetapkan saat membuat.
            'code' => [$report ? 'prohibited' : 'required', 'string', 'max:100',
                'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('reports', 'code')],
            'base_table' => [$report ? 'prohibited' : 'required', 'string', 'max:150'],
            'base_alias' => ['nullable', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/'],

            'name' => ['required', 'string', 'max:150'],
            'title' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(['table', 'summary', 'crosstab', 'chart'])],
            'source_type' => ['required', Rule::in(['builder', 'raw'])],
            'raw_query' => ['nullable', 'string', 'max:65535'],
            'group_by' => ['nullable', 'string', 'max:255'],
            'having' => ['nullable', 'string', 'max:255'],
            'default_order_column' => ['nullable', 'string', 'max:100'],
            'default_order_direction' => ['required', Rule::in(['asc', 'desc'])],
            'per_page' => ['required', 'integer', 'min:5', 'max:500'],
            'scope_column' => ['nullable', 'string', 'max:100'],
            'permission_code' => ['nullable', 'string', 'max:150'],
            'use_soft_delete' => ['nullable', 'boolean'],
            'allow_export_excel' => ['nullable', 'boolean'],
            'allow_export_pdf' => ['nullable', 'boolean'],
            'allow_export_csv' => ['nullable', 'boolean'],
            'allow_print' => ['nullable', 'boolean'],
            'export_queue_threshold' => ['nullable', 'integer', 'min:100', 'max:50000'],
            'is_active' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->filled('base_table')) {
                    try {
                        app(DataSourceResolver::class)->assertReadable($this->input('base_table'));
                    } catch (\Throwable $e) {
                        $validator->errors()->add('base_table', $e->getMessage());
                    }
                }

                $this->checkRawQuery($validator);
            },
        ];
    }

    /**
     * Mode raw adalah titik paling berbahaya di seluruh sistem, jadi ditolak
     * sejak di builder — bukan hanya saat report dibuka.
     */
    private function checkRawQuery(Validator $validator): void
    {
        if ($this->input('source_type') !== 'raw') {
            return;
        }

        if (! setting('allow_raw_query', false)) {
            $validator->errors()->add('source_type',
                'Mode raw dimatikan lewat pengaturan security.allow_raw_query.');

            return;
        }

        if (! $this->user()?->hasPermission('system.raw_query')) {
            $validator->errors()->add('source_type', "Mode raw butuh izin 'system.raw_query'.");

            return;
        }

        if (! $this->filled('raw_query')) {
            $validator->errors()->add('raw_query', 'Wajib diisi untuk mode raw.');

            return;
        }

        if (! app(ReportService::class)->isSelectOnly($this->input('raw_query'))) {
            $validator->errors()->add('raw_query',
                'Hanya satu pernyataan SELECT yang diizinkan. Titik koma di tengah, '
                .'kata kunci DML/DDL, information_schema, dan LOAD_FILE ditolak.');
        }
    }

    public function messages(): array
    {
        return [
            'code.prohibited' => 'Kode report tidak dapat diubah setelah dibuat.',
            'base_table.prohibited' => 'Tabel dasar tidak dapat diubah. Buat report baru bila perlu tabel lain.',
            'code.regex' => 'Kode hanya boleh huruf kecil, angka, dan underscore, diawali huruf.',
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'kode', 'name' => 'nama', 'base_table' => 'tabel dasar',
            'base_alias' => 'alias tabel', 'per_page' => 'baris per halaman',
            'raw_query' => 'query', 'source_type' => 'mode sumber',
        ];
    }
}
