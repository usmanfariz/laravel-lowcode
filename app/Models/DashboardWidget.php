<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'code', 'title', 'type', 'icon', 'color', 'width', 'link_url',
    'source_table', 'source_column', 'aggregate', 'filter', 'format',
    'report_code', 'row_limit', 'content',
    'permission_code', 'order_no', 'is_active',
])]
class DashboardWidget extends Model
{
    protected function casts(): array
    {
        return [
            'filter' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** Widget yang menumpang report punya sumber datanya di sana. */
    public function usesReport(): bool
    {
        return in_array($this->type, ['chart', 'table'], true);
    }

    /** Lebar kolom Bootstrap; metadata menyimpan 1–12. */
    public function columnClass(): string
    {
        return 'col-md-'.max(1, min(12, (int) ($this->width ?: 3)));
    }
}
