<?php

/**
 * Hide duplicate catalogue products (same name/brand with no SKU, or same SKU).
 *
 * 1. Git pull latest code
 * 2. Copy to public_html/hide-duplicate-products.php and set HIDE_DUP_KEY
 * 3. Preview: https://www.urbanfocus.co.za/hide-duplicate-products.php?key=YOUR_SECRET&preview=1
 * 4. Run:     https://www.urbanfocus.co.za/hide-duplicate-products.php?key=YOUR_SECRET
 * 5. DELETE this file when done
 */

declare(strict_types=1);
use App\Models\Product;
use App\Services\CatalogDeduper;
use Illuminate\Contracts\Console\Kernel;

const HIDE_DUP_KEY = 'CHANGE-ME-hide-duplicate-products-secret';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');

if (str_contains(HIDE_DUP_KEY, 'CHANGE-ME') || strlen(HIDE_DUP_KEY) < 16) {
    http_response_code(403);
    exit('Refusing to run: edit this file and set a strong, unique secret key (16+ chars, no "CHANGE-ME") before use.');
}

if (! hash_equals(HIDE_DUP_KEY, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden');
}

$laravelRoot = dirname(__DIR__).'/urbanfocus';

header('Content-Type: text/plain; charset=utf-8');
@set_time_limit(0);
@ini_set('memory_limit', '512M');

require $laravelRoot.'/vendor/autoload.php';
$app = require_once $laravelRoot.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$deduper = $app->make(CatalogDeduper::class);

echo "Hide duplicate catalogue products\n";
echo str_repeat('-', 40)."\n";

try {
    $ids = $deduper->scanIdsToHide();
    echo 'Duplicate rows to hide: '.count($ids)."\n";

    if ($ids !== []) {
        $samples = Product::whereIn('id', $ids)->orderBy('name')->limit(25)->get();
        foreach ($samples as $product) {
            echo '- #'.$product->id.' '.$product->brand.' '.$product->name."\n";
        }
        if (count($ids) > 25) {
            echo '… and '.(count($ids) - 25)." more\n";
        }
    }

    if (isset($_GET['preview'])) {
        echo "\nPreview only. Re-run without &preview=1 to unpublish duplicates.\n";
        exit;
    }

    $result = $deduper->deactivateDuplicates();
    echo "\nUnpublished: {$result['hidden']}\n";
    echo "Homepage/search will now show one copy of each duplicate title.\n";
} catch (Throwable $e) {
    echo 'FAILED: '.$e->getMessage()."\n";
    http_response_code(500);
}
