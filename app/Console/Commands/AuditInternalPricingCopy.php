<?php

namespace App\Console\Commands;

use App\Services\InternalPricingCopySanitizer;
use Illuminate\Console\Command;

class AuditInternalPricingCopy extends Command
{
    protected $signature = 'catalog:audit-internal-copy
                            {--limit= : Maximum products to scan}
                            {--csv= : Write findings to a CSV path}';

    protected $description = 'Report customer-facing product copy that looks like internal pricing or staff notes (does not change the database)';

    public function handle(InternalPricingCopySanitizer $sanitizer): int
    {
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $findings = $sanitizer->auditCatalog($limit);

        if ($findings === []) {
            $this->info('No suspicious internal-pricing phrases found in scanned listings.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'SKU', 'Title', 'Phrase'],
            array_map(fn (array $row) => [
                $row['id'],
                $row['sku'] ?: '—',
                \Illuminate\Support\Str::limit($row['title'], 50, ''),
                $row['phrase'],
            ], array_slice($findings, 0, 80))
        );

        $this->warn(count($findings).' suspicious excerpt(s) across '.count(array_unique(array_column($findings, 'id'))).' products.');
        $this->line('This command does not alter the database. Run catalog:scrub-internal-copy after reviewing.');

        $csv = trim((string) $this->option('csv'));
        if ($csv !== '') {
            $handle = fopen($csv, 'w');
            if ($handle === false) {
                $this->error('Could not write CSV: '.$csv);

                return self::FAILURE;
            }

            fputcsv($handle, ['id', 'title', 'sku', 'slug', 'url', 'phrase', 'excerpt']);
            foreach ($findings as $row) {
                fputcsv($handle, [
                    $row['id'],
                    $row['title'],
                    $row['sku'],
                    $row['slug'],
                    $row['url'],
                    $row['phrase'],
                    $row['excerpt'],
                ]);
            }
            fclose($handle);
            $this->info('Wrote '.$csv);
        }

        return self::SUCCESS;
    }
}
