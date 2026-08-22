<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductPricingService;
use Illuminate\Console\Command;

class ApplyCompetitivePricing extends Command
{
    protected $signature = 'pricing:apply
                            {--sku= : Reprice a single SKU}
                            {--dry-run : Show what would change without writing}';

    protected $description = 'Reprice the catalogue from cost using competitive category and brand markups';

    public function handle(ProductPricingService $pricing): int
    {
        $sku = trim((string) $this->option('sku'));
        $dryRun = (bool) $this->option('dry-run');

        $this->line('Competitive pricing: laptops/Dell-HP-Lenovo 8%, networking/CCTV 12%, fallback '.$pricing->markupPercentFor(100).'%.');
        $this->newLine();

        $query = Product::query()->with(['category.parent'])->orderBy('id');
        if ($sku !== '') {
            $query->where('sku', $sku);
        }

        if ($dryRun) {
            $shown = 0;
            $wouldUpdate = 0;
            $query->chunkById(100, function ($products) use ($pricing, &$shown, &$wouldUpdate) {
                foreach ($products as $product) {
                    $cost = $pricing->resolveCostPrice($product);
                    if ($cost <= 0) {
                        continue;
                    }

                    $would = $pricing->retailPrice($cost, $product);
                    if ($would === (float) $product->price) {
                        continue;
                    }

                    $wouldUpdate++;
                    if ($shown < 20) {
                        $this->line(sprintf(
                            '%s  %s  R%s → R%s  (cost R%s, %s%%)',
                            $product->sku ?: '—',
                            $product->name,
                            number_format((float) $product->price, 2),
                            number_format($would, 2),
                            number_format($cost, 2),
                            number_format($pricing->markupPercentFor($cost, $product), 1)
                        ));
                        $shown++;
                    }
                }
            });

            $this->newLine();
            $this->info("Dry run: {$wouldUpdate} products would change. Re-run without --dry-run to apply.");

            return self::SUCCESS;
        }

        $result = $pricing->applyToAllProducts($sku !== '' ? $sku : null);

        $this->info("Updated: {$result['updated']}");
        $this->line("Prices reduced: {$result['reduced']}");
        $this->line("Unchanged: {$result['unchanged']}");
        $this->line("Skipped (no cost/price): {$result['skipped']}");

        return self::SUCCESS;
    }
}
