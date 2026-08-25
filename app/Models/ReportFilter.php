<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'report_id', 'label', 'column_name', 'operator', 'input_type',
    'data_source_type', 'data_source', 'value_field', 'label_field',
    'data_filter', 'static_options', 'default_values', 'is_required',
    'width', 'order_no', 'is_active',
])]
class ReportFilter extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'data_filter' => 'array',
            'static_options' => 'array',
            'default_values' => 'array',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** Berapa nilai yang dibutuhkan operator ini. */
    public function valueCount(): int
    {
        return match ($this->operator) {
            'is_null', 'is_not_null' => 0,
            'between' => 2,
            'in', 'not_in' => -1, // banyak
            default => 1,
        };
    }

    public function isMultiValue(): bool
    {
        return $this->valueCount() === -1;
    }
}
