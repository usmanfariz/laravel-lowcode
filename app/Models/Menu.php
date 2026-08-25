<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'parent_id', 'code', 'name', 'icon', 'link_type', 'target_value',
    'permission_code', 'open_new_tab', 'order_no', 'is_active',
])]
class Menu extends Model
{
    protected function casts(): array
    {
        return [
            'open_new_tab' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order_no');
    }

    /**
     * URL tujuan menu. Menu bertipe "header" tidak punya tujuan — ia hanya
     * pembungkus anak-anaknya, jadi dikembalikan '#'.
     *
     * Route yang belum terdaftar dikembalikan '#' juga, bukan melempar
     * exception: satu menu salah ketik tidak boleh mematikan seluruh sidebar.
     */
    public function url(): string
    {
        return match ($this->link_type) {
            'url' => $this->target_value ?? '#',
            'form' => $this->target_value ? url('forms/'.$this->target_value) : '#',
            'report' => $this->target_value ? url('reports/'.$this->target_value) : '#',
            'route' => $this->target_value && \Route::has($this->target_value)
                ? route($this->target_value)
                : '#',
            default => '#',
        };
    }
}
