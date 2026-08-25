<?php

namespace App\Http\Requests\Builder;

use App\Services\Report\ReportQueryBuilder;
use App\Services\SqlExpressionGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReportColumnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:150'],
            'source_type' => ['required', Rule::in(['column', 'expression'])],
            'column_name' => ['nullable', 'string', 'max:150'],
            'expression' => ['nullable', 'string', 'max:255'],
            'column_alias' => ['nullable', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/'],
            'aggregate' => ['required', Rule::in([
                'none', 'sum', 'avg', 'count', 'count_distinct', 'min', 'max',
            ])],
            'format' => ['required', Rule::in([
                'text', 'number', 'decimal', 'currency', 'percentage',
                'date', 'datetime', 'boolean', 'badge',
            ])],
            'decimal_places' => ['nullable', 'integer', 'min:0', 'max:6'],
            'align' => ['required', Rule::in(['left', 'center', 'right'])],
            'width' => ['nullable', 'string', 'max:20'],
            'is_visible' => ['nullable', 'boolean'],
            'is_sortable' => ['nullable', 'boolean'],
            'is_searchable' => ['nullable', 'boolean'],
            'is_group_column' => ['nullable', 'boolean'],
            'show_total' => ['nullable', 'boolean'],
            'order_no' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $report = $this->route('report');

                if ($this->input('source_type') === 'expression') {
                    $this->checkExpression($validator);
                } else {
                    if (! $this->filled('column_name')) {
                        $validator->errors()->add('column_name', 'Wajib diisi untuk kolom biasa.');
                    } else {
                        try {
                            app(ReportQueryBuilder::class)->qualify($report, $this->input('column_name'));
                        } catch (\Throwable $e) {
                            $validator->errors()->add('column_name', $e->getMessage());
                        }
                    }
                }

                // Kolom beragregat tidak bisa sekaligus jadi kolom pengelompokan:
                // MySQL menolak SUM(x) di dalam GROUP BY.
                if ($this->boolean('is_group_column') && $this->input('aggregate') !== 'none') {
                    $validator->errors()->add('is_group_column',
                        'Kolom beragregat tidak dapat dijadikan kolom pengelompokan.');
                }

                // Pencarian memakai klausa WHERE, yang berjalan sebelum agregasi.
                if ($this->boolean('is_searchable') && $this->input('aggregate') !== 'none') {
                    $validator->errors()->add('is_searchable',
                        'Kolom beragregat tidak dapat ikut pencarian.');
                }
            },
        ];
    }

    private function checkExpression(Validator $validator): void
    {
        if (! $this->user()?->hasPermission('system.raw_query')) {
            $validator->errors()->add('expression', "Kolom ekspresi butuh izin 'system.raw_query'.");

            return;
        }

        if (! $this->filled('expression')) {
            $validator->errors()->add('expression', 'Wajib diisi untuk kolom ekspresi.');

            return;
        }

        try {
            app(SqlExpressionGuard::class)->assertSafe($this->input('expression'), 'kolom report');
        } catch (\Throwable $e) {
            $validator->errors()->add('expression', $e->getMessage());
        }
    }

    public function attributes(): array
    {
        return [
            'label' => 'label', 'column_name' => 'kolom', 'expression' => 'ekspresi',
            'aggregate' => 'agregat', 'order_no' => 'urutan',
            'is_group_column' => 'kolom pengelompokan', 'is_searchable' => 'ikut pencarian',
        ];
    }
}
