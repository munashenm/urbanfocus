<?php

namespace App\Console\Commands;

use App\Services\ProductSeoService;
use Illuminate\Console\Command;

class OptimizeCatalogSeo extends Command
{
    protected $signature = 'catalog:optimize-seo
                            {--dry-run : Preview changes without saving}
                            {--limit= : Maximum number of products to process}';

    protected $description = 'Assign categories and SEO metadata to uncategorized or weak product listings';

    public function handle(ProductSeoService $seo): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        if ($dryRun) {
            $this->warn('Dry run — no changes will be saved.');
        }

        $stats = $seo->optimizeCatalog($dryRun, $limit);

        $this->info("Processed {$stats['processed']} products.");
        $this->line("Categories assigned: {$stats['categorized']}");
        $this->line("Meta updated: {$stats['meta_updated']}");
        $this->line("Image alt tags updated: {$stats['images_updated']}");

        return self::SUCCESS;
    }
}
