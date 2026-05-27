<?php

namespace App\Services\Blog;

use App\Models\Article;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class BlogSchema
{
    public static function hasArticlesTable(): bool
    {
        return Schema::hasTable('articles');
    }

    public static function hasColumn(string $column): bool
    {
        return self::hasArticlesTable() && Schema::hasColumn('articles', $column);
    }

    public static function hasAuthors(): bool
    {
        return Schema::hasTable('authors') && self::hasColumn('author_id');
    }

    public static function hasTags(): bool
    {
        return Schema::hasTable('tags') && Schema::hasTable('article_tag');
    }

    public static function hasFeatured(): bool
    {
        return self::hasColumn('is_featured');
    }

    public static function hasBlogTopics(): bool
    {
        return Schema::hasTable('blog_topics');
    }

    public static function hasAnalyticsSnapshots(): bool
    {
        return Schema::hasTable('blog_analytics_snapshots');
    }

    public static function adminReady(): bool
    {
        return self::hasColumn('category')
            && self::hasAuthors()
            && self::hasBlogTopics();
    }

    /** @return list<string> */
    public static function missingForAdmin(): array
    {
        $missing = [];

        if (! self::hasColumn('category') || ! self::hasColumn('is_featured')) {
            $missing[] = 'articles blog columns (migration 000091)';
        }

        if (! self::hasAuthors()) {
            $missing[] = 'authors table';
        }

        if (! self::hasTags()) {
            $missing[] = 'tags tables';
        }

        if (! self::hasBlogTopics()) {
            $missing[] = 'blog_topics table (migration 000092)';
        }

        if (! self::hasColumn('faqs') || ! self::hasColumn('toc_enabled')) {
            $missing[] = 'article automation columns (migration 000092)';
        }

        return $missing;
    }

    /** @param Builder<\App\Models\Article> $query */
    public static function withOptionalRelations(Builder $query): Builder
    {
        $with = [];

        if (self::hasAuthors()) {
            $with[] = 'author';
        }

        if (self::hasTags()) {
            $with[] = 'tags';
        }

        return $with === [] ? $query : $query->with($with);
    }

    /** @param array<string, mixed> $data */
    public static function onlyExistingColumns(array $data): array
    {
        $allowed = [
            'title', 'slug', 'excerpt', 'content', 'image',
            'meta_title', 'meta_description', 'is_published', 'published_at',
            'source_url', 'source_name', 'external_id',
        ];

        foreach (['category', 'is_featured', 'author_id', 'faqs', 'focus_keywords', 'social_snippets', 'toc_enabled', 'views'] as $column) {
            if (self::hasColumn($column)) {
                $allowed[] = $column;
            }
        }

        return array_intersect_key($data, array_flip($allowed));
    }

    public static function stripUnknownAttributes(Article $article): Article
    {
        foreach (['category', 'is_featured', 'author_id', 'faqs', 'focus_keywords', 'social_snippets', 'toc_enabled', 'views'] as $column) {
            if (! self::hasColumn($column)) {
                $article->offsetUnset($column);
            }
        }

        return $article;
    }
}
