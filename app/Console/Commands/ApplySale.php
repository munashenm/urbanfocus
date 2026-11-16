<?php

namespace App\Console\Commands;

use App\Services\SaleWindowService;
use Illuminate\Console\Command;

class ApplySale extends Command
{
    protected $signature = 'sale:apply
                            {csv : CSV with SKU,sale_price,sale_starts_at,sale_ends_at,promo_label}
                            {--dry-run : Report changes without writing}';

    protected $description = 'Apply a timed sale price to products matched by SKU';

    public function handle(SaleWindowService $sales): int
    {
        try {
            $result = $sales->applyCsv((string) $this->argument('csv'), (bool) $this->option('dry-run'));
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Metric', 'Count'], [
            ['found', $result['found']],
            ['not_found', $result['not_found']],
            ['changed', $result['changed']],
            ['invalid', $result['invalid']],
        ]);

        if ($result['dry_run']) {
            $this->info('Dry run: no products were changed.');
        } elseif ($result['backup']) {
            $this->info('Backup written to storage/app/'.$result['backup']);
        }

        return self::SUCCESS;
    }
}
