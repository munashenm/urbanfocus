<?php

namespace App\Services\Blog;

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
}
