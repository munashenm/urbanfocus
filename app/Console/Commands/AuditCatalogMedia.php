<?php

namespace App\Console\Commands;

use App\Services\CatalogMediaHealthService;
use Illuminate\Console\Command;

class AuditCatalogMedia extends Command
{
    protected $signature = 'catalog:audit-media
                            {--check-remote : HTTP-check remote image URLs for 404/403}
                            {--recover : Attach high-confidence SKU/brand images from local/catalog sources}';

    protected $description = 'Audit every active product image and completeness field; optionally recover exact SKU matches';

    public function handle(CatalogMediaHealthService $health): int
    {
        $checkRemote = (bool) $this->option('check-remote');
        $recover = (bool) $this->option('recover');

        if (! $recover) {
            $this->comment('Audit only — products will not be modified. Pass --recover for high-confidence SKU image recovery.');
        }

        $report = $health->run(checkRemote: $checkRemote, recover: $recover);
        $counts = $report['counts'];

        $this->info('Total products scanned: '.$counts['total_scanned']);
        $this->line('Valid product images: '.$counts['valid_images']);
        $this->line('Missing images: '.$counts['missing_images']);
        $this->line('Broken images: '.$counts['broken_images']);
        $this->line('Placeholder images: '.$counts['placeholder_images']);
        $this->line('Recovered images: '.$counts['recovered_images']);
        $this->line('Still requiring manual images: '.$counts['still_requiring_manual_images']);
        $this->line('Missing titles: '.$counts['missing_titles']);
        $this->line('Missing SKUs: '.$counts['missing_skus']);
        $this->line('Missing prices: '.$counts['missing_prices']);
        $this->line('Duplicate SKUs: '.$counts['duplicate_skus']);
        $this->line('Missing brands: '.$counts['missing_brands']);
        $this->line('Missing descriptions: '.$counts['missing_descriptions']);

        if (! empty($report['huawei_02356uum'])) {
            $this->newLine();
            $this->info('Huawei SKU 02356UUM');
            foreach ($report['huawei_02356uum'] as $row) {
                $this->line(sprintf(
                    '  #%d sku=%s active=%s name=%s meta=%s url=%s image=%s problems=%s',
                    $row['id'],
                    $row['sku'],
                    $row['is_active'] ? 'yes' : 'no',
                    $row['name'],
                    $row['meta_title'] ?: '—',
                    $row['product_url'] ?: '—',
                    $row['image_status'],
                    implode(', ', $row['problems']) ?: 'none'
                ));
            }
        } else {
            $this->warn('Huawei SKU 02356UUM was not found in the product table.');
        }

        $this->newLine();
        $this->line('Wrote storage/app/catalog-media-audit.json');
        $this->line('Wrote storage/app/reports/missing-product-images.csv');
        $this->line('Wrote storage/app/reports/catalogue-data-problems.csv');

        return self::SUCCESS;
    }
}
