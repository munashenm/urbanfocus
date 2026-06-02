<?php

/**
 * Bulk CSV product import (cPanel — no time limit)
 *
 * For large files (e.g. Pinnacle/Esquire distributor exports with thousands of rows + image downloads).
 *
 * 1. Git pull latest code
 * 2. Upload your CSV to: urbanfocus/storage/imports/products.csv
 *    (or astrum_pricelist.csv / astrum_products_level2.csv / scoop_pricelist.csv with &file=...)
 * 3. Copy this file to public_html/import-csv.php and set IMPORT_KEY
 * 4. Preview: https://www.urbanfocus.co.za/import-csv.php?key=YOUR_SECRET&preview=1
 * 5. Run:    https://www.urbanfocus.co.za/import-csv.php?key=YOUR_SECRET
 * 6. DELETE public_html/import-csv.php when done
 *
 * Rules: IT products only (except Scoop/Astrum distributor feeds — all Scoop rows import).
 * Must have cost/dealer price. Image URL(s) required except Astrum (placeholder attached).
 * Astrum: run scripts/convert_astrum_pricelist.py on the xlsx first. price column = storefront price as-is (ASTRUM_RETAIL_FROM=price).
 * Other feeds: retail = markup + rounding from config/pricing.php
 *
 * Scoop import:
 *   Upload scoop_pricelist.csv then preview/run with &file=scoop_pricelist.csv
 */

declare(strict_types=1);

const IMPORT_KEY = 'CHANGE-ME-import-csv-secret';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');

if (str_contains(IMPORT_KEY, 'CHANGE-ME') || strlen(IMPORT_KEY) < 16) {
    http_response_code(403);
    exit('Refusing to run: edit this file and set a strong, unique secret key (16+ chars, no "CHANGE-ME") before use.');
}

if (! hash_equals(IMPORT_KEY, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden');
}

$laravelRoot = dirname(__DIR__).'/urbanfocus';
$csvFile = basename((string) ($_GET['file'] ?? 'products.csv'));
$csvFile = preg_match('/^[a-zA-Z0-9._-]+\.csv$/', $csvFile) ? $csvFile : 'products.csv';
$csvPath = $laravelRoot.'/storage/imports/'.$csvFile;

header('Content-Type: text/plain; charset=utf-8');
@set_time_limit(0);
@ini_set('memory_limit', '512M');

echo "Urban Focus bulk CSV import\n";
echo str_repeat('-', 40)."\n";

if (! file_exists($csvPath)) {
    exit("CSV not found.\nUpload your file to:\n  urbanfocus/storage/imports/{$csvFile}\n");
}

echo 'File: '.$csvPath.' ('.number_format(filesize($csvPath))." bytes)\n\n";

require $laravelRoot.'/vendor/autoload.php';
$app = require_once $laravelRoot.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$import = $app->make(App\Services\ProductImportService::class);
$pricing = $import->pricingPolicy();

echo "Pricing: {$pricing['markup_percent']}% markup, round {$pricing['round_mode']} to R{$pricing['round_to']}\n";
echo "Example: R{$pricing['example']['cost']} cost → R{$pricing['example']['retail']} retail\n";
if (! empty($pricing['scoop_example'])) {
    $scoop = $pricing['scoop_example'];
    echo "Scoop: R{$scoop['dealer_ex_vat']} dealer ex-VAT → R{$scoop['cost_inc_vat']} inc-VAT → R{$scoop['retail']} retail\n";
}
echo "\n";

if (isset($_GET['preview'])) {
    $result = $import->previewFromPath($csvPath);

    echo "PREVIEW (no changes made)\n";
    echo "Rows scanned: {$result['total_rows']}\n";
    echo "Would create: {$result['would_create']}\n";
    echo "Would update: {$result['would_update']}\n";
    echo "Skip empty: {$result['skipped']}\n";
    echo "Skip non-IT: {$result['skippedNonIt']}\n";
    echo "Skip no image: {$result['skippedNoImage']}\n";
    echo "Skip no price: {$result['skippedNoPrice']}\n";

    if (! empty($result['samples']['import'])) {
        echo "\nSample imports (cost → retail):\n";
        foreach ($result['samples']['import'] as $sample) {
            echo '- '.$sample['name'].' — R'.number_format($sample['cost'], 2).' → R'.number_format($sample['retail'], 2)."\n";
        }
    }

    exit;
}

$result = $import->importFromPath($csvPath, function ($imported, $updated, $skippedNoImage, $row) {
    echo "Progress row {$row}: imported {$imported}, updated {$updated}, skipped (no image) {$skippedNoImage}\n";
    flush();
});

echo "\nDone.\n";
echo "Imported: {$result['imported']}\n";
echo "Updated: {$result['updated']}\n";
echo "Skipped empty rows: {$result['skipped']}\n";
echo "Skipped without images: {$result['skippedNoImage']}\n";
echo "Skipped without price: {$result['skippedNoPrice']}\n";
echo "Skipped image download failed: {$result['skippedImageFailed']}\n";
echo "Skipped non-IT: {$result['skippedNonIt']}\n";

if (! empty($result['errors'])) {
    echo "\nErrors (first 20):\n";
    foreach (array_slice($result['errors'], 0, 20) as $error) {
        echo "- {$error}\n";
    }
}

try {
    $kernel->call('cache:clear');
    echo "\nCache cleared.\n";
} catch (Throwable) {
}

echo "\nDELETE public_html/import-csv.php now.\n";
