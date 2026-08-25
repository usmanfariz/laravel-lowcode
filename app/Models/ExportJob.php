<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'source_type', 'source_code', 'title', 'format', 'params',
    'status', 'row_count', 'file_path', 'file_size', 'error',
    'started_at', 'finished_at',
])]
class ExportJob extends Model
{
    protected function casts(): array
    {
        return [
            'params' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isDownloadable(): bool
    {
        return $this->status === 'done' && $this->file_path !== null;
    }

    /** Lama pengerjaan dalam detik, untuk ditampilkan di daftar. */
    public function durationSeconds(): ?int
    {
        if (! $this->started_at || ! $this->finished_at) {
            return null;
        }

        return (int) $this->started_at->diffInSeconds($this->finished_at);
    }
}
