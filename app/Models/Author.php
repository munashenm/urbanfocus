<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Author extends Model
{
    protected $fillable = [
        'name', 'slug', 'title', 'bio', 'avatar',
        'meta_title', 'meta_description', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (Author $author) {
            if (empty($author->slug)) {
                $author->slug = Str::slug($author->name);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return static::where('is_active', true)
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->firstOrFail();
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function seoTitle(): string
    {
        return $this->meta_title ?: ($this->name.' | Urban Focus Blog');
    }

    public function seoDescription(): string
    {
        $base = $this->meta_description ?: ($this->bio ?: "Articles by {$this->name} at Urban Focus.");

        return seo_meta_description($base, ['type' => 'article', 'name' => $this->name]);
    }
}
