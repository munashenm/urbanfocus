<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
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
     *     products_in_excluded_categories: int,
     *     products_by_name: int,
     *     sample_products_by_name: list<string>
     * }
     */
    public function previewNonItCleanup(): array
    {
        $excludedCategories = Category::query()
            ->get()
            ->filter(fn (Category $category) => $this->catalogFilter->isCategoryExcluded($category));

        $excludedCategoryIds = $excludedCategories->pluck('id')->all();

        $productsInExcludedCategories = $excludedCategoryIds === []
            ? 0
            : Product::query()->whereIn('category_id', $excludedCategoryIds)->count();

        $productsByName = 0;
        $sampleProductsByName = [];

        foreach (Product::query()->select(['id', 'name', 'short_description', 'category_id'])->lazyById(100) as $product) {
            if (in_array($product->category_id, $excludedCategoryIds, true)) {
                continue;
            }

            if (! $this->catalogFilter->isProductNameExcluded($product)) {
                continue;
            }

            $productsByName++;

            if (count($sampleProductsByName) < 15) {
                $sampleProductsByName[] = $product->name;
            }
        }

        return [
            'terms_loaded' => count($this->catalogFilter->excludedProductTerms()),
            'total_products' => Product::query()->count(),
            'excluded_categories' => $excludedCategories->pluck('name')->values()->all(),
            'products_in_excluded_categories' => $productsInExcludedCategories,
            'products_by_name' => $productsByName,
            'sample_products_by_name' => $sampleProductsByName,
        ];
    }

    /** @return array{products_deleted: int, categories_deleted: int, images_removed: int, errors: array<int, string>} */
    public function removeNonItProducts(): array
    {
        @set_time_limit(0);

        $productsDeleted = 0;
        $imagesRemoved = 0;
        $categoriesDeleted = 0;
        $errors = [];

        $excludedCategoryIds = Category::query()
            ->get()
            ->filter(fn (Category $category) => $this->catalogFilter->isCategoryExcluded($category))
            ->pluck('id')
            ->all();

        if ($excludedCategoryIds !== []) {
            $query = Product::with('images')->whereIn('category_id', $excludedCategoryIds);

            foreach ($query->lazyById(25) as $product) {
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
        }

        foreach (Product::with('images')->lazyById(25) as $product) {
            if (! $this->catalogFilter->isProductNameExcluded($product)) {
                continue;
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

        $categoriesDeleted = $this->deleteExcludedCategories();

        Cache::forget('sitemap.xml');

        return [
            'products_deleted' => $productsDeleted,
            'categories_deleted' => $categoriesDeleted,
            'images_removed' => $imagesRemoved,
            'errors' => $errors,
        ];
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

    protected function deleteExcludedCategories(): int
    {
        $deleted = 0;
        $passes = 0;

        while ($passes < 10) {
            $passes++;
            $removedThisPass = 0;

            $categories = Category::query()
                ->get()
                ->sortByDesc(fn (Category $category) => $this->categoryDepth($category));

            foreach ($categories as $category) {
                if (! $this->catalogFilter->isCategoryExcluded($category)) {
                    continue;
                }

                if ($category->products()->exists() || $category->children()->exists()) {
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
        $current = $category->parent;

        while ($current) {
            $depth++;
            $current = $current->parent;
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
