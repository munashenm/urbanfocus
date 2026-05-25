<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
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
                foreach ($product->images as $image) {
                    $this->images->delete($image->path);
                    $imagesRemoved++;
                }
                $product->forceDelete();
                $deleted++;
            }
        });

        $this->removeOrphanProductStorage();

        return ['deleted' => $deleted, 'images_removed' => $imagesRemoved];
    }

    /** @return array{products_deleted: int, categories_deleted: int, images_removed: int} */
    public function removeNonItProducts(): array
    {
        $productsDeleted = 0;
        $imagesRemoved = 0;
        $categoriesDeleted = 0;

        $excludedCategoryIds = Category::with('parent')
            ->get()
            ->filter(fn (Category $category) => $this->catalogFilter->isCategoryExcluded($category))
            ->pluck('id')
            ->all();

        if ($excludedCategoryIds !== []) {
            Product::with('images')
                ->whereIn('category_id', $excludedCategoryIds)
                ->orderBy('id')
                ->chunkById(50, function ($products) use (&$productsDeleted, &$imagesRemoved) {
                    foreach ($products as $product) {
                        foreach ($product->images as $image) {
                            $this->images->delete($image->path);
                            $imagesRemoved++;
                        }
                        $product->forceDelete();
                        $productsDeleted++;
                    }
                });
        }

        $remainingCategories = Category::with('parent')->get();

        $sorted = $remainingCategories->sortByDesc(function (Category $category) {
            $depth = 0;
            $current = $category->parent;
            while ($current) {
                $depth++;
                $current = $current->parent;
            }

            return $depth;
        });

        foreach ($sorted as $category) {
            if (! $this->catalogFilter->isCategoryExcluded($category)) {
                continue;
            }

            if ($category->products()->exists()) {
                continue;
            }

            if ($category->children()->exists()) {
                continue;
            }

            $category->delete();
            $categoriesDeleted++;
        }

        // Second pass for parent categories emptied after child removal.
        foreach ($sorted as $category) {
            if (! Category::whereKey($category->id)->exists()) {
                continue;
            }

            if (! $this->catalogFilter->isCategoryExcluded($category)) {
                continue;
            }

            if ($category->products()->exists() || $category->children()->exists()) {
                continue;
            }

            $category->delete();
            $categoriesDeleted++;
        }

        Cache::forget('sitemap.xml');

        return compact('productsDeleted', 'categoriesDeleted', 'imagesRemoved');
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
