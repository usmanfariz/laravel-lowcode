<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi ditangani middleware permission pada route.
    }

    public function rules(): array
    {
        $id = $this->route('user')?->id;

        return [
            'username' => ['required', 'string', 'max:100', 'alpha_dash',
                Rule::unique('users', 'username')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($id)],
            // Password wajib saat tambah, opsional saat ubah.
            'password' => [$id ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:30'],
            'scope_value' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'roles' => ['array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'username' => 'username',
            'name' => 'nama',
            'scope_value' => 'nilai scope',
            'roles' => 'role',
        ];
    }
}
