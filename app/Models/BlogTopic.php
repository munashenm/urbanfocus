<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogTopic extends Model
{
    protected $fillable = [
        'title', 'slug', 'source', 'source_url', 'score',
        'keywords', 'metadata', 'status', 'article_id', 'discovered_at',
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'metadata' => 'array',
            'discovered_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function scopeSuggested($query)
    {
        return $query->where('status', 'suggested')->orderByDesc('score');
    }
}
