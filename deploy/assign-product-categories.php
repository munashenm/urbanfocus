<?php

/**
 * Assign products to canonical categories (cPanel / no Terminal).
 *
 * SETUP
 * 1. Git pull latest code into ~/urbanfocus
 * 2. Copy urbanfocus/deploy/assign-product-categories.php → public_html/assign-product-categories.php
 * 3. Edit ASSIGN_KEY below (16+ chars, not CHANGE-ME)
 * 4. Run in order:
 *    a) assign-product-categories.php?key=YOUR_SECRET&dry-run=1
 *    b) assign-product-categories.php?key=YOUR_SECRET&run=1&limit=25
 *    c) assign-product-categories.php?key=YOUR_SECRET&run=1
 * 5. DELETE public_html/assign-product-categories.php when finished
 */

declare(strict_types=1);

const ASSIGN_KEY = 'CHANGE-ME-assign-categories-secret';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');

if (str_contains(ASSIGN_KEY, 'CHANGE-ME') || strlen(ASSIGN_KEY) < 16) {
    http_response_code(403);
    exit('Refusing to run: edit this file and set a strong, unique secret key (16+ chars, no "CHANGE-ME") before use.');
}

if (! hash_equals(ASSIGN_KEY, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden');
}

$laravelRoot = dirname(__DIR__).'/urbanfocus';
$host = $_SERVER['HTTP_HOST'] ?? 'www.urbanfocus.co.za';
$base = 'https://'.$host.'/assign-product-categories.php?key='.urlencode((string) $_GET['key']);

header('Content-Type: text/html; charset=utf-8');
echo '<pre style="font:14px/1.5 monospace;white-space:pre-wrap">';

if (! is_dir($laravelRoot.'/vendor')) {
    exit("STOP: urbanfocus/vendor missing — run setup or composer install first.\n");
}

require $laravelRoot.'/vendor/autoload.php';
$app = require_once $laravelRoot.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

@set_time_limit(0);
@ini_set('memory_limit', '512M');

/** @var \App\Services\ProductSeoService $seo */
$seo = $app->make(\App\Services\ProductSeoService::class);

if (! isset($_GET['dry-run']) && ! isset($_GET['run'])) {
    echo "=== Assign products to categories ===\n";
    echo 'Time: '.date('Y-m-d H:i:s')."\n\n";
    echo "Maps uncategorised products and legacy categories (e.g. Laptops & Notebooks)\n";
    echo "into the current tree such as Computing & Office Technology → Laptops.\n\n";
    echo "1. Preview (no changes):\n   {$base}&dry-run=1\n\n";
    echo "2. Test 25 products:\n   {$base}&run=1&limit=25\n\n";
    echo "3. Assign all products:\n   {$base}&run=1\n\n";
    echo "Tip: back up your database in phpMyAdmin before step 3.\n";
    echo "DELETE this file from public_html when finished.\n</pre>";
    exit;
}

$dryRun = isset($_GET['dry-run']);
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : null;

echo "=== Assign products to categories ===\n";
echo 'Time: '.date('Y-m-d H:i:s')."\n";
echo $dryRun ? "Mode: DRY RUN (no database changes)\n\n" : "Mode: LIVE\n\n";

try {
    $stats = $seo->assignProductCategories($dryRun, $limit);

    echo "Processed: {$stats['processed']}\n";
    echo "Assigned:  {$stats['categorized']}\n";
    echo "Skipped:   {$stats['skipped']}\n\n";

    if ($stats['samples'] !== []) {
        echo "Sample assignments:\n";
        foreach ($stats['samples'] as $line) {
            echo "  • {$line}\n";
        }
        echo "\n";
    }

    if ($dryRun) {
        echo "Next — test 25 products:\n{$base}&run=1&limit=25\n\n";
        echo "Then assign all:\n{$base}&run=1\n";
    } else {
        echo "✓ Done. Check Admin → Products or browse /category/computing-office/laptops\n";
    }
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
    echo $e->getFile().':'.$e->getLine()."\n";
}

echo "\nDELETE public_html/assign-product-categories.php now.\n</pre>";
