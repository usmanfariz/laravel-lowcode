<?php

namespace App\Http\Requests\Builder;

use App\Services\DataSourceResolver;
use App\Services\SqlExpressionGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FormListColumnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:150'],
            'source_type' => ['required', Rule::in(['column', 'relation', 'expression'])],
            'column_name' => ['nullable', 'string', 'max:150'],
            'relation_table' => ['nullable', 'string', 'max:150'],
            'relation_key' => ['nullable', 'string', 'max:100'],
            'relation_label' => ['nullable', 'string', 'max:100'],
            'expression' => ['nullable', 'string', 'max:255'],
            'format' => ['required', Rule::in([
                'text', 'number', 'decimal', 'currency', 'percentage',
                'date', 'datetime', 'boolean', 'badge', 'image', 'link',
            ])],
            'align' => ['required', Rule::in(['left', 'center', 'right'])],
            'width' => ['nullable', 'string', 'max:20'],
            'is_visible' => ['nullable', 'boolean'],
            'is_searchable' => ['nullable', 'boolean'],
            'is_sortable' => ['nullable', 'boolean'],
            'order_no' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function after(): array
    {
        return [fn (Validator $v) => match ($this->input('source_type')) {
            'column' => $this->checkColumn($v),
            'relation' => $this->checkRelation($v),
            'expression' => $this->checkExpression($v),
            default => null,
        }];
    }

    private function checkColumn(Validator $validator): void
    {
        if (! $this->filled('column_name')) {
            $validator->errors()->add('column_name', 'Wajib diisi untuk kolom biasa.');

            return;
        }

        $this->assertColumn($validator, 'column_name', $this->route('form')->table_name, $this->input('column_name'));
    }

    /**
     * Kolom relasi menyentuh dua tabel: kolom kunci di tabel form, dan tabel
     * tujuan beserta kolom kunci serta labelnya. Semuanya diperiksa.
     */
    private function checkRelation(Validator $validator): void
    {
        foreach (['column_name', 'relation_table', 'relation_key', 'relation_label'] as $key) {
            if (! $this->filled($key)) {
                $validator->errors()->add($key, 'Wajib diisi untuk kolom relasi.');
            }
        }

        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $this->assertColumn($validator, 'column_name', $this->route('form')->table_name, $this->input('column_name'));

        try {
            $resolver = app(DataSourceResolver::class);
            $resolver->assertReadable($this->input('relation_table'));
            $resolver->assertColumn($this->input('relation_table'), $this->input('relation_key'));
            $resolver->assertColumn($this->input('relation_table'), $this->input('relation_label'));
        } catch (\Throwable $e) {
            $validator->errors()->add('relation_table', $e->getMessage());
        }
    }

    /**
     * Ekspresi masuk langsung ke klausa SELECT. Selain disaring
     * SqlExpressionGuard, pemakainya wajib memegang system.raw_query.
     */
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
            app(SqlExpressionGuard::class)->assertSafe($this->input('expression'), 'kolom list');
        } catch (\Throwable $e) {
            $validator->errors()->add('expression', $e->getMessage());
        }
    }

    private function assertColumn(Validator $validator, string $key, string $table, string $column): void
    {
        try {
            app(DataSourceResolver::class)->assertColumn($table, $column);
        } catch (\Throwable $e) {
            $validator->errors()->add($key, $e->getMessage());
        }
    }

    public function attributes(): array
    {
        return [
            'label' => 'label', 'source_type' => 'jenis sumber',
            'column_name' => 'kolom', 'relation_table' => 'tabel relasi',
            'relation_key' => 'kolom kunci relasi', 'relation_label' => 'kolom label relasi',
            'expression' => 'ekspresi', 'order_no' => 'urutan',
        ];
    }
}
