<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $role = $this->route('role');

        return [
            // Kode role dipakai di metadata dan kode program, jadi dikunci
            // ke huruf kecil + underscore dan tidak boleh diubah setelah dibuat.
            'code' => [
                $role ? 'prohibited' : 'required',
                'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('roles', 'code'),
            ],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'data_scope' => ['required', Rule::in(['all', 'own', 'branch', 'custom'])],
            'is_active' => ['nullable', 'boolean'],
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Kode role hanya boleh huruf kecil, angka, dan underscore, diawali huruf.',
            'code.prohibited' => 'Kode role tidak dapat diubah setelah dibuat.',
        ];
    }

    public function attributes(): array
    {
        return ['code' => 'kode', 'name' => 'nama', 'data_scope' => 'cakupan data'];
    }
}
