<?php

namespace App\Console\Commands;

use App\Services\CategoryReorganizationService;
use Illuminate\Console\Command;

class ReorganizeCategories extends Command
{
    protected $signature = 'categories:reorganize
                            {--dry-run : Preview changes without writing}
                            {--run : Apply the full migration}
                            {--limit= : Limit product remapping to N products for testing}';

    protected $description = 'Reorganize categories into the new main/subcategory tree and remap products safely';

    public function handle(CategoryReorganizationService $service): int
    {
        if (! $this->option('dry-run') && ! $this->option('run')) {
            $this->error('Specify --dry-run to preview or --run to apply changes.');

            return self::FAILURE;
        }

        $preview = $service->preview();

        $this->info('Category reorganization preview');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Main categories in new tree', $preview['main_categories']],
                ['Products to remap', $preview['products_to_move']],
                ['Products unchanged', $preview['products_unchanged']],
                ['Redirects to create', $preview['redirects_to_create']],
            ]
        );

        if ($preview['sample_moves'] !== []) {
            $this->newLine();
            $this->info('Sample product moves:');
            $this->table(['From', 'To', 'Count'], array_map(
                fn (array $row) => [$row['from'], $row['to'], $row['count']],
                $preview['sample_moves']
            ));
        }

        if ($this->option('dry-run')) {
            $this->comment('Dry run complete. No changes were made. Use --run to apply.');

            return self::SUCCESS;
        }

        if (! $this->confirm('A product category backup will be created. Continue with full migration?')) {
            return self::SUCCESS;
        }

        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $result = $service->reorganize(backup: true, limit: $limit);

        $this->info("Migration complete: {$result['moved']} products remapped, {$result['redirects']} redirects created, {$result['deactivated']} orphan categories deactivated.");

        return self::SUCCESS;
    }
}
