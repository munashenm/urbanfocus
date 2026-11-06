<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CatalogNavigation
{
    public const EXCLUDED_KEY = 'catalog.excluded_category_ids';

    public const STOCKED_KEY = 'catalog.stocked_category_ids';

    public const CHILDREN_KEY = 'catalog.children_map';

    public const MEGA_KEY = 'catalog.mega_menu';

    public const NAV_KEY = 'catalog.nav_categories';

    public const TREE_HASH_KEY = 'catalog.canonical_tree_hash';

    public const PAGE_VERSION_KEY = 'page.cache.version';

    public static function forget(bool $tree = false): void
    {
        foreach ([
            self::EXCLUDED_KEY,
            self::STOCKED_KEY,
            self::CHILDREN_KEY,
            self::MEGA_KEY,
            self::NAV_KEY,
        ] as $key) {
            Cache::forget($key);
        }

        if ($tree) {
            Cache::forget(self::TREE_HASH_KEY);
        }

        $version = (int) Cache::get(self::PAGE_VERSION_KEY, 1);
        Cache::forever(self::PAGE_VERSION_KEY, $version + 1);
    }

    /**
     * Active category children keyed by parent id. Root categories use key 0.
     *
     * @return array<int, list<int>>
     */
    public function childrenMap(): array
    {
        return Cache::remember(self::CHILDREN_KEY, 600, function () {
            $map = [];

            foreach (Category::query()->where('is_active', true)->get(['id', 'parent_id']) as $category) {
                $parent = (int) ($category->parent_id ?? 0);
                $map[$parent][] = (int) $category->id;
            }

            return $map;
        });
    }

    /**
     * Categories that contain an active product, including ancestors.
     * Empty categories stay reachable by URL but drop out of menus and sitemaps.
     *
     * @return list<int>
     */
    public function stockedCategoryIds(): array
    {
        return Cache::remember(self::STOCKED_KEY, 600, function () {
            $direct = Product::query()
                ->where('is_active', true)
                ->whereNotNull('category_id')
                ->distinct()
                ->pluck('category_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $parentOf = [];
            foreach ($this->childrenMap() as $parentId => $childIds) {
                foreach ($childIds as $childId) {
                    $parentOf[$childId] = (int) $parentId;
                }
            }

            $stocked = [];
            foreach ($direct as $id) {
                $current = $id;
                $guard = 0;
                while ($current && $guard < 12) {
                    $stocked[$current] = true;
                    $parent = $parentOf[$current] ?? 0;
                    if ($parent === 0) {
                        break;
                    }
                    $current = $parent;
                    $guard++;
                }
            }

            return array_map('intval', array_keys($stocked));
        });
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return Collection<int, Category>
     */
    public function filterStocked(Collection $categories, bool $filterChildren = true): Collection
    {
        $stocked = array_flip($this->stockedCategoryIds());

        return $categories
            ->filter(fn (Category $category) => isset($stocked[(int) $category->id]))
            ->values()
            ->each(function (Category $category) use ($stocked, $filterChildren) {
                if ($filterChildren && $category->relationLoaded('children')) {
                    $category->setRelation(
                        'children',
                        $category->children
                            ->filter(fn (Category $child) => isset($stocked[(int) $child->id]))
                            ->values()
                    );
                }
            });
    }
}
