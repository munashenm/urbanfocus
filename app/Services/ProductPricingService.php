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

    /** @param 'scoop'|null $importSource */
    public function importCostPrice(float $supplierCost, ?string $importSource = null): float
    {
        if ($importSource === 'scoop' && config('pricing.scoop_prices_ex_vat', true)) {
            return $this->costWithVat($supplierCost);
        }

        return round($supplierCost, 2);
    }

    /** @param 'scoop'|null $importSource */
    public function retailPriceForImport(float $supplierCost, ?string $importSource = null): float
    {
        return $this->retailPrice($this->importCostPrice($supplierCost, $importSource));
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
