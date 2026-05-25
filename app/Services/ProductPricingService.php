<?php

namespace App\Services;

use App\Models\Product;

class ProductPricingService
{
    public function retailPrice(float $costPrice): float
    {
        if ($costPrice <= 0) {
            return 0.0;
        }

        $markup = config('pricing.markup_percent', 40);
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

    public function applyToProduct(Product $product): bool
    {
        $cost = (float) ($product->cost_price ?: $product->price);
        if ($cost <= 0) {
            return false;
        }

        $newPrice = $this->retailPrice($cost);
        $updates = [
            'cost_price' => $cost,
            'price' => $newPrice,
        ];

        if ($product->sale_price && (float) $product->sale_price > 0 && (float) $product->sale_price <= $cost) {
            $newSale = $this->retailPrice((float) $product->sale_price);
            $updates['sale_price'] = $newSale < $newPrice ? $newSale : null;
        }

        $product->update($updates);

        return true;
    }

    /** @return array{updated: int, skipped: int} */
    public function applyToAllProducts(): array
    {
        $updated = 0;
        $skipped = 0;

        Product::query()->orderBy('id')->chunkById(100, function ($products) use (&$updated, &$skipped) {
            foreach ($products as $product) {
                if ($this->applyToProduct($product)) {
                    $updated++;
                } else {
                    $skipped++;
                }
            }
        });

        return compact('updated', 'skipped');
    }
}
