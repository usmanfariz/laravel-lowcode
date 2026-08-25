<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'report_id', 'label', 'source_type', 'column_name', 'expression',
    'column_alias', 'aggregate', 'format', 'decimal_places', 'align', 'width',
    'is_visible', 'is_sortable', 'is_searchable', 'is_group_column',
    'show_total', 'order_no', 'is_active',
])]
class ReportColumn extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'is_sortable' => 'boolean',
            'is_searchable' => 'boolean',
            'is_group_column' => 'boolean',
            'show_total' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function hasAggregate(): bool
    {
        return $this->aggregate && $this->aggregate !== 'none';
    }
}
