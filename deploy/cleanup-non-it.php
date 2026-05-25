<?php

/**
 * Remove non-IT products and categories (cPanel)
 *
 * 1. Git pull latest code
 * 2. Copy to public_html/cleanup-non-it.php and set CLEANUP_KEY
 * 3. Preview: https://www.urbanfocus.co.za/cleanup-non-it.php?key=YOUR_SECRET&preview=1
 * 4. Run:      https://www.urbanfocus.co.za/cleanup-non-it.php?key=YOUR_SECRET
 * 5. DELETE this file when done
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

$cleanup = $app->make(App\Services\ProductCleanupService::class);

if (isset($_GET['preview'])) {
    echo "Non-IT cleanup preview\n";
    echo str_repeat('-', 40)."\n";

    try {
        $preview = $cleanup->previewNonItCleanup();

        echo "Blocklist terms loaded: {$preview['terms_loaded']}\n";
        echo "Total products in catalog: {$preview['total_products']}\n";
        echo "Non-IT products to delete: {$preview['products_to_delete']}\n";
        echo "Non-IT categories to delete: {$preview['categories_to_delete']}\n";

        if ($preview['excluded_categories'] !== []) {
            echo "\nCategories:\n";
            foreach (array_slice($preview['excluded_categories'], 0, 25) as $name) {
                echo "- {$name}\n";
            }
            if (count($preview['excluded_categories']) > 25) {
                echo '… and '.(count($preview['excluded_categories']) - 25)." more\n";
            }
        }

        if ($preview['sample_products'] !== []) {
            echo "\nSample products:\n";
            foreach ($preview['sample_products'] as $name) {
                echo "- {$name}\n";
            }
        }

        if ($preview['terms_loaded'] === 0) {
            echo "\nWARNING: No blocklist terms loaded. Run git pull and php artisan config:clear.\n";
        }

        if ($preview['products_to_delete'] === 0 && $preview['categories_to_delete'] === 0) {
            echo "\nNothing left to remove — cleanup may have already completed.\n";
        }
    } catch (Throwable $e) {
        echo 'FAILED: '.$e->getMessage()."\n";
        echo $e->getFile().':'.$e->getLine()."\n";
    }

    exit;
}

echo "Non-IT catalog cleanup\n";
echo str_repeat('-', 40)."\n";

try {
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

    if ($result['products_deleted'] === 0 && $result['categories_deleted'] === 0) {
        echo "\nTip: run with &preview=1 to see what would match.\n";
    }
} catch (Throwable $e) {
    echo 'FAILED: '.$e->getMessage()."\n";
    echo $e->getFile().':'.$e->getLine()."\n";
}

echo "\nDELETE public_html/cleanup-non-it.php now.\n";
