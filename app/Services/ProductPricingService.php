<?php

namespace App\Services;

use App\Models\Product;

class ProductPricingService
{
    public function vatRate(): float
    {
        return (float) config('app.vat_rate', 15);
    }

    /** Dealer/cost price ex-VAT → VAT-inclusive acquisition cost. */
    public function costWithVat(float $exVatCost): float
    {
        if ($exVatCost <= 0) {
            return 0.0;
        }

        return round($exVatCost * (1 + ($this->vatRate() / 100)), 2);
    }

    /**
     * @param  array{name?: string, brand?: string, category_path?: string}  $context
     */
    public function markupPercentFor(float $costPrice, ?Product $product = null, array $context = []): float
    {
        $name = mb_strtolower(trim((string) ($context['name'] ?? $product?->name ?? '')));
        $brand = mb_strtolower(trim((string) ($context['brand'] ?? $product?->brand ?? '')));
        $path = mb_strtolower(trim((string) ($context['category_path'] ?? $this->categoryPath($product))));

        foreach (config('pricing.name_term_markups', []) as $term => $percent) {
            $term = mb_strtolower(trim((string) $term));
            if ($term !== '' && $name !== '' && str_contains($name, $term)) {
                return (float) $percent;
            }
        }

        $categoryMarkups = config('pricing.category_markups', []);
        uksort($categoryMarkups, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        foreach ($categoryMarkups as $needle => $percent) {
            $needle = mb_strtolower(trim((string) $needle));
            if ($needle !== '' && $path !== '' && (str_starts_with($path, $needle) || str_contains($path, '/'.$needle))) {
                return (float) $percent;
            }
        }

        $minBrandCost = (float) config('pricing.competitive_brand_min_cost', 4000);
        if ($costPrice >= $minBrandCost && $brand !== '') {
            foreach (config('pricing.competitive_brand_markups', []) as $brandNeedle => $percent) {
                $brandNeedle = mb_strtolower(trim((string) $brandNeedle));
                if ($brandNeedle !== '' && str_contains($brand, $brandNeedle)) {
                    return (float) $percent;
                }
            }
        }

        return (float) config('pricing.markup_percent', 15);
    }

    /**
     * @param  array{name?: string, brand?: string, category_path?: string}  $context
     */
    public function retailPrice(float $costPrice, ?Product $product = null, array $context = []): float
    {
        if ($costPrice <= 0) {
            return 0.0;
        }

        $markup = $this->markupPercentFor($costPrice, $product, $context);
        $roundTo = max(1, (int) config('pricing.round_to', 50));
        $threshold = (float) config('pricing.low_cost_threshold', 20);

        $markedUp = $costPrice * (1 + ($markup / 100));
        $mode = config('pricing.round_mode', 'up');

        if ($threshold > 0 && $costPrice < $threshold) {
            return $mode === 'nearest'
                ? round($markedUp, 2)
                : (float) (ceil($markedUp * 100) / 100);
        }

        if ($roundTo <= 1) {
            return round($markedUp, 2);
        }

        $rounded = $mode === 'nearest'
            ? (int) (round($markedUp / $roundTo) * $roundTo)
            : (int) (ceil($markedUp / $roundTo) * $roundTo);

        return (float) max($rounded, $roundTo);
    }

    /** @param 'scoop'|null $importSource */
    public function importCostPrice(float $supplierCost, ?string $importSource = null): float
    {
        if ($importSource === 'scoop' && config('pricing.scoop_prices_ex_vat', true)) {
            return $this->costWithVat($supplierCost);
        }

        return round($supplierCost, 2);
    }

    /**
     * @param  'scoop'|null  $importSource
     * @param  array{name?: string, brand?: string, category_path?: string}  $context
     */
    public function retailPriceForImport(float $supplierCost, ?string $importSource = null, array $context = []): float
    {
        $cost = $this->importCostPrice($supplierCost, $importSource);

        return $this->retailPrice($cost, null, $context);
    }

    public function resolveCostPrice(Product $product): float
    {
        $cost = (float) ($product->cost_price ?? 0);
        $price = (float) ($product->price ?? 0);

        if ($cost > 0 && ($price <= 0 || $cost < $price)) {
            return round($cost, 2);
        }

        if ($price <= 0) {
            return 0.0;
        }

        $legacy = (float) config('pricing.legacy_markup_percent', 40);
        if ($legacy <= 0) {
            return round($price, 2);
        }

        return round($price / (1 + ($legacy / 100)), 2);
    }

    public function applyToProduct(Product $product): bool
    {
        $cost = $this->resolveCostPrice($product);
        if ($cost <= 0) {
            return false;
        }

        $product->loadMissing('category.parent');
        $newPrice = $this->retailPrice($cost, $product);
        if ($newPrice <= 0) {
            return false;
        }

        $updates = [
            'cost_price' => $cost,
            'price' => $newPrice,
        ];

        if ($product->sale_price && (float) $product->sale_price > 0 && (float) $product->sale_price <= $cost) {
            $newSale = $this->retailPrice((float) $product->sale_price, $product);
            $updates['sale_price'] = $newSale < $newPrice ? $newSale : null;
        }

        $product->update($updates);

        return true;
    }

    /**
     * @return array{updated: int, skipped: int, reduced: int, unchanged: int}
     */
    public function applyToAllProducts(?string $sku = null): array
    {
        $updated = 0;
        $skipped = 0;
        $reduced = 0;
        $unchanged = 0;

        $query = Product::query()->with(['category.parent'])->orderBy('id');
        if ($sku) {
            $query->where('sku', $sku);
        }

        $query->chunkById(100, function ($products) use (&$updated, &$skipped, &$reduced, &$unchanged) {
            foreach ($products as $product) {
                $before = (float) $product->price;
                if ($this->applyToProduct($product)) {
                    $updated++;
                    $after = (float) $product->fresh()->price;
                    if ($after < $before) {
                        $reduced++;
                    } elseif ($after === $before) {
                        $unchanged++;
                    }
                } else {
                    $skipped++;
                }
            }
        });

        return compact('updated', 'skipped', 'reduced', 'unchanged');
    }

    protected function categoryPath(?Product $product): string
    {
        if (! $product?->category_id) {
            return '';
        }

        $category = $product->relationLoaded('category')
            ? $product->category
            : $product->category()->with('parent')->first();

        if (! $category) {
            return '';
        }

        $category->loadMissing('parent');

        return mb_strtolower($category->urlPath());
    }
}
