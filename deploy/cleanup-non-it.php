<?php

/**
 * Remove non-IT products and categories (cPanel)
 *
 * 1. Git pull latest code
 * 2. Copy to public_html/cleanup-non-it.php and set CLEANUP_KEY
 * 3. Visit: https://www.urbanfocus.co.za/cleanup-non-it.php?key=YOUR_SECRET
 * 4. DELETE this file when done
 */

declare(strict_types=1);

const CLEANUP_KEY = 'CHANGE-ME-cleanup-non-it-secret';

if (($_GET['key'] ?? '') !== CLEANUP_KEY) {
    http_response_code(403);
    exit('Forbidden');
}

$laravelRoot = dirname(__DIR__).'/urbanfocus';

header('Content-Type: text/plain; charset=utf-8');

require $laravelRoot.'/vendor/autoload.php';
$app = require_once $laravelRoot.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$cleanup = $app->make(App\Services\ProductCleanupService::class);
$result = $cleanup->removeNonItProducts();

echo "Non-IT cleanup complete\n";
echo str_repeat('-', 40)."\n";
echo "Products deleted: {$result['products_deleted']}\n";
echo "Categories deleted: {$result['categories_deleted']}\n";
echo "Images removed: {$result['images_removed']}\n";
echo "\nExcluded terms are in config/catalog.php\n";
echo "DELETE public_html/cleanup-non-it.php now.\n";
