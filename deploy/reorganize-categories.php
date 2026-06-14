<?php

/**
 * Reorganize categories and remap products safely (cPanel / no Terminal).
 *
 * 1. Copy urbanfocus/deploy/reorganize-categories.php → public_html/reorganize-categories.php
 * 2. Set REORG_KEY below
 * 3. Preview: reorganize-categories.php?key=YOUR_SECRET&dry-run=1
 * 4. Test 10 products: reorganize-categories.php?key=YOUR_SECRET&run=1&limit=10
 * 5. Full migration: reorganize-categories.php?key=YOUR_SECRET&run=1
 * 6. DELETE this file after use
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

header('Content-Type: text/html; charset=utf-8');
echo '<pre style="font:14px/1.5 monospace;white-space:pre-wrap">';

if (! is_dir($laravelRoot)) {
    exit("STOP: urbanfocus folder not found at {$laravelRoot}\n");
}

require $laravelRoot.'/vendor/autoload.php';
/** @var \Illuminate\Foundation\Application $app */
$app = require_once $laravelRoot.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Urban Focus category reorganization ===\n";
echo 'Time: '.date('Y-m-d H:i:s')."\n\n";

$service = app(App\Services\CategoryReorganizationService::class);

if (isset($_GET['dry-run'])) {
    $preview = $service->preview();
    echo "DRY RUN — no changes made\n\n";
    echo "Products to remap: {$preview['products_to_move']}\n";
    echo "Products unchanged: {$preview['products_unchanged']}\n";
    echo "Main categories: {$preview['main_categories']}\n\n";

    if ($preview['sample_moves'] !== []) {
        echo "Sample moves:\n";
        foreach ($preview['sample_moves'] as $move) {
            echo "  {$move['count']}× {$move['from']} → {$move['to']}\n";
        }
    }

    echo "\nNext: add &run=1&limit=10 to test, then &run=1 for full migration.\n";
    echo "DELETE this file after use.\n</pre>";
    exit;
}

if (! isset($_GET['run'])) {
    echo "Add &dry-run=1 to preview or &run=1 to migrate.\n</pre>";
    exit;
}

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : null;

echo "Running migration";
if ($limit) {
    echo " (limit {$limit} products)";
}
echo "...\n\n";

try {
    $kernel->call('migrate', ['--force' => true]);
    echo trim($kernel->output())."\n\n";

    $result = $service->reorganize(backup: true, limit: $limit);

    echo "✓ Products remapped: {$result['moved']}\n";
    echo "✓ Redirects created: {$result['redirects']}\n";
    echo "✓ Orphan categories deactivated: {$result['deactivated']}\n";
    echo "\nClear caches:\n";
    foreach (['route:clear', 'view:clear', 'cache:clear'] as $cmd) {
        $kernel->call($cmd);
        echo trim($kernel->output())."\n";
    }
} catch (Throwable $e) {
    echo "ERROR: ".$e->getMessage()."\n";
    echo $e->getFile().':'.$e->getLine()."\n";
}

echo "\nDELETE public_html/reorganize-categories.php now.\n</pre>";
