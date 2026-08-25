<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'form_id', 'form_detail_id', 'field_name', 'label', 'input_type',
    'is_required', 'is_readonly', 'is_unique', 'default_value', 'placeholder',
    'help_text', 'width', 'order_no', 'validation',
    'data_source_type', 'data_source', 'value_field', 'label_field',
    'data_filter', 'data_order_by', 'depends_on', 'depends_column',
    'show_condition', 'upload_path', 'allowed_extensions', 'max_file_size',
    'is_active',
])]
class FormField extends Model
{
    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_readonly' => 'boolean',
            'is_unique' => 'boolean',
            'is_active' => 'boolean',
            'data_filter' => 'array',
            'show_condition' => 'array',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(FormFieldOption::class)
            ->where('is_active', true)
            ->orderBy('order_no');
    }

    /** Nama komponen Blade yang menangani input_type ini. */
    public function component(): string
    {
        return match ($this->input_type) {
            'textarea' => 'form.textarea',
            'select', 'select2', 'multi_select', 'ajax_select', 'autocomplete' => 'form.select',
            'radio' => 'form.radio',
            'checkbox' => 'form.checkbox',
            'switch' => 'form.switch',
            'file', 'image' => 'form.file',
            'editor' => 'form.editor',
            default => 'form.input',
        };
    }

    /** Apakah nilai field ini berupa larik (multi_select). */
    public function isMultiple(): bool
    {
        return $this->input_type === 'multi_select';
    }

    /** Lebar kolom Bootstrap; metadata menyimpan 1–12, default 12. */
    public function columnClass(): string
    {
        $width = (int) ($this->width ?: 12);

        return 'col-md-'.max(1, min(12, $width));
    }
}
