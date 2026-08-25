<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'report_id', 'join_type', 'table_name', 'table_alias',
    'first_column', 'operator', 'second_column', 'order_no', 'is_active',
])]
class ReportJoin extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Alias yang dipakai di query; jatuh ke nama tabel bila tidak diisi. */
    public function alias(): string
    {
        return $this->table_alias ?: $this->table_name;
    }
}
