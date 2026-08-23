<?php

namespace App\Console\Commands;

use App\Services\SpecialistCatalogService;
use Illuminate\Console\Command;

class SyncSpecialistCatalog extends Command
{
    protected $signature = 'catalog:sync-specialist
                            {--dry-run : Show what would be created without writing}
                            {--file= : Override the catalog path}
                            {--sku= : Sync a single catalog SKU}';

    protected $description = 'Add Urban Focus specialist technology products (Nitrokey, PiKVM, Hailo, Proxmox, …) without duplicating SKUs';

    public function handle(SpecialistCatalogService $catalog): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $file = trim((string) $this->option('file')) ?: null;
        $sku = trim((string) $this->option('sku')) ?: null;

        $topup = rtrim(rtrim(number_format($catalog->topupPercent(), 1), '0'), '.');
        $this->line("Specialist sync: skip existing SKUs/names, create the rest at street price + {$topup}% top-up. Re-run refreshes SEO copy on products we added.");
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
