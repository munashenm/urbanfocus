<?php

namespace App\Console\Commands;

use App\Services\InternalPricingCopySanitizer;
use App\Services\SeoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ScrubInternalPricingCopy extends Command
{
    protected $signature = 'catalog:scrub-internal-copy
                            {--dry-run : Preview matches without saving}
                            {--no-backup : Skip JSON backup of affected rows}
                            {--limit= : Maximum number of products to scan}';

    protected $description = 'Remove internal pricing language and SEO-implementation commentary from customer-facing product copy';

    public function handle(InternalPricingCopySanitizer $sanitizer, SeoService $seo): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        if ($dryRun) {
            $this->warn('Dry run — no changes will be saved.');
        } elseif (! $this->option('no-backup')) {
            $backup = $sanitizer->backupAffectedCopy($limit);
            $this->info('Backup written: '.$backup);
        }

        $stats = $sanitizer->scrubCatalog($dryRun, $limit);

        if ($stats['samples'] !== []) {
            $this->newLine();
            $this->line('Sample listings cleaned:');
            foreach ($stats['samples'] as $sample) {
                $this->line('  '.$sample);
            }
            $this->newLine();
        }

        $this->info("Scanned {$stats['processed']} products.");
        $this->line($dryRun
            ? "Would update {$stats['updated']} listings."
            : "Updated {$stats['updated']} listings."
        );

        if (! $dryRun && $stats['updated'] > 0) {
            $seo->clearCache();
            Cache::forget('home.product_rows_v1');
            Cache::forget('home.product_rows_v2');
            $this->line('Storefront and feed caches cleared.');
        }

        if (! $dryRun) {
            $remaining = $sanitizer->auditCatalog($limit);
            if ($remaining !== []) {
                $this->error(count($remaining).' leak(s) remain after scrub. Review catalog:audit-internal-copy.');

                return self::FAILURE;
            }
            $this->info('Post-scrub scan: 0 remaining internal-pricing phrases.');
        }

        return self::SUCCESS;
    }
}
