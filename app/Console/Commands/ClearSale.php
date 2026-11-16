<?php

namespace App\Console\Commands;

use App\Services\SaleWindowService;
use Illuminate\Console\Command;

class ClearSale extends Command
{
    protected $signature = 'sale:clear
                            {--label= : Only products with this promo label}
                            {--dry-run : Report the rollback without writing}';

    protected $description = 'Remove a sale, or restore the values saved before sale:apply';

    public function handle(SaleWindowService $sales): int
    {
        $label = $this->option('label');
        $result = $sales->clear($label !== null ? (string) $label : null, (bool) $this->option('dry-run'));

        $this->info(($result['dry_run'] ? 'Would restore ' : 'Restored ').$result['restored'].' product(s).');

        return self::SUCCESS;
    }
}
