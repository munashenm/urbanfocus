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
                            {--limit= : Maximum number of products to scan}';

    protected $description = 'Remove internal pricing, markup, margin and fee language from customer-facing product copy';

    public function handle(InternalPricingCopySanitizer $sanitizer, SeoService $seo): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        if ($dryRun) {
            $this->warn('Dry run — no changes will be saved.');
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

        return self::SUCCESS;
    }
}
