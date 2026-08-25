<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'form_id', 'code', 'title', 'table_name', 'primary_key', 'foreign_key',
    'min_rows', 'max_rows', 'allow_add', 'allow_delete', 'show_total_row',
    'order_no', 'is_active',
])]
class FormDetail extends Model
{
    protected function casts(): array
    {
        return [
            'allow_add' => 'boolean',
            'allow_delete' => 'boolean',
            'show_total_row' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class, 'form_detail_id')
            ->where('is_active', true)
            ->orderBy('order_no');
    }
}
