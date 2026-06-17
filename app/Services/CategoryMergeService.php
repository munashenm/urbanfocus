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
            'total_products' => Product::count(),
            'categorized_products' => Product::whereNotNull('category_id')->count(),
            'migration_tables_ready' => $this->reorganization->migrationTablesReady(),
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

    /**
     * Batch remap for cPanel (avoids HTTP timeouts on large catalogs).
     *
     * @return array<string, mixed>
     */
    public function mergeBatch(int $offset, int $batchSize, bool $backupOnFirst = false): array
    {
        $this->categoryMapper->ensureCanonicalTree();

        $reorganize = $this->reorganization->remapProductBatch($offset, $batchSize, $backupOnFirst);

        return [
            'reorganize' => $reorganize,
            'legacy_products_remaining' => $this->countLegacyCategoryProducts(),
        ];
    }

    /**
     * Heuristic assignment batch for cPanel.
     *
     * @return array<string, mixed>
     */
    public function assignBatch(int $offset, int $batchSize): array
    {
        $this->categoryMapper->ensureCanonicalTree();

        $assign = $this->productSeo->assignProductCategories(offset: $offset, limit: $batchSize);
        $total = Product::count();
        $nextOffset = $offset + $assign['processed'];
        $hasMore = $nextOffset < $total;

        return [
            'assign' => $assign,
            'has_more' => $hasMore,
            'next_offset' => $nextOffset,
            'total' => $total,
            'legacy_products_remaining' => $this->countLegacyCategoryProducts(),
        ];
    }

    /** @return array<string, mixed> */
    public function finalize(): array
    {
        $this->categoryMapper->ensureCanonicalTree();

        $finalize = $this->reorganization->finalizeMigration();
        $assign = $this->productSeo->assignProductCategories();

        return [
            'finalize' => $finalize,
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
