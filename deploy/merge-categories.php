<?php

/**
 * Merge all products into canonical categories (cPanel / no Terminal).
 *
 * SETUP
 * 1. Git pull latest code into ~/urbanfocus
 * 2. Copy urbanfocus/deploy/merge-categories.php → public_html/merge-categories.php
 * 3. Edit MERGE_KEY below (16+ chars, not CHANGE-ME)
 * 4. Run in order:
 *    a) merge-categories.php?key=YOUR_SECRET&migrate=1
 *    b) merge-categories.php?key=YOUR_SECRET&dry-run=1
 *    c) merge-categories.php?key=YOUR_SECRET&run=1&limit=25
 *    d) merge-categories.php?key=YOUR_SECRET&run=1
 * 5. DELETE public_html/merge-categories.php when finished
 */

declare(strict_types=1);

const MERGE_KEY = 'CHANGE-ME-merge-categories-secret';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');

if (str_contains(MERGE_KEY, 'CHANGE-ME') || strlen(MERGE_KEY) < 16) {
    http_response_code(403);
    exit('Refusing to run: edit this file and set a strong, unique secret key (16+ chars, no "CHANGE-ME") before use.');
}

if (! hash_equals(MERGE_KEY, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden');
}

$laravelRoot = dirname(__DIR__).'/urbanfocus';
$host = $_SERVER['HTTP_HOST'] ?? 'www.urbanfocus.co.za';
$base = 'https://'.$host.'/merge-categories.php?key='.urlencode((string) $_GET['key']);

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

/** @var \App\Services\CategoryMergeService $merge */
$merge = $app->make(\App\Services\CategoryMergeService::class);

if (isset($_GET['migrate'])) {
    echo "=== Running database migrations ===\n";
    try {
        $exitCode = $kernel->call('migrate', ['--force' => true]);
        echo trim($kernel->output())."\n";
        echo $exitCode === 0 ? "✓ Migrations complete.\n\n" : "✗ Migration exit code: {$exitCode}\n\n";
    } catch (Throwable $e) {
        echo 'Migration ERROR: '.$e->getMessage()."\n\n";
    }
}

if (! isset($_GET['dry-run']) && ! isset($_GET['run'])) {
    echo "=== Merge products into canonical categories ===\n";
    echo 'Time: '.date('Y-m-d H:i:s')."\n\n";
    echo "This runs the full category merge:\n";
    echo "  1. Remap products from legacy categories (e.g. laptops-notebooks)\n";
    echo "  2. Heuristic assignment for any remaining stragglers\n";
    echo "  3. Create slug redirects and deactivate empty legacy categories\n\n";
    echo "1. Migrations (first time only):\n   {$base}&migrate=1\n\n";
    echo "2. Preview (no changes):\n   {$base}&dry-run=1\n\n";
    echo "3. Test 25 products:\n   {$base}&run=1&limit=25\n\n";
    echo "4. Full merge:\n   {$base}&run=1\n\n";
    echo "Tip: back up your database in phpMyAdmin before step 4.\n";
    echo "DELETE this file from public_html when finished.\n</pre>";
    exit;
}

echo "=== Merge products into canonical categories ===\n";
echo 'Time: '.date('Y-m-d H:i:s')."\n\n";

if (isset($_GET['dry-run'])) {
    $preview = $merge->preview();
    $reorg = $preview['reorganization'];

    echo "DRY RUN — no product or category changes made\n\n";
    echo "Products on legacy categories now: {$preview['legacy_products']}\n";
    echo "Products to remap (reorg pass): {$reorg['products_to_move']}\n";
    echo "Products unchanged (reorg pass): {$reorg['products_unchanged']}\n";
    echo "Redirects to create: {$reorg['redirects_to_create']}\n\n";

    if ($reorg['sample_moves'] !== []) {
        echo "Sample moves:\n";
        foreach ($reorg['sample_moves'] as $move) {
            echo "  {$move['count']}× {$move['from']} → {$move['to']}\n";
        }
        echo "\n";
    }

    echo "Next — test 25 products:\n{$base}&run=1&limit=25\n\n";
    echo "Then full merge:\n{$base}&run=1\n</pre>";
    exit;
}

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : null;

echo 'Applying category merge';
echo $limit ? " (limit {$limit} products)" : ' (all products)';
echo "...\n\n";

try {
    $result = $merge->merge(backup: true, limit: $limit);

    echo "✓ Reorg remapped: {$result['reorganize']['moved']}\n";
    echo "✓ Heuristic assigned: {$result['assign']['categorized']}\n";
    echo "✓ Redirects created: {$result['reorganize']['redirects']}\n";
    echo "✓ Legacy categories deactivated: {$result['reorganize']['deactivated']}\n";
    echo "✓ Products still on legacy categories: {$result['legacy_products_remaining']}\n\n";

    echo "Clearing caches...\n";
    foreach (['config:clear', 'route:clear', 'view:clear', 'cache:clear'] as $cmd) {
        $kernel->call($cmd);
        $out = trim($kernel->output());
        if ($out !== '') {
            echo $out."\n";
        }
    }

    if ($limit) {
        echo "\nTest complete. Run full merge when ready:\n{$base}&run=1\n";
    } else {
        echo "\n✓ Category merge complete.\n";
        echo "Check /shop?category=computing-office/laptops and Admin → Categories.\n";
    }
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
    echo $e->getFile().':'.$e->getLine()."\n";
}

echo "\nDELETE public_html/merge-categories.php now.\n</pre>";
