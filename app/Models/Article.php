<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Article extends Model
{
    protected $fillable = [
        'title', 'slug', 'excerpt', 'content', 'image',
        'source_url', 'source_name', 'external_id',
        'meta_title', 'meta_description', 'is_published', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Article $article) {
            if (empty($article->slug)) {
                $article->slug = Str::slug($article->title);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function seoTitle(): string
    {
        return $this->meta_title ?: ($this->title.' | Urban Focus Blog');
    }

    public function seoDescription(): string
    {
        $value = trim((string) ($this->meta_description ?? ''));

        if ($value !== '') {
            return seo_meta_description($value, [
                'type' => 'article',
                'name' => $this->title,
            ]);
        }

        $source = strip_tags($this->excerpt ?: '');

        if ($source === '') {
            $source = $this->title;
        }

        return seo_meta_description($source, [
            'type' => 'article',
            'name' => $this->title,
        ]);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->where(function ($q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }
}
