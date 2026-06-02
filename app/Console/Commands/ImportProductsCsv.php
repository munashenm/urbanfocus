<?php

namespace App\Console\Commands;

use App\Services\ProductImportService;
use Illuminate\Console\Command;

class ImportProductsCsv extends Command
{
    protected $signature = 'products:import
                            {path? : CSV file path (default: storage/imports/products.csv)}
                            {--preview : Scan only — no database changes}';

    protected $description = 'Import IT products from Esquire, Pinnacle, or WooCommerce CSV feeds';

    public function handle(ProductImportService $import): int
    {
        $path = $this->argument('path') ?: storage_path('imports/products.csv');

        if (! is_readable($path)) {
            $this->error("CSV not found or not readable: {$path}");

            return self::FAILURE;
        }

        $pricing = $import->pricingPolicy();
        $this->line('File: '.$path.' ('.number_format(filesize($path)).' bytes)');
        $this->line("Pricing: {$pricing['markup_percent']}% markup, round {$pricing['round_mode']} to R{$pricing['round_to']}");
        $this->newLine();

        if ($this->option('preview')) {
            $result = $import->previewFromPath($path);

            $this->info('Preview (no changes made)');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Rows scanned', $result['total_rows']],
                    ['Would create', $result['would_create']],
                    ['Would update', $result['would_update']],
                    ['Skip empty', $result['skipped']],
                    ['Skip non-IT', $result['skippedNonIt']],
                    ['Skip no image', $result['skippedNoImage']],
                    ['Skip no price', $result['skippedNoPrice']],
                ]
            );

            if (! empty($result['samples']['import'])) {
                $this->newLine();
                $this->line('Sample imports (cost → retail):');
                foreach ($result['samples']['import'] as $sample) {
                    $this->line(sprintf(
                        '- %s — R%s → R%s',
                        $sample['name'],
                        number_format($sample['cost'], 2),
                        number_format($sample['retail'], 2)
                    ));
                }
            }

            return self::SUCCESS;
        }

        if (! $this->confirm('Run import now?', true)) {
            return self::SUCCESS;
        }

        $this->line('Importing (this may take several minutes for large feeds)...');

        $result = $import->importFromPath($path, function ($imported, $updated, $skippedNoImage, $row) {
            if ($row % 100 === 0) {
                $this->output->write("\r  Row {$row}: ".($imported + $updated).' saved, '.$skippedNoImage.' skipped (no image)');
            }
        });

        $this->newLine();

        $this->info("Imported: {$result['imported']}, Updated: {$result['updated']}");
        $this->line("Skipped empty: {$result['skipped']}");
        $this->line("Skipped non-IT: {$result['skippedNonIt']}");
        $this->line("Skipped no image: {$result['skippedNoImage']}");
        $this->line("Skipped no price: {$result['skippedNoPrice']}");
        $this->line("Skipped image failed: {$result['skippedImageFailed']}");

        if (! empty($result['errors'])) {
            $this->newLine();
            $this->warn('Errors (first 10):');
            foreach (array_slice($result['errors'], 0, 10) as $error) {
                $this->line('- '.$error);
            }
        }

        return self::SUCCESS;
    }
}
