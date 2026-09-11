<?php

/**
 * Report-only scan of product copy for internal pricing language.
 *
 * Does not write to the database. From the Laravel root:
 *   php scripts/audit-product-content.php
 *   php artisan catalog:audit-internal-copy --csv=storage/app/internal-copy-audit.csv
 */

declare(strict_types=1);

use App\Services\InternalPricingCopySanitizer;
use Illuminate\Contracts\Console\Kernel;

$laravelRoot = dirname(__DIR__);

require $laravelRoot.'/vendor/autoload.php';
$app = require $laravelRoot.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$findings = $app->make(InternalPricingCopySanitizer::class)->auditCatalog();

echo "id,sku,title,phrase,url\n";
foreach ($findings as $row) {
    echo implode(',', array_map(
        static fn ($value) => '"'.str_replace('"', '""', (string) $value).'"',
        [$row['id'], $row['sku'] ?? '', $row['title'], $row['phrase'], $row['url']]
    ))."\n";
}

fwrite(STDERR, count($findings)." finding(s). Database was not changed.\n");
