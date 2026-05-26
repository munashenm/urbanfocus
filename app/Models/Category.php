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
        $all = static::where('is_active', true)->get(['id', 'parent_id']);
        $ids = [$categoryId];
        $changed = true;

        while ($changed) {
            $changed = false;
            foreach ($all as $cat) {
                if ($cat->parent_id && in_array($cat->parent_id, $ids, true) && ! in_array($cat->id, $ids, true)) {
                    $ids[] = $cat->id;
                    $changed = true;
                }
            }
        }

        return $ids;
    }

    public function seoTitle(): string
    {
        if (! empty($this->attributes['meta_title'])) {
            return $this->attributes['meta_title'];
        }

        return $this->name.' | Buy in South Africa | Urban Focus';
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
