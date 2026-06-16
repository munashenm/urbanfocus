<?php

namespace App\Console\Commands;

use App\Services\CategoryMergeService;
use Illuminate\Console\Command;

class MergeCategories extends Command
{
    protected $signature = 'categories:merge
                            {--dry-run : Preview changes without writing}
                            {--run : Remap products and deactivate empty legacy categories}
                            {--limit= : Limit product remapping to N products for testing}';

    protected $description = 'Merge all products into canonical categories and deactivate empty legacy categories';

    public function handle(CategoryMergeService $merge): int
    {
        if (! $this->option('dry-run') && ! $this->option('run')) {
            $this->error('Specify --dry-run to preview or --run to apply changes.');

            return self::FAILURE;
        }

        $preview = $merge->preview();
        $reorg = $preview['reorganization'];

        $this->info('Category merge preview');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Products on legacy categories now', $preview['legacy_products']],
                ['Products to remap (reorg pass)', $reorg['products_to_move']],
                ['Products unchanged (reorg pass)', $reorg['products_unchanged']],
                ['Redirects to create', $reorg['redirects_to_create']],
            ]
        );

        if ($this->option('dry-run')) {
            $this->comment('Dry run complete. Use --run to merge all products into canonical categories.');

            return self::SUCCESS;
        }

        if (! $this->confirm('A product category backup will be created. Continue with full merge?')) {
            return self::SUCCESS;
        }

        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $result = $merge->merge(backup: true, limit: $limit);

        $this->info(sprintf(
            'Merge complete: %d products remapped (reorg), %d reassigned (heuristics), %d redirects, %d legacy categories deactivated, %d products still on legacy categories.',
            $result['reorganize']['moved'],
            $result['assign']['categorized'],
            $result['reorganize']['redirects'],
            $result['reorganize']['deactivated'],
            $result['legacy_products_remaining'],
        ));

        return self::SUCCESS;
    }
}
