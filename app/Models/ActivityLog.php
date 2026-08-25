<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'event', 'module', 'table_name', 'record_id', 'description',
    'old_values', 'new_values', 'ip_address', 'user_agent', 'url', 'http_method',
])]
class ActivityLog extends Model
{
    /** Tabel ini hanya punya created_at; log tidak pernah diubah. */
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Kolom yang berubah antara nilai lama dan baru. */
    public function changedKeys(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];

        return array_values(array_unique([
            ...array_keys(array_diff_assoc(
                array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $new),
                array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $old),
            )),
        ]));
    }
}
