<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HelpArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('helpArticle')?->id;

        return [
            'code' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_.-]*$/',
                Rule::unique('help_articles', 'code')->ignore($id)],
            'category' => ['required', 'string', 'max:100'],
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:10000'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'link_route' => ['nullable', 'string', 'max:150'],
            'link_label' => ['nullable', 'string', 'max:100'],
            'permission_code' => ['nullable', 'string', 'max:150'],
            'is_featured' => ['nullable', 'boolean'],
            'order_no' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'kode',
            'category' => 'kategori',
            'question' => 'pertanyaan',
            'answer' => 'jawaban',
            'keywords' => 'kata kunci',
            'link_route' => 'route tujuan',
            'link_label' => 'label tombol',
            'permission_code' => 'izin',
            'order_no' => 'urutan',
        ];
    }
}
