<?php

/**
 * Bulk CSV product import (cPanel — no time limit)
 *
 * For large files (e.g. distributor exports with thousands of rows + image downloads).
 *
 * 1. Git pull latest code
 * 2. Upload your CSV to: urbanfocus/storage/imports/products.csv
 * 3. Copy this file to public_html/import-csv.php and set IMPORT_KEY
 * 4. Visit: https://www.urbanfocus.co.za/import-csv.php?key=YOUR_SECRET
 * 5. DELETE public_html/import-csv.php when done
 *
 * Supports WooCommerce CSV and Esquire-style exports (ProductName, ProductCode, Category, Image, etc.)
 * Rows without images are skipped automatically.
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

$result = $import->importFromPath($csvPath, function ($imported, $updated, $skippedNoImage, $row) {
    echo "Progress row {$row}: imported {$imported}, updated {$updated}, skipped (no image) {$skippedNoImage}\n";
    flush();
});

echo "\nDone.\n";
echo "Imported: {$result['imported']}\n";
echo "Updated: {$result['updated']}\n";
echo "Skipped empty rows: {$result['skipped']}\n";
echo "Skipped without images: {$result['skippedNoImage']}\n";

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
