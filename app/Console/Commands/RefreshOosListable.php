<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\CatalogOosPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Recompute is_oos_listable for every product. Run once after deploying the
 * migration, and any time descriptions or images are repaired in bulk.
 *
 *   php artisan catalog:refresh-oos-listable
 *   php artisan catalog:refresh-oos-listable --live
 */
class RefreshOosListable extends Command
{
    protected $signature = 'catalog:refresh-oos-listable {--live : Persist changes (default is dry-run)}';

    protected $description = 'Recompute which out-of-stock products may appear in the shop (all suppliers)';

    public function handle(): int
    {
        if (! Schema::hasColumn('products', 'is_oos_listable')) {
            $this->error('Run the oos listing migration first.');

            return self::FAILURE;
        }

        $live = (bool) $this->option('live');
        $yes = 0;
        $no = 0;
        $changed = 0;
        $backfilledStockAt = 0;

        Product::query()->with('images')->orderBy('id')->chunkById(200, function ($products) use ($live, &$yes, &$no, &$changed, &$backfilledStockAt) {
            foreach ($products as $product) {
                $eligible = CatalogOosPolicy::isEligible($product);
                if ($eligible) {
                    $yes++;
                } else {
                    $no++;
                }
                $nowInStock = CatalogOosPolicy::isOutOfStock($product) === false;
                $needsFlag = (bool) $product->is_oos_listable !== $eligible;
                $needsStockAt = $nowInStock && $product->last_in_stock_at === null;
                if ($needsStockAt) {
                    $backfilledStockAt++;
                }
                if (! $needsFlag && ! $needsStockAt) {
                    continue;
                }
                if ($needsFlag) {
                    $changed++;
                }
                if ($live) {
                    $product->is_oos_listable = $eligible;
                    if ($needsStockAt) {
                        $product->last_in_stock_at = now();
                    }
                    $product->save();
                }
            }
        });

        $this->table(['metric', 'count'], [
            ['eligible (name, 200+ desc, picture, cost)', $yes],
            ['not eligible', $no],
            [$live ? 'is_oos_listable flags updated' : 'is_oos_listable flags that would change', $changed],
            [$live ? 'last_in_stock_at backfilled (currently in stock)' : 'last_in_stock_at that would be backfilled (in stock)', $backfilledStockAt],
            ['mode', $live ? 'LIVE' : 'DRY-RUN'],
        ]);

        return self::SUCCESS;
    }
}
