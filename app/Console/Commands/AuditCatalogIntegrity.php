<?php

namespace App\Console\Commands;

use App\Services\CatalogIntegrityService;
use Illuminate\Console\Command;

class AuditCatalogIntegrity extends Command
{
    protected $signature = 'catalog:audit-integrity
                            {--apply-taxonomy : Reassign only high-confidence category mistakes}';

    protected $description = 'Audit catalogue SKU, pricing, image and taxonomy integrity';

    public function handle(CatalogIntegrityService $integrity): int
    {
        $report = $integrity->audit();
        $totals = $report['totals'];

        $counts = $report['counts'];
        $this->info("Products: {$totals['all']} (active {$totals['active']}, inactive {$totals['inactive']})");
        $this->line('Duplicate SKU groups: '.$counts['duplicate_sku_groups']);
        $this->line('SKU/name conflicts: '.$counts['sku_name_conflicts']);
        $this->line('Duplicate names: '.$counts['duplicate_name_groups']);
        $this->line('SEO title collisions: '.$counts['seo_title_collisions']);
        $this->line('Missing SKUs: '.$counts['missing_skus']);
        $this->line('Missing prices: '.$counts['missing_prices']);
        $this->line('Missing images: '.$counts['missing_images']);
        $this->line('Missing descriptions: '.$counts['missing_descriptions']);
        $this->line('Malformed names: '.$counts['malformed_names']);
        $this->line('Brand/SKU mismatches: '.$counts['wrong_brand_sku']);
        $this->line('High-confidence taxonomy fixes: '.$counts['taxonomy_high_confidence']);
        $this->line('Taxonomy review only: '.$counts['taxonomy_review']);
        $this->line('USW-16P related rows: '.$counts['usw_16p_rows']);

        foreach ($report['usw_16p']['rows'] as $row) {
            $this->line(sprintf(
                '  #%d %s sku=%s brand=%s active=%s hidden=%s %s',
                $row['id'],
                $row['name'],
                $row['sku'],
                $row['brand'],
                $row['active'] ? 'yes' : 'no',
                $row['hidden_as_duplicate'] ? 'yes' : 'no',
                $row['url']
            ));
        }

        if ($this->option('apply-taxonomy')) {
            $result = $integrity->applyHighConfidenceTaxonomyFixes();
            $this->info("Applied {$result['fixed']} high-confidence category correction(s).");
            foreach ($result['samples'] as $sample) {
                $this->line('  '.$sample);
            }
        } else {
            $this->comment('Re-run with --apply-taxonomy to move laptop chargers and interactive boards only.');
        }

        $path = storage_path('app/catalog-integrity-audit.json');
        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->line('Wrote '.$path);

        return self::SUCCESS;
    }
}
