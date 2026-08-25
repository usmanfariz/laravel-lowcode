<?php

namespace App\Http\Requests\Admin;

use App\Models\Menu;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('menu')?->id;

        return [
            'parent_id' => ['nullable', 'integer', 'exists:menus,id'],
            'code' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_.]*$/',
                Rule::unique('menus', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:150'],
            'icon' => ['nullable', 'string', 'max:100'],
            'link_type' => ['required', Rule::in(['route', 'url', 'form', 'report', 'header'])],
            // Header hanya pembungkus, jadi tidak boleh punya tujuan.
            'target_value' => [
                $this->input('link_type') === 'header' ? 'nullable' : 'required',
                'nullable', 'string', 'max:255',
            ],
            'permission_code' => ['nullable', 'string', 'max:150'],
            'open_new_tab' => ['nullable', 'boolean'],
            'order_no' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $id = $this->route('menu')?->id;
                $parentId = $this->input('parent_id');

                if (! $id || ! $parentId) {
                    return;
                }

                // Menu tidak boleh menjadi induk dirinya sendiri, langsung
                // maupun lewat rantai turunan — itu membuat sidebar rekursi tak henti.
                if ((int) $parentId === (int) $id || $this->isDescendant((int) $parentId, (int) $id)) {
                    $validator->errors()->add(
                        'parent_id',
                        'Menu tidak boleh dijadikan anak dari dirinya sendiri atau turunannya.'
                    );
                }
            },
        ];
    }

    private function isDescendant(int $candidateParentId, int $menuId): bool
    {
        $seen = [];
        $cursor = Menu::find($candidateParentId);

        while ($cursor && $cursor->parent_id) {
            if (in_array($cursor->parent_id, $seen, true)) {
                return true; // siklus yang sudah terlanjur ada di data
            }
            if ((int) $cursor->parent_id === $menuId) {
                return true;
            }
            $seen[] = $cursor->parent_id;
            $cursor = Menu::find($cursor->parent_id);
        }

        return false;
    }

    public function attributes(): array
    {
        return [
            'parent_id' => 'menu induk',
            'code' => 'kode',
            'name' => 'nama',
            'link_type' => 'jenis tautan',
            'target_value' => 'tujuan',
            'order_no' => 'urutan',
        ];
    }
}
