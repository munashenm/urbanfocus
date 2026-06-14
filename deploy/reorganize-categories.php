<?php

/**
 * Reorganize categories and remap products safely (cPanel / no Terminal).
 *
 * SETUP
 * 1. Git pull latest code into ~/urbanfocus
 * 2. File Manager: copy urbanfocus/deploy/reorganize-categories.php → public_html/reorganize-categories.php
 * 3. Edit REORG_KEY below (16+ chars, not CHANGE-ME)
 * 4. Run in order (replace YOUR_SECRET):
 *    a) migrate.php?key=YOUR_SECRET          — or add &migrate=1 to step b
 *    b) reorganize-categories.php?key=YOUR_SECRET&dry-run=1
 *    c) reorganize-categories.php?key=YOUR_SECRET&run=1&limit=10
 *    d) reorganize-categories.php?key=YOUR_SECRET&run=1
 * 5. DELETE public_html/reorganize-categories.php after success
 */

declare(strict_types=1);

const REORG_KEY = 'CHANGE-ME-reorganize-categories-secret';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');

if (str_contains(REORG_KEY, 'CHANGE-ME') || strlen(REORG_KEY) < 16) {
    http_response_code(403);
    exit('Refusing to run: edit this file and set a strong, unique secret key (16+ chars, no "CHANGE-ME") before use.');
}

if (! hash_equals(REORG_KEY, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden');
}

$laravelRoot = dirname(__DIR__).'/urbanfocus';
$host = $_SERVER['HTTP_HOST'] ?? 'www.urbanfocus.co.za';
$base = 'https://'.$host.'/reorganize-categories.php?key='.urlencode((string) $_GET['key']);

header('Content-Type: text/html; charset=utf-8');
echo '<pre style="font:14px/1.5 monospace;white-space:pre-wrap">';

if (! is_dir($laravelRoot)) {
    exit("STOP: urbanfocus folder not found at {$laravelRoot}\n");
}

if (! file_exists($laravelRoot.'/vendor/autoload.php')) {
    exit("STOP: vendor/ missing in urbanfocus/\n");
}

require $laravelRoot.'/vendor/autoload.php';
/** @var \Illuminate\Foundation\Application $app */
$app = require_once $laravelRoot.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$runMigrate = isset($_GET['migrate']) || isset($_GET['dry-run']) || isset($_GET['run']);

if ($runMigrate) {
    echo "=== Running database migrations ===\n";
    try {
        $exitCode = $kernel->call('migrate', ['--force' => true]);
        echo trim($kernel->output())."\n";
        echo $exitCode === 0 ? "✓ Migrations complete.\n\n" : "✗ Migration exit code: {$exitCode}\n\n";
    } catch (Throwable $e) {
        echo 'Migration ERROR: '.$e->getMessage()."\n\n";
    }
}

$service = app(App\Services\CategoryReorganizationService::class);

if (! isset($_GET['dry-run']) && ! isset($_GET['run'])) {
    echo "=== Urban Focus category reorganization (no Terminal) ===\n";
    echo 'Time: '.date('Y-m-d H:i:s')."\n\n";
    echo "Run these URLs in order (same browser tabs are fine):\n\n";
    echo "1. Migrations (if you have not run migrate.php yet):\n   {$base}&migrate=1&dry-run=1\n\n";
    echo "2. Preview only (no changes):\n   {$base}&dry-run=1\n\n";
    echo "3. Test on 10 products:\n   {$base}&run=1&limit=10\n\n";
    echo "4. Full migration (all products):\n   {$base}&run=1\n\n";
    echo "Tip: back up your database in phpMyAdmin before step 4.\n";
    echo "DELETE this file from public_html when finished.\n</pre>";
    exit;
}

echo "=== Urban Focus category reorganization ===\n";
echo 'Time: '.date('Y-m-d H:i:s')."\n\n";

if (isset($_GET['dry-run'])) {
    $preview = $service->preview();
    echo "DRY RUN — no product or category changes made\n\n";
    echo "Main categories in new tree: {$preview['main_categories']}\n";
    echo "Products to remap: {$preview['products_to_move']}\n";
    echo "Products unchanged: {$preview['products_unchanged']}\n";
    echo "Redirects to create: {$preview['redirects_to_create']}\n\n";

    if ($preview['sample_moves'] !== []) {
        echo "Sample moves:\n";
        foreach ($preview['sample_moves'] as $move) {
            echo "  {$move['count']}× {$move['from']} → {$move['to']}\n";
        }
        echo "\n";
    }

    if ($preview['orphan_categories'] !== []) {
        echo "Old categories with products (first 10):\n";
        foreach (array_slice($preview['orphan_categories'], 0, 10) as $orphan) {
            echo "  {$orphan['products']} products in {$orphan['name']} → {$orphan['suggested']}\n";
        }
        echo "\n";
    }

    echo "Next — test 10 products:\n{$base}&run=1&limit=10\n\n";
    echo "Then full run:\n{$base}&run=1\n</pre>";
    exit;
}

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : null;

echo 'Applying category reorganization';
echo $limit ? " (limit {$limit} products)" : ' (all products)';
echo "...\n\n";

try {
    $result = $service->reorganize(backup: true, limit: $limit);

    echo "✓ Products remapped: {$result['moved']}\n";
    echo "✓ Products processed: {$result['processed']}\n";
    echo "✓ Redirects created: {$result['redirects']}\n";
    echo "✓ Orphan categories deactivated: {$result['deactivated']}\n\n";

    echo "Clearing caches...\n";
    foreach (['config:clear', 'route:clear', 'view:clear', 'cache:clear'] as $cmd) {
        $kernel->call($cmd);
        $out = trim($kernel->output());
        if ($out !== '') {
            echo $out."\n";
        }
    }

    if ($limit) {
        echo "\nTest complete. Run full migration when ready:\n{$base}&run=1\n";
    } else {
        echo "\n✓ Category reorganization complete.\n";
        echo "Check Admin → Categories and a few product pages.\n";
    }
} catch (Throwable $e) {
    echo "ERROR: ".$e->getMessage()."\n";
    echo $e->getFile().':'.$e->getLine()."\n";
}

echo "\nDELETE public_html/reorganize-categories.php now.\n</pre>";
