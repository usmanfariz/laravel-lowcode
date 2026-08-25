<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'table' => ['required', 'string', 'max:150',
                // Tabel wajib ada di whitelist dan boleh dibaca.
                Rule::exists('data_sources', 'table_name')->where('is_active', 1)->where('is_readable', 1)],
            'code' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('forms', 'code')],
            'name' => ['required', 'string', 'max:150'],
            'title' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'permission_prefix' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_.]*$/'],
            'scope_column' => ['nullable', 'string', 'max:100'],
            'create_menu' => ['nullable', 'boolean'],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Kode form hanya boleh huruf kecil, angka, dan underscore, diawali huruf.',
            'code.unique' => 'Kode form sudah dipakai.',
            'table.exists' => 'Tabel tidak terdaftar di data_sources atau tidak boleh dibaca.',
            'columns.required' => 'Pilih minimal satu kolom.',
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'kode form', 'name' => 'nama',
            'permission_prefix' => 'prefix izin', 'columns' => 'kolom',
        ];
    }
}
