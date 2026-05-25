<?php

/**
 * Remove non-IT products and categories (cPanel)
 *
 * Use this if Admin → Remove Non-IT Products times out (500 error).
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
@set_time_limit(0);
@ini_set('memory_limit', '512M');

require $laravelRoot.'/vendor/autoload.php';
$app = require_once $laravelRoot.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Non-IT catalog cleanup\n";
echo str_repeat('-', 40)."\n";

try {
    $cleanup = $app->make(App\Services\ProductCleanupService::class);
    $result = $cleanup->removeNonItProducts();

    echo "Products deleted: {$result['products_deleted']}\n";
    echo "Categories deleted: {$result['categories_deleted']}\n";
    echo "Images removed: {$result['images_removed']}\n";

    if (! empty($result['errors'])) {
        echo "\nErrors (first 10):\n";
        foreach (array_slice($result['errors'], 0, 10) as $error) {
            echo "- {$error}\n";
        }
    }
} catch (Throwable $e) {
    echo 'FAILED: '.$e->getMessage()."\n";
    echo $e->getFile().':'.$e->getLine()."\n";
}

echo "\nDELETE public_html/cleanup-non-it.php now.\n";
