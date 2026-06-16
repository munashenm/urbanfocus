<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;

class CategoryMergeService
{
    public function __construct(
        protected CategoryReorganizationService $reorganization,
        protected ProductSeoService $productSeo,
        protected CategoryMapperService $categoryMapper,
    ) {}

    /** @return array<string, mixed> */
    public function preview(): array
    {
        $this->categoryMapper->ensureCanonicalTree();

        return [
            'reorganization' => $this->reorganization->preview(),
            'legacy_products' => $this->countLegacyCategoryProducts(),
        ];
    }

    /**
     * Remap all products into canonical categories, build redirects, and deactivate empty legacy categories.
     *
     * @return array<string, mixed>
     */
    public function merge(bool $backup = true, ?int $limit = null): array
    {
        $this->categoryMapper->ensureCanonicalTree();

        $reorganize = $this->reorganization->reorganize(backup: $backup, limit: $limit);
        $assign = $this->productSeo->assignProductCategories(limit: $limit);

        return [
            'reorganize' => $reorganize,
            'assign' => $assign,
            'legacy_products_remaining' => $this->countLegacyCategoryProducts(),
        ];
    }

    protected function countLegacyCategoryProducts(): int
    {
        $canonicalIds = $this->canonicalCategoryIds();

        return Product::query()
            ->whereNotNull('category_id')
            ->whereNotIn('category_id', $canonicalIds)
            ->count();
    }

    /** @return list<int> */
    protected function canonicalCategoryIds(): array
    {
        $slugs = $this->categoryMapper->canonicalSlugs();

        return Category::query()
            ->whereIn('slug', $slugs)
            ->pluck('id')
            ->all();
    }
}
