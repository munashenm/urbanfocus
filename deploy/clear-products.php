<?php

/**
 * Delete ALL products (hard delete) — use before a fresh CSV import.
 *
 * 1. Copy urbanfocus/deploy/clear-products.php → public_html/clear-products.php
 * 2. Edit CLEAR_KEY below
 * 3. Visit: https://www.urbanfocus.co.za/clear-products.php?key=YOUR_SECRET&confirm=DELETE-ALL-PRODUCTS
 * 4. DELETE this file immediately after use
 */

declare(strict_types=1);

const CLEAR_KEY = 'CHANGE-ME-clear-products';

if (($_GET['key'] ?? '') !== CLEAR_KEY) {
    http_response_code(403);
    exit('Forbidden');
}

if (($_GET['confirm'] ?? '') !== 'DELETE-ALL-PRODUCTS') {
    http_response_code(400);
    exit('Add &confirm=DELETE-ALL-PRODUCTS to the URL to proceed.');
}

$laravelRoot = dirname(__DIR__).'/urbanfocus';

header('Content-Type: text/html; charset=utf-8');
echo '<pre>';

if (! file_exists($laravelRoot.'/vendor/autoload.php')) {
    exit("Error: urbanfocus/vendor not found.\n");
}

require $laravelRoot.'/vendor/autoload.php';
$app = require_once $laravelRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = $app->make(App\Services\ProductCleanupService::class);

echo "Deleting all products...\n\n";

try {
    $result = $service->deleteAll();
    Illuminate\Support\Facades\Cache::forget('sitemap.xml');
    echo "Deleted {$result['deleted']} product(s).\n";
    echo "Removed {$result['images_removed']} image file(s).\n";
    echo "\n✓ Done. Import your CSV via Admin → Catalog & Feeds.\n";
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
}

echo "\nDELETE public_html/clear-products.php now.\n</pre>";
