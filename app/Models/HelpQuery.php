<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'question', 'help_article_id', 'score', 'is_answered'])]
class HelpQuery extends Model
{
    /** Riwayat tidak pernah diubah, jadi updated_at tidak ada gunanya. */
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'is_answered' => 'boolean',
            'score' => 'float',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(HelpArticle::class, 'help_article_id');
    }
}
