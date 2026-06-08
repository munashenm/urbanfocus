<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Article extends Model
{
    protected $fillable = [
        'title', 'slug', 'excerpt', 'content', 'image', 'category', 'is_featured',
        'author_id', 'faqs', 'focus_keywords', 'social_snippets', 'toc_enabled', 'views',
        'source_url', 'source_name', 'external_id',
        'meta_title', 'meta_description', 'is_published', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'toc_enabled' => 'boolean',
            'faqs' => 'array',
            'focus_keywords' => 'array',
            'social_snippets' => 'array',
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

    public function resolveRouteBinding($value, $field = null)
    {
        return static::published()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->firstOrFail();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function tags(): BelongsToMany
    {
        // The article_tag pivot table has no created_at/updated_at columns
        // (see migration 000092), so withTimestamps() would break queries.
        return $this->belongsToMany(Tag::class);
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

    public function authorName(): string
    {
        return $this->author?->name ?? 'Urban Focus Team';
    }

    /** @return list<array{question: string, answer: string}> */
    public function faqList(): array
    {
        $faqs = $this->faqs ?? [];

        return array_values(array_filter(array_map(function ($faq) {
            if (! is_array($faq)) {
                return null;
            }

            $question = trim((string) ($faq['question'] ?? ''));
            $answer = trim((string) ($faq['answer'] ?? ''));

            return ($question !== '' && $answer !== '') ? compact('question', 'answer') : null;
        }, $faqs)));
    }

    /** @return list<string> */
    public function keywordList(): array
    {
        return array_values(array_filter(array_map('trim', $this->focus_keywords ?? [])));
    }

    /** @return array<string, string> */
    public function socialSnippetList(): array
    {
        return array_filter($this->social_snippets ?? []);
    }

    public function categoryKey(): ?string
    {
        $category = trim((string) ($this->category ?? ''));

        return $category !== '' ? $category : null;
    }

    public function categoryLabel(): ?string
    {
        $key = $this->categoryKey();

        return $key ? (config("blog.categories.{$key}.label") ?? Str::title(str_replace('-', ' ', $key))) : null;
    }

    /** @return array<string, mixed>|null */
    public function categoryCta(): ?array
    {
        $key = $this->categoryKey();

        if (! $key) {
            return null;
        }

        $cta = config("blog.categories.{$key}");

        return is_array($cta) ? $cta : null;
    }

    public function displayImageUrl(): string
    {
        if ($this->image) {
            if (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')) {
                return $this->image;
            }

            return storage_public_url($this->image) ?? asset(ltrim($this->image, '/'));
        }

        $key = $this->categoryKey();
        $placeholder = $key ? config("blog.category_placeholders.{$key}") : null;

        return asset($placeholder ?: 'images/blog/default.svg');
    }

    public function ogImageUrl(): string
    {
        return $this->displayImageUrl();
    }

    public function readingTimeMinutes(): int
    {
        $text = strip_tags($this->content ?: $this->excerpt ?: '');
        $words = str_word_count($text);

        return max(1, (int) ceil($words / 200));
    }

    public function isSynced(): bool
    {
        return filled($this->source_url);
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true)
            ->where(function ($q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    public function scopeFeatured($query)
    {
        if (Schema::hasColumn('articles', 'is_featured')) {
            return $query->where('is_featured', true);
        }

        return $query;
    }

    public function scopeInCategory($query, ?string $category)
    {
        if ($category && array_key_exists($category, config('blog.categories', [])) && Schema::hasColumn('articles', 'category')) {
            $query->where('category', $category);
        }

        return $query;
    }

    public function scopeWithTag($query, string $tagSlug)
    {
        if (! Schema::hasTable('tags') || ! Schema::hasTable('article_tag')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('tags', fn ($q) => $q->where('slug', $tagSlug));
    }
}
