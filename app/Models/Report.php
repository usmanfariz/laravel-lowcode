<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'name', 'title', 'description', 'type', 'source_type',
    'connection', 'base_table', 'base_alias', 'use_soft_delete', 'raw_query', 'group_by', 'having',
    'default_order_column', 'default_order_direction', 'per_page',
    'scope_column', 'permission_code', 'allow_export_excel', 'allow_export_pdf',
    'allow_export_csv', 'allow_print', 'export_queue_threshold',
    'is_active', 'created_by', 'updated_by',
])]
class Report extends Model
{
    protected function casts(): array
    {
        return [
            'use_soft_delete' => 'boolean',
            'allow_export_excel' => 'boolean',
            'allow_export_pdf' => 'boolean',
            'allow_export_csv' => 'boolean',
            'allow_print' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function joins(): HasMany
    {
        return $this->hasMany(ReportJoin::class)->where('is_active', true)->orderBy('order_no');
    }

    public function columns(): HasMany
    {
        return $this->hasMany(ReportColumn::class)->where('is_active', true)->orderBy('order_no');
    }

    public function filters(): HasMany
    {
        return $this->hasMany(ReportFilter::class)->where('is_active', true)->orderBy('order_no');
    }

    /** Alias tabel utama; jatuh ke nama tabelnya bila tidak diisi. */
    public function alias(): string
    {
        return $this->base_alias ?: $this->base_table;
    }
}
