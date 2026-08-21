<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CatalogDeduper
{
    public const CACHE_KEY = 'catalog.duplicate_listings_v2';

    /**
     * @return list<int>
     */
    public function idsToHide(): array
    {
        return $this->snapshot()['hide'];
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Keep one product per listing key. Prefer SKU matches, then newest id.
     *
     * @return list<int>
     */
    public function scanIdsToHide(): array
    {
        return $this->scanSnapshot()['hide'];
    }

    public function canonicalProduct(Product $product): Product
    {
        $keepId = $this->snapshot()['keep'][$product->id] ?? null;

        if ($keepId === null || $keepId === $product->id) {
            return $product;
        }

        return Product::query()->whereKey($keepId)->first() ?? $product;
    }

    /**
     * @return array{hidden: int, samples: list<string>}
     */
    public function deactivateDuplicates(): array
    {
        $ids = $this->scanIdsToHide();
        $samples = [];

        if ($ids !== []) {
            $samples = Product::whereIn('id', $ids)
                ->orderBy('name')
                ->limit(20)
                ->get()
                ->map(fn (Product $product) => trim($product->brand.' '.$product->name).' #'.$product->id)
                ->all();

            Product::whereIn('id', $ids)->update(['is_active' => false]);
        }

        $this->clearCache();

        return [
            'hidden' => count($ids),
            'samples' => $samples,
        ];
    }

    public function listingKey(Product $product): string
    {
        $sku = mb_strtolower(trim((string) $product->sku));

        if ($sku !== '') {
            return 'sku:'.$sku;
        }

        $name = mb_strtolower(trim((string) $product->name));
        $brand = mb_strtolower(trim((string) $product->brand));

        return 'title:'.$name.'|'.$brand;
    }

    public function uniqueCollection(Collection $products): Collection
    {
        return $products->unique(fn (Product $product) => $this->listingKey($product))->values();
    }

    /**
     * @return array{hide: list<int>, keep: array<int, int>}
     */
    protected function snapshot(): array
    {
        return Cache::remember(self::CACHE_KEY, 600, fn () => $this->scanSnapshot());
    }

    /**
     * @return array{hide: list<int>, keep: array<int, int>}
     */
    protected function scanSnapshot(): array
    {
        $seen = [];
        $hide = [];
        $keep = [];

        Product::query()
            ->select(['id', 'name', 'brand', 'sku'])
            ->where('is_active', true)
            ->orderByDesc('id')
            ->get()
            ->each(function (Product $product) use (&$seen, &$hide, &$keep) {
                $key = $this->listingKey($product);

                if (isset($seen[$key])) {
                    $hide[] = $product->id;
                    $keep[$product->id] = $seen[$key];

                    return;
                }

                $seen[$key] = $product->id;
            });

        return [
            'hide' => array_values(array_unique($hide)),
            'keep' => $keep,
        ];
    }
}
