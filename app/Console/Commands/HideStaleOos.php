<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\CatalogOosPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Hide products that have been out of stock across the shop (i.e. after the
 * multi-supplier sync guards have already cleared site stock) for 90+ days.
 * When stock later returns, ProductObserver re-publishes rows hidden by this
 * command (oos_hidden_at is set).
 *
 *   php artisan catalog:hide-stale-oos
 *   php artisan catalog:hide-stale-oos --live
 */
class HideStaleOos extends Command
{
    protected $signature = 'catalog:hide-stale-oos
                            {--live : Persist changes (default is dry-run)}
                            {--days= : Days out of stock before hiding (default: config catalog.stale_oos_days)}';

    protected $description = 'Unpublish products that have been out of stock for 90+ days (all suppliers)';

    public function handle(): int
    {
        if (! Schema::hasColumn('products', 'oos_hidden_at')) {
            $this->error('Run the oos listing migration first (is_oos_listable / last_in_stock_at / oos_hidden_at).');

            return self::FAILURE;
        }

        $live = (bool) $this->option('live');
        $days = (int) ($this->option('days') ?: config('catalog.stale_oos_days', 90));
        $cutoff = Carbon::now()->subDays($days);
        $hidden = 0;
        $scanned = 0;
        $skippedNoTimestamp = 0;

        Product::query()
            ->where('is_active', true)
            ->whereNull('oos_hidden_at')
            ->orderBy('id')
            ->chunkById(200, function ($products) use ($live, $cutoff, &$hidden, &$scanned, &$skippedNoTimestamp) {
                foreach ($products as $product) {
                    if (! CatalogOosPolicy::isOutOfStock($product)) {
                        continue;
                    }
                    $scanned++;
                    // Do not fall back to created_at: a published product with a
                    // null last_in_stock_at may have been in stock recently, before
                    // this column existed. Those rows are skipped until a sync or
                    // refresh records a real last-in-stock time.
                    if (! $product->last_in_stock_at) {
                        $skippedNoTimestamp++;
                        continue;
                    }
                    if ($product->last_in_stock_at->gt($cutoff)) {
                        continue;
                    }
                    if (! $live) {
                        $hidden++;
                        continue;
                    }
                    $product->is_active = false;
                    $product->oos_hidden_at = now();
                    $product->save();
                    $hidden++;
                }
            });

        $this->table(['metric', 'count'], [
            ['published out-of-stock products scanned', $scanned],
            ['skipped (no last_in_stock_at yet)', $skippedNoTimestamp],
            [$live ? 'hidden this run' : 'would hide', $hidden],
            ['days', $days],
            ['cutoff', $cutoff->toDateTimeString()],
            ['mode', $live ? 'LIVE' : 'DRY-RUN'],
        ]);

        if (! $live) {
            $this->comment('Dry run only. Re-run with --live to unpublish.');
        }

        return self::SUCCESS;
    }
}
