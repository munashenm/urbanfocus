<?php

namespace App\Console\Commands;

use App\Services\HighMarginCatalogService;
use Illuminate\Console\Command;

class SyncHighMarginCatalog extends Command
{
    protected $signature = 'catalog:sync-high-margin
                            {--dry-run : Show what would be created without writing}
                            {--file= : Override the catalog path}
                            {--sku= : Sync a single catalog SKU}';

    protected $description = 'Add Urban Focus high-margin technology products at exact VAT-inclusive prices without duplicating SKUs';

    public function handle(HighMarginCatalogService $catalog): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $file = trim((string) $this->option('file')) ?: null;
        $sku = trim((string) $this->option('sku')) ?: null;

        $this->line('High-margin sync: exact VAT-inclusive prices (no specialist top-up). Available on order. Re-run refreshes copy, prices and photos.');
        $this->newLine();

        $result = $catalog->sync($dryRun, $file, $sku);

        if ($result['samples'] !== []) {
            foreach ($result['samples'] as $sample) {
                $this->line(sprintf(
                    '%s  %s  %s%s',
                    str_pad(strtoupper((string) $sample['action']), 12),
                    $sample['sku'] ?? '—',
                    $sample['name'] ?? '',
                    isset($sample['reason']) ? '  — '.$sample['reason'] : (isset($sample['price']) ? '  R'.number_format((float) $sample['price'], 2) : '')
                ));
            }
            $this->newLine();
        }

        $updated = $result['updated'] ?? 0;
        $this->info($dryRun
            ? "Dry run: {$result['created']} would be created, {$updated} would be updated, {$result['skipped']} already on the store, {$result['imaged']} would get photos, {$result['errors']} errors."
            : "Created: {$result['created']}. Updated: {$updated}. Skipped: {$result['skipped']}. Photos: {$result['imaged']}. Errors: {$result['errors']}."
        );

        return $result['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
