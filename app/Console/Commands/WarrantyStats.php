<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only counts of warranty_months on the catalogue. Never writes.
 *
 *   php artisan products:warranty-stats
 */
class WarrantyStats extends Command
{
    protected $signature = 'products:warranty-stats';

    protected $description = 'Count published/draft products with null or unknown warranty_months (read-only)';

    public function handle(): int
    {
        if (! Schema::hasColumn('products', 'warranty_months')) {
            $this->error('products.warranty_months column is missing');

            return self::FAILURE;
        }

        $base = Product::query();
        $published = (clone $base)->where('is_active', 1);
        $drafts = (clone $base)->where(function ($q) {
            $q->where('is_active', 0)->orWhereNull('is_active');
        });

        $report = [
            'published_total' => (clone $published)->count(),
            'published_unknown' => $this->unknownCount(clone $published),
            'published_explicit' => $this->explicitCount(clone $published),
            'draft_total' => (clone $drafts)->count(),
            'draft_unknown' => $this->unknownCount(clone $drafts),
            'draft_explicit' => $this->explicitCount(clone $drafts),
        ];
        $this->info('['.now()->toDateTimeString().'] '.json_encode($report));
        $this->line('Published with a null/empty/0 warranty_months: '.$report['published_unknown'].' of '.$report['published_total']);

        $breakdown = (clone $published)
            ->whereNotNull('warranty_months')
            ->where('warranty_months', '>', 0)
            ->selectRaw('warranty_months, count(*) as n')
            ->groupBy('warranty_months')
            ->orderBy('warranty_months')
            ->pluck('n', 'warranty_months')
            ->all();
        if ($breakdown) {
            $this->line('Published explicit warranty_months: '.json_encode($breakdown, JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }

    private function unknownCount($query): int
    {
        return $query->where(function ($q) {
            $q->whereNull('warranty_months')->orWhere('warranty_months', 0);
        })->count();
    }

    private function explicitCount($query): int
    {
        return $query->whereNotNull('warranty_months')->where('warranty_months', '>', 0)->count();
    }
}
