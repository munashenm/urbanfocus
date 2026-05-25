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
