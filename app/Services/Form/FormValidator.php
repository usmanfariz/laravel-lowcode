<?php

namespace App\Services\Form;

use App\Models\Form;
use App\Models\FormField;
use App\Services\DataSourceResolver;
use Illuminate\Validation\Rule;

/**
 * Menurunkan aturan validasi Laravel dari metadata field.
 *
 * Aturan dasar berasal dari input_type dan is_required; kolom `validation`
 * menambahkan aturan bebas dari builder.
 */
class FormValidator
{
    public function __construct(
        private readonly FormRenderer $renderer,
        private readonly DataSourceResolver $sources,
    ) {}

    /**
     * @param  mixed  $id  primary key baris yang sedang diubah (null saat tambah),
     *                     dipakai untuk mengecualikan diri sendiri pada rule unique
     * @return array<string, array<int, mixed>>
     */
    public function rules(Form $form, mixed $id = null): array
    {
        $rules = [];

        foreach ($form->fields as $field) {
            $rules[$field->field_name] = $this->fieldRules($field, $form, $id);

            if ($field->isMultiple() && ($rule = $this->valueRule($field))) {
                $rules[$field->field_name.'.*'] = [$rule];
            }
        }

        foreach ($form->details as $detail) {
            foreach ($detail->fields as $field) {
                // Baris detail dikirim sebagai larik: detail[kode][indeks][field]
                $key = "detail.{$detail->code}.*.{$field->field_name}";
                $rules[$key] = $this->fieldRules($field, $form, null);

                if ($field->isMultiple() && ($rule = $this->valueRule($field))) {
                    $rules[$key.'.*'] = [$rule];
                }
            }

            if ($detail->min_rows) {
                $rules["detail.{$detail->code}"] = ['array', "min:{$detail->min_rows}"];
            }
            if ($detail->max_rows) {
                $rules["detail.{$detail->code}"][] = "max:{$detail->max_rows}";
            }
        }

        return $rules;
    }

    /** @return array<int, mixed> */
    private function fieldRules(FormField $field, Form $form, mixed $id): array
    {
        $rules = [$field->is_required ? 'required' : 'nullable'];

        $type = match ($field->input_type) {
            'number' => 'integer',
            'decimal', 'currency', 'percentage' => 'numeric',
            'email' => 'email',
            'url' => 'url',
            'date' => 'date',
            'datetime' => 'date',
            'time' => 'date_format:H:i',
            'switch', 'checkbox' => 'boolean',
            'multi_select' => 'array',
            'file', 'image' => 'file',

            // Field pemilih sengaja tidak diberi aturan tipe: nilainya bisa
            // integer (kunci relasi) maupun teks (enum), dan pembatas yang
            // sebenarnya adalah Rule::in / Rule::exists di bawah. Memaksa
            // 'string' membuat kunci relasi bertipe integer ditolak — tidak
            // terlihat lewat form HTML yang selalu mengirim teks, tapi gagal
            // pada permintaan JSON.
            'select', 'select2', 'ajax_select', 'autocomplete', 'radio' => null,

            default => 'string',
        };

        if ($type !== null) {
            $rules[] = $type;
        }

        if ($field->input_type === 'image') {
            $rules[] = 'image';
        }

        if ($field->max_file_size && in_array($field->input_type, ['file', 'image'], true)) {
            // Metadata menyimpan KB, sama dengan satuan rule max: pada file.
            $rules[] = 'max:'.(int) $field->max_file_size;
        }

        if ($field->allowed_extensions && in_array($field->input_type, ['file', 'image'], true)) {
            $rules[] = 'mimes:'.$field->allowed_extensions;
        }

        // Select hanya membatasi pilihan di tampilan. Tanpa aturan di sini,
        // nilai apa pun bisa dikirim langsung ke server.
        //
        // multi_select dikecualikan: nilainya larik, jadi batasannya dipasang
        // pada kunci "<field>.*" oleh rules(), bukan di sini.
        if (! $field->isMultiple() && ($rule = $this->valueRule($field))) {
            $rules[] = $rule;
        }

        if ($field->is_unique) {
            $rule = Rule::unique($form->table_name, $field->field_name);
            $rules[] = $id === null ? $rule : $rule->ignore($id, $form->primary_key);
        }

        if ($field->validation) {
            // Aturan tambahan dari builder, dipisah pipe seperti sintaks Laravel.
            foreach (explode('|', $field->validation) as $extra) {
                if ($extra !== '') {
                    $rules[] = $extra;
                }
            }
        }

        return $rules;
    }

    /** Batasan nilai yang berasal dari data_source_type, atau null bila tidak ada. */
    private function valueRule(FormField $field): mixed
    {
        return match ($field->data_source_type) {
            'static' => $field->options->isNotEmpty()
                ? Rule::in($field->options->pluck('value')->all())
                : null,

            // Nilai enum diambil dari definisi kolom, sumber kebenaran yang sama
            // dengan yang dipakai renderer.
            'enum' => ($opts = $this->renderer->optionsFor($field))->isNotEmpty()
                ? Rule::in($opts->pluck('value')->all())
                : null,

            // Select bersumber tabel wajib menunjuk baris yang benar-benar ada.
            'table' => $this->existsRule($field),

            default => null,
        };
    }

    private function existsRule(FormField $field): ?object
    {
        if (! $field->data_source || ! $field->value_field) {
            return null;
        }

        try {
            // Tabel dan kolom tetap harus lolos whitelist sebelum masuk
            // klausa exists.
            $this->sources->assertColumn($field->data_source, $field->value_field);
        } catch (\Throwable) {
            return null;
        }

        return Rule::exists($field->data_source, $field->value_field);
    }

    /** @return array<string, string> */
    public function attributes(Form $form): array
    {
        $attributes = [];

        foreach ($form->fields as $field) {
            $attributes[$field->field_name] = mb_strtolower($field->label);
        }

        foreach ($form->details as $detail) {
            foreach ($detail->fields as $field) {
                $attributes["detail.{$detail->code}.*.{$field->field_name}"]
                    = mb_strtolower($field->label);
            }
        }

        return $attributes;
    }
}
