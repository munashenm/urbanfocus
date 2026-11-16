<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\CatalogOosPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Publish hidden/unpublished out-of-stock products that already meet the
 * quality bar, for every supplier. Default is dry-run.
 *
 *   php artisan catalog:publish-eligible-oos
 *   php artisan catalog:publish-eligible-oos --live
 */
class PublishEligibleOos extends Command
{
    protected $signature = 'catalog:publish-eligible-oos
                            {--live : Persist changes (default is dry-run)}
                            {--limit=0 : Max products to publish}';

    protected $description = 'Publish eligible hidden out-of-stock products as visible OOS (all suppliers)';

    public function handle(): int
    {
        $live = (bool) $this->option('live');
        $limit = (int) $this->option('limit');
        $query = Product::query()
            ->with('images')
            ->where('is_active', false)
            ->whereNull('deleted_at');

        $candidates = 0;
        $eligible = 0;
        $published = 0;
        $skipped = 0;
        $rows = [];

        $query->orderBy('id')->chunkById(200, function ($products) use ($live, $limit, &$candidates, &$eligible, &$published, &$skipped, &$rows) {
            foreach ($products as $product) {
                if ($limit > 0 && $eligible >= $limit) {
                    return false;
                }
                if (! CatalogOosPolicy::isOutOfStock($product)) {
                    continue;
                }
                $candidates++;
                if (! CatalogOosPolicy::isEligible($product)) {
                    $skipped++;
                    continue;
                }
                $eligible++;
                $rows[] = [$product->sku, $product->name, $product->import_source ?: $product->supplier_name ?: ''];
                if (! $live) {
                    continue;
                }
                $product->is_active = true;
                if ($product->manage_stock) {
                    $product->stock_quantity = max(0, (int) $product->stock_quantity);
                } else {
                    $product->in_stock = false;
                }
                if (Schema::hasColumn('products', 'is_oos_listable')) {
                    $product->is_oos_listable = true;
                }
                $product->save();
                $published++;
            }

            return true;
        });

        $this->table(['metric', 'count'], [
            ['hidden out-of-stock products scanned', $candidates],
            ['eligible (name, 200+ desc, picture, cost)', $eligible],
            ['skipped (quality bar not met)', $skipped],
            ['published this run', $live ? $published : 0],
            ['mode', $live ? 'LIVE' : 'DRY-RUN'],
        ]);

        if ($rows !== []) {
            $this->info('Eligible SKUs (first 40):');
            foreach (array_slice($rows, 0, 40) as $row) {
                $this->line('  '.implode(' — ', $row));
            }
            if (count($rows) > 40) {
                $this->line('  … '.(count($rows) - 40).' more');
            }
        }

        if (! $live) {
            $this->comment('Dry run only. Re-run with --live to publish. Then rebuild the merchant feed.');
        }

        return self::SUCCESS;
    }
}
