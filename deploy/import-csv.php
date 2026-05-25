<?php

/**
 * Bulk CSV product import (cPanel — no time limit)
 *
 * For large files (e.g. distributor exports with thousands of rows + image downloads).
 *
 * 1. Git pull latest code
 * 2. Upload your CSV to: urbanfocus/storage/imports/products.csv
 * 3. Copy this file to public_html/import-csv.php and set IMPORT_KEY
 * 4. Preview: https://www.urbanfocus.co.za/import-csv.php?key=YOUR_SECRET&preview=1
 * 5. Run:    https://www.urbanfocus.co.za/import-csv.php?key=YOUR_SECRET
 * 6. DELETE public_html/import-csv.php when done
 *
 * Rules: IT products only, must have image URL(s) and cost price. Retail = markup + rounding from config/pricing.php
 */

declare(strict_types=1);

const IMPORT_KEY = 'CHANGE-ME-import-csv-secret';

if (($_GET['key'] ?? '') !== IMPORT_KEY) {
    http_response_code(403);
    exit('Forbidden');
}

$laravelRoot = dirname(__DIR__).'/urbanfocus';
$csvPath = $laravelRoot.'/storage/imports/products.csv';

header('Content-Type: text/plain; charset=utf-8');
@set_time_limit(0);
@ini_set('memory_limit', '512M');

echo "Urban Focus bulk CSV import\n";
echo str_repeat('-', 40)."\n";

if (! file_exists($csvPath)) {
    exit("CSV not found.\nUpload your file to:\n  urbanfocus/storage/imports/products.csv\n");
}

echo 'File: '.$csvPath.' ('.number_format(filesize($csvPath))." bytes)\n\n";

require $laravelRoot.'/vendor/autoload.php';
$app = require_once $laravelRoot.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$import = $app->make(App\Services\ProductImportService::class);
$pricing = $import->pricingPolicy();

echo "Pricing: {$pricing['markup_percent']}% markup, round {$pricing['round_mode']} to R{$pricing['round_to']}\n";
echo "Example: R{$pricing['example']['cost']} cost → R{$pricing['example']['retail']} retail\n\n";

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
