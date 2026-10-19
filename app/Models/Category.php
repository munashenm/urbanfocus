<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'image',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Category $category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function activeProducts(): HasMany
    {
        return $this->products()->where('is_active', true);
    }

    public function isTopLevel(): bool
    {
        return $this->parent_id === null;
    }

    public function urlPath(): string
    {
        if ($this->parent_id && $this->parent) {
            return $this->parent->slug.'/'.$this->slug;
        }

        return $this->slug;
    }

    public function url(): string
    {
        $this->loadMissing('parent');

        if ($this->parent_id && $this->parent) {
            return route('categories.show.child', [
                'parent' => $this->parent->slug,
                'child' => $this->slug,
            ]);
        }

        return route('categories.show', $this);
    }

    /** @return list<array{name: string, category: self}> */
    public function breadcrumbChain(): array
    {
        $chain = [];
        $current = $this;

        while ($current) {
            array_unshift($chain, ['name' => $current->name, 'category' => $current]);
            $current = $current->parent;
        }

        return $chain;
    }

    public function fullPathLabel(): string
    {
        return collect($this->breadcrumbChain())->pluck('name')->implode(' > ');
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeVisibleInCatalog($query)
    {
        $excluded = app(\App\Services\CatalogFilterService::class)->excludedCategoryIds();

        if ($excluded !== []) {
            $query->whereNotIn('id', $excluded);
        }

        return $query;
    }

    /** @return array<int> */
    public static function descendantIds(int $categoryId): array
    {
        $map = app(\App\Services\CatalogNavigation::class)->childrenMap();
        $ids = [$categoryId];
        $queue = [$categoryId];

        while ($queue !== []) {
            $id = array_shift($queue);
            foreach ($map[$id] ?? [] as $childId) {
                if (! in_array($childId, $ids, true)) {
                    $ids[] = $childId;
                    $queue[] = $childId;
                }
            }
        }

        return $ids;
    }

    public function seoTitle(): string
    {
        if (! empty($this->attributes['meta_title'])) {
            return seo_page_title((string) $this->attributes['meta_title']);
        }

        return seo_page_title($this->name.' | Buy in South Africa | Urban Focus');
    }

    public function seoDescription(): string
    {
        $value = trim((string) ($this->attributes['meta_description'] ?? ''));

        if ($value !== '') {
            return seo_meta_description($value, [
                'type' => 'category',
                'name' => $this->name,
            ]);
        }

        $source = strip_tags($this->description ?: '');

        if ($source === '') {
            $source = 'Shop '.$this->name.' in South Africa at Urban Focus';
        }

        return seo_meta_description($source, [
            'type' => 'category',
            'name' => $this->name,
        ]);
    }
}
