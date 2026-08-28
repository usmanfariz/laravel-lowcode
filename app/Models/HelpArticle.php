<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'code', 'category', 'question', 'answer', 'keywords',
    'link_route', 'link_label', 'permission_code',
    'is_featured', 'order_no', 'is_active',
])]
class HelpArticle extends Model
{
    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return array<int, string> */
    public function keywordList(): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', (string) $this->keywords)),
            fn (string $k) => $k !== ''
        ));
    }

    /**
     * Tujuan tombol pada balon jawaban.
     *
     * Route yang belum terdaftar dikembalikan null, bukan melempar exception:
     * satu artikel yang menyebut route usang tidak boleh mematikan chatbot.
     */
    public function linkUrl(): ?string
    {
        if (! $this->link_route) {
            return null;
        }

        if (str_starts_with($this->link_route, '/') || str_starts_with($this->link_route, 'http')) {
            return $this->link_route;
        }

        return \Route::has($this->link_route) ? route($this->link_route) : null;
    }

    /** Tombol hanya ditawarkan kepada yang memang bisa membuka halamannya. */
    public function linkVisibleTo(?User $user): bool
    {
        if (! $this->permission_code) {
            return true;
        }

        return $user?->hasPermission($this->permission_code) === true;
    }
}
