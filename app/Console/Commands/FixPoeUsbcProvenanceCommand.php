<?php

namespace App\Console\Commands;

use App\Services\FixPoeUsbcProvenanceService;
use Illuminate\Console\Command;

class FixPoeUsbcProvenanceCommand extends Command
{
    protected $signature = 'products:fix-poe-usbc-provenance
                            {--dry-run : Show the planned update without writing}';

    protected $description = 'Fix product 10873 / POE-USBC brand, model, category, description and Scoop provenance only';

    public function handle(FixPoeUsbcProvenanceService $fixer): int
    {
        $result = $fixer->run((bool) $this->option('dry-run'));

        $this->table(
            ['Field', 'Value'],
            [
                ['Updated', $result['updated'] ? 'yes' : 'no'],
                ['Product ID', $result['product_id'] ?? '—'],
                ['SKU', $result['sku'] ?? '—'],
                ['Brand', $result['brand'] ?? '—'],
                ['Category', $result['category'] ?? '—'],
                ['Images before', (string) $result['images_before']],
                ['Images after', (string) $result['images_after']],
                ['Price', $result['price'] ?? '—'],
                ['Message', $result['message']],
            ]
        );

        return str_contains(strtolower($result['message']), 'not found') || str_contains(strtolower($result['message']), 'refusing')
            ? self::FAILURE
            : self::SUCCESS;
    }
}
