<?php

namespace App\Http\Requests\Builder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FormSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('form')?->id;

        return [
            // table_name dan code tidak ikut di sini: keduanya menentukan
            // identitas form dan tidak boleh berubah lewat editor.
            'name' => ['required', 'string', 'max:150'],
            'title' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'primary_key' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['single', 'master_detail', 'wizard'])],
            'layout_columns' => ['required', 'integer', 'min:1', 'max:4'],
            'scope_column' => ['nullable', 'string', 'max:100'],
            'use_soft_delete' => ['nullable', 'boolean'],
            'use_audit_column' => ['nullable', 'boolean'],
            'default_order_column' => ['nullable', 'string', 'max:100'],
            'default_order_direction' => ['required', Rule::in(['asc', 'desc'])],
            'per_page' => ['required', 'integer', 'min:5', 'max:200'],
            'permission_prefix' => ['nullable', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_.]*$/'],
            'allow_create' => ['nullable', 'boolean'],
            'allow_edit' => ['nullable', 'boolean'],
            'allow_delete' => ['nullable', 'boolean'],
            'allow_export' => ['nullable', 'boolean'],
            'allow_print' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama', 'primary_key' => 'primary key',
            'layout_columns' => 'jumlah kolom', 'per_page' => 'baris per halaman',
            'permission_prefix' => 'prefix izin',
        ];
    }
}
