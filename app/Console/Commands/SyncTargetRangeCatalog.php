<?php

namespace App\Console\Commands;

use App\Services\TargetRangeCatalogService;
use Illuminate\Console\Command;

class SyncTargetRangeCatalog extends Command
{
    protected $signature = 'catalog:sync-target-range
                            {--dry-run : Show what would be created without writing}
                            {--file= : Override the JSON catalog path}
                            {--sku= : Sync a single catalog SKU}';

    protected $description = 'Add curated Urban Focus target-range products without duplicating SKUs already on the store';

    public function handle(TargetRangeCatalogService $catalog): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $file = trim((string) $this->option('file')) ?: null;
        $sku = trim((string) $this->option('sku')) ?: null;

        $this->line('Target-range sync: skip existing SKUs/names, create the rest at competitive street prices (Paystack/bank fee included).');
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

        $this->info($dryRun
            ? "Dry run: {$result['created']} would be created, {$result['skipped']} already on the store, {$result['imaged']} would get photos, {$result['errors']} errors."
            : "Created: {$result['created']}. Skipped (already on store): {$result['skipped']}. Photos attached: {$result['imaged']}. Errors: {$result['errors']}."
        );

        if (! $dryRun && $result['created'] > 0) {
            $this->line('New listings are available to order (not fake warehouse stock). Re-run with --dry-run anytime to audit.');
        }

        return $result['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
