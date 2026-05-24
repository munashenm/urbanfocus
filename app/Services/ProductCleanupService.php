<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;

class ProductCleanupService
{
    public function __construct(protected ImageService $images) {}

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
