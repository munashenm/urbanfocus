<?php

namespace App\Console\Commands;

use App\Services\InternalPricingCopySanitizer;
use Illuminate\Console\Command;

class AuditInternalPricingCopy extends Command
{
    protected $signature = 'catalog:audit-internal-copy
                            {--limit= : Maximum products to scan}
                            {--sku= : Inspect a single SKU first}
                            {--csv= : Write findings to a CSV path}';

    protected $description = 'Report customer-facing product copy that looks like internal pricing notes or SEO-implementation commentary (does not change the database)';

    public function handle(InternalPricingCopySanitizer $sanitizer): int
    {
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $sku = trim((string) $this->option('sku'));

        if ($sku !== '') {
            $inspect = $sanitizer->inspectSku($sku);
            if ($inspect === null) {
                $this->error('SKU not found: '.$sku);
            } else {
                $this->info($inspect['sku'].' — '.$inspect['name']);
                $this->line('URL: '.$inspect['url']);
                $this->line('Needs scrub: '.($inspect['needs_scrub'] ? 'yes' : 'no'));
                $this->line('Phrases: '.($inspect['phrases'] === [] ? '(none)' : implode(', ', $inspect['phrases'])));
                $this->line('Excerpt: '.$inspect['excerpt']);
                $this->newLine();
            }
        }

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
            $count = $sanitizer->writeAuditCsv($csv, $limit);
            $this->info('Wrote '.$csv.' ('.$count.' row(s)).');
        }

        return self::SUCCESS;
    }
}
