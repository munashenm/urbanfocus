<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProductCleanupService
{
    public function __construct(
        protected ImageService $images,
        protected CatalogFilterService $catalogFilter,
    ) {}

    /** @return array{deleted: int, images_removed: int} */
    public function deleteAll(): array
    {
        $deleted = 0;
        $imagesRemoved = 0;

        Product::withTrashed()->with('images')->orderBy('id')->chunk(50, function ($products) use (&$deleted, &$imagesRemoved) {
            foreach ($products as $product) {
                $imagesRemoved += $this->safelyDeleteProduct($product);
                $deleted++;
            }
        });

        $this->removeOrphanProductStorage();

        return ['deleted' => $deleted, 'images_removed' => $imagesRemoved];
    }

    /**
     * @return array{
     *     terms_loaded: int,
     *     total_products: int,
     *     excluded_categories: list<string>,
     *     products_to_delete: int,
     *     categories_to_delete: int,
     *     sample_products: list<string>
     * }
     */
    public function previewNonItCleanup(): array
    {
        $excludedCategories = $this->catalogFilter->collectExcludedCategories();
        $productsToDelete = 0;
        $sampleProducts = [];

        foreach (Product::query()->with('category')->lazyById(100) as $product) {
            if (! $this->catalogFilter->isProductExcluded($product)) {
                continue;
            }

            $productsToDelete++;

            if (count($sampleProducts) < 15) {
                $sampleProducts[] = $product->name;
            }
        }

        return [
            'terms_loaded' => count($this->catalogFilter->excludedProductTerms()),
            'it_heads_loaded' => count($this->catalogFilter->itCategoryHeads()),
            'total_products' => Product::query()->count(),
            'excluded_categories' => $excludedCategories->pluck('name')->values()->all(),
            'products_to_delete' => $productsToDelete,
            'categories_to_delete' => $excludedCategories->count(),
            'sample_products' => $sampleProducts,
        ];
    }

    /** @return array{products_deleted: int, categories_deleted: int, images_removed: int, errors: array<int, string>} */
    public function removeNonItProducts(): array
    {
        @set_time_limit(0);

        $productsDeleted = 0;
        $imagesRemoved = 0;
        $errors = [];

        $excludedCategoryIds = $this->catalogFilter->collectExcludedCategoryIds();

        // 1. Delete every non-IT product (blocked category or blocked name).
        foreach (Product::with(['images', 'category'])->lazyById(25) as $product) {
            if (! $this->catalogFilter->isProductExcluded($product)) {
                continue;
            }

            $this->deleteProductSafely($product, $productsDeleted, $imagesRemoved, $errors);
        }

        // 2. Remove any products still assigned to excluded categories.
        if ($excludedCategoryIds !== []) {
            foreach (Product::with('images')->whereIn('category_id', $excludedCategoryIds)->lazyById(25) as $product) {
                $this->deleteProductSafely($product, $productsDeleted, $imagesRemoved, $errors);
            }
        }

        // 3. Delete excluded categories once they are empty (deepest first).
        $categoriesDeleted = $this->deleteExcludedCategories($excludedCategoryIds);

        Cache::forget('sitemap.xml');

        return [
            'products_deleted' => $productsDeleted,
            'categories_deleted' => $categoriesDeleted,
            'images_removed' => $imagesRemoved,
            'errors' => $errors,
        ];
    }

    /** @param array<int, string> $errors */
    protected function deleteProductSafely(Product $product, int &$productsDeleted, int &$imagesRemoved, array &$errors): void
    {
        if (! Product::whereKey($product->id)->exists()) {
            return;
        }

        try {
            $imagesRemoved += $this->safelyDeleteProduct($product);
            $productsDeleted++;
        } catch (\Throwable $e) {
            $errors[] = ($product->sku ?: $product->name).': '.$e->getMessage();
            Log::warning('Non-IT product delete failed', [
                'product_id' => $product->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function safelyDeleteProduct(Product $product): int
    {
        $imagesRemoved = 0;

        foreach ($product->images as $image) {
            try {
                if ($image->path) {
                    $this->images->delete($image->path);
                }
            } catch (\Throwable) {
                // continue — still remove DB record
            }

            $image->delete();
            $imagesRemoved++;
        }

        ProductImage::where('product_id', $product->id)->delete();
        $product->forceDelete();

        return $imagesRemoved;
    }

    /** @param list<int> $excludedCategoryIds */
    protected function deleteExcludedCategories(array $excludedCategoryIds): int
    {
        if ($excludedCategoryIds === []) {
            return 0;
        }

        $deleted = 0;
        $passes = 0;

        while ($passes < 20) {
            $passes++;
            $removedThisPass = 0;

            /** @var Collection<int, Category> $categories */
            $categories = Category::query()
                ->with('parent')
                ->whereIn('id', $excludedCategoryIds)
                ->get()
                ->sortByDesc(fn (Category $category) => $this->categoryDepth($category));

            foreach ($categories as $category) {
                if (! Category::whereKey($category->id)->exists()) {
                    continue;
                }

                if ($category->products()->exists()) {
                    continue;
                }

                if ($category->children()->exists()) {
                    continue;
                }

                try {
                    $category->delete();
                    $deleted++;
                    $removedThisPass++;
                } catch (\Throwable $e) {
                    Log::warning('Excluded category delete failed', [
                        'category_id' => $category->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            if ($removedThisPass === 0) {
                break;
            }
        }

        return $deleted;
    }

    protected function categoryDepth(Category $category): int
    {
        $depth = 0;
        $current = $category;

        while ($current->parent_id) {
            if (! $current->relationLoaded('parent')) {
                $current->load('parent');
            }

            $current = $current->parent;

            if (! $current) {
                break;
            }

            $depth++;
        }

        return $depth;
    }

    protected function removeOrphanProductStorage(): void
    {
        $disk = Storage::disk('public');
        if (! $disk->exists('products')) {
            return;
        }

        foreach ($disk->directories('products') as $directory) {
            $disk->deleteDirectory($directory);
        }
    }
}
