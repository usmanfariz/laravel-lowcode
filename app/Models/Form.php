<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'name', 'title', 'description', 'connection', 'table_name',
    'primary_key', 'key_type', 'type', 'layout_columns', 'scope_column',
    'lock_condition', 'lock_message',
    'use_soft_delete', 'use_audit_column', 'default_order_column',
    'default_order_direction', 'per_page', 'permission_prefix',
    'allow_create', 'allow_edit', 'allow_delete', 'allow_export', 'allow_print',
    'is_active', 'created_by', 'updated_by',
])]
class Form extends Model
{
    protected function casts(): array
    {
        return [
            'lock_condition' => 'array',
            'use_soft_delete' => 'boolean',
            'use_audit_column' => 'boolean',
            'allow_create' => 'boolean',
            'allow_edit' => 'boolean',
            'allow_delete' => 'boolean',
            'allow_export' => 'boolean',
            'allow_print' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** Field milik form induk saja — field detail dipisah lewat form_detail_id. */
    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)
            ->whereNull('form_detail_id')
            ->where('is_active', true)
            ->orderBy('order_no');
    }

    /**
     * Seluruh field termasuk yang nonaktif — dipakai builder, yang justru
     * harus bisa melihat dan menyalakan kembali field yang dimatikan.
     */
    public function allFields(): HasMany
    {
        return $this->hasMany(FormField::class)
            ->whereNull('form_detail_id')
            ->orderBy('order_no');
    }

    public function details(): HasMany
    {
        return $this->hasMany(FormDetail::class)
            ->where('is_active', true)
            ->orderBy('order_no');
    }

    /** Seluruh detail termasuk yang nonaktif — dipakai builder. */
    public function allDetails(): HasMany
    {
        return $this->hasMany(FormDetail::class)->orderBy('order_no');
    }

    /** Seluruh aksi termasuk yang nonaktif — dipakai builder. */
    public function allActions(): HasMany
    {
        return $this->hasMany(FormAction::class)->orderBy('order_no');
    }

    public function listColumns(): HasMany
    {
        return $this->hasMany(FormListColumn::class)->orderBy('order_no');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(FormAction::class)
            ->where('is_active', true)
            ->orderBy('order_no');
    }

    /** Kode permission untuk satu action, mis. "product.create". */
    public function permission(string $action): ?string
    {
        return $this->permission_prefix ? $this->permission_prefix.'.'.$action : null;
    }
}
