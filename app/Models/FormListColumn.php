<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'form_id', 'label', 'source_type', 'column_name', 'relation_table',
    'relation_key', 'relation_label', 'expression', 'format', 'format_options',
    'align', 'width', 'is_visible', 'is_searchable', 'is_sortable', 'order_no',
])]
class FormListColumn extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'format_options' => 'array',
            'is_visible' => 'boolean',
            'is_searchable' => 'boolean',
            'is_sortable' => 'boolean',
        ];
    }
}
