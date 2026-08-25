<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'connection', 'table_name', 'label', 'primary_key',
    'is_readable', 'is_writable', 'blocked_columns', 'is_active',
])]
class DataSource extends Model
{
    protected function casts(): array
    {
        return [
            'is_readable' => 'boolean',
            'is_writable' => 'boolean',
            'blocked_columns' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
