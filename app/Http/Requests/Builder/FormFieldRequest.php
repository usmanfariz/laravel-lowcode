<?php

namespace App\Http\Requests\Builder;

use App\Models\FormDetail;
use App\Services\DataSourceResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FormFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $form = $this->route('form');
        $field = $this->route('field');

        return [
            // Keunikan dihitung per detail: field bernama "qty" boleh ada di
            // form induk dan di setiap baris detail sekaligus.
            'field_name' => ['required', 'string', 'max:100', 'regex:/^[a-z_][a-z0-9_]*$/',
                Rule::unique('form_fields', 'field_name')
                    ->where('form_id', $form->id)
                    ->where('form_detail_id', $this->detail()?->id)
                    ->ignore($field?->id)],
            'form_detail_id' => ['nullable', 'integer'],
            'label' => ['required', 'string', 'max:150'],
            'input_type' => ['required', Rule::in(self::inputTypes())],
            'is_required' => ['nullable', 'boolean'],
            'is_readonly' => ['nullable', 'boolean'],
            'is_unique' => ['nullable', 'boolean'],
            'default_value' => ['nullable', 'string', 'max:255'],
            'placeholder' => ['nullable', 'string', 'max:255'],
            'help_text' => ['nullable', 'string', 'max:255'],
            'width' => ['required', 'integer', 'min:1', 'max:12'],
            'order_no' => ['required', 'integer', 'min:0', 'max:9999'],
            'validation' => ['nullable', 'string', 'max:255'],
            'data_source_type' => ['required', Rule::in(['none', 'static', 'table', 'enum'])],
            'data_source' => ['nullable', 'string', 'max:150'],
            'value_field' => ['nullable', 'string', 'max:100'],
            'label_field' => ['nullable', 'string', 'max:100'],
            'data_order_by' => ['nullable', 'string', 'max:100'],
            'depends_on' => ['nullable', 'string', 'max:100'],
            'depends_column' => ['nullable', 'string', 'max:100'],
            'upload_path' => ['nullable', 'string', 'max:255'],
            'allowed_extensions' => ['nullable', 'string', 'max:255'],
            'max_file_size' => ['nullable', 'integer', 'min:1', 'max:102400'],
            'is_active' => ['nullable', 'boolean'],
            'options' => ['array'],
            'options.*.value' => ['nullable', 'string', 'max:150'],
            'options.*.label' => ['nullable', 'string', 'max:150'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $this->checkFieldExistsInTable($validator);
                $this->checkDataSource($validator);
            },
        ];
    }

    /**
     * Nama field harus benar-benar ada sebagai kolom di tabel target. Field
     * yang menunjuk kolom tak ada baru gagal saat form disimpan pengguna —
     * jauh lebih mahal daripada ditolak di sini.
     */
    private function checkFieldExistsInTable(Validator $validator): void
    {
        $name = $this->input('field_name');

        if (! $name) {
            return;
        }

        // Field milik baris detail diperiksa terhadap tabel detailnya.
        $table = $this->detail()?->table_name ?? $this->route('form')->table_name;

        try {
            $columns = app(DataSourceResolver::class)->allowedColumns($table);
        } catch (\Throwable) {
            return; // tabel bermasalah dilaporkan di tempat lain
        }

        if (! in_array($name, $columns, true)) {
            $validator->errors()->add(
                'field_name',
                "Kolom '{$name}' tidak ada di tabel {$table} atau sedang diblokir."
            );
        }
    }

    /** Detail yang sedang disunting, dipastikan milik form ini. */
    public function detail(): ?FormDetail
    {
        $id = $this->input('form_detail_id');

        if (! $id) {
            return null;
        }

        return FormDetail::where('id', $id)
            ->where('form_id', $this->route('form')->id)
            ->first();
    }

    /** Sumber data wajib lengkap dan lolos whitelist sebelum disimpan. */
    private function checkDataSource(Validator $validator): void
    {
        $type = $this->input('data_source_type');

        if ($type === 'table') {
            foreach (['data_source', 'value_field', 'label_field'] as $key) {
                if (! $this->filled($key)) {
                    $validator->errors()->add($key, 'Wajib diisi untuk sumber data tabel.');
                }
            }

            if ($this->filled('data_source')) {
                try {
                    $resolver = app(DataSourceResolver::class);
                    $resolver->assertReadable($this->input('data_source'));

                    foreach (['value_field', 'label_field', 'data_order_by'] as $key) {
                        if ($this->filled($key)) {
                            $resolver->assertColumn($this->input('data_source'), $this->input($key));
                        }
                    }
                } catch (\Throwable $e) {
                    $validator->errors()->add('data_source', $e->getMessage());
                }
            }
        }

        if ($type === 'enum' && ! $this->filled('value_field')) {
            $validator->errors()->add('value_field', 'Wajib diisi untuk sumber data enum.');
        }

        if ($type === 'static') {
            $filled = collect($this->input('options', []))
                ->filter(fn ($o) => ($o['value'] ?? '') !== '' || ($o['label'] ?? '') !== '');

            if ($filled->isEmpty()) {
                $validator->errors()->add('options', 'Isi minimal satu opsi untuk sumber data statis.');
            }
        }
    }

    /** @return array<int, string> */
    public static function inputTypes(): array
    {
        return [
            'text', 'textarea', 'number', 'decimal', 'currency', 'percentage',
            'email', 'password', 'url', 'date', 'datetime', 'time',
            'select', 'select2', 'multi_select', 'ajax_select', 'autocomplete',
            'radio', 'checkbox', 'switch', 'file', 'image', 'hidden', 'editor',
        ];
    }

    public function attributes(): array
    {
        return [
            'field_name' => 'nama kolom', 'label' => 'label',
            'input_type' => 'jenis input', 'width' => 'lebar',
            'order_no' => 'urutan', 'data_source' => 'sumber data',
            'value_field' => 'kolom nilai', 'label_field' => 'kolom label',
        ];
    }
}
