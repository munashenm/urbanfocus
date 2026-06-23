<?php

/**
 * Category merge for cPanel — simplified (use if merge-categories.php returns HTTP 500).
 *
 * 1. Copy urbanfocus/deploy/uf-merge.php → public_html/uf-merge.php
 * 2. Set UF_MERGE_KEY below (same secret as your merge URL key is fine)
 * 3. Visit: https://www.urbanfocus.co.za/uf-merge.php?key=YOUR_SECRET
 * 4. DELETE when finished
 */

declare(strict_types=1);

const UF_MERGE_KEY = 'CHANGE-ME-uf-merge-secret';
const DEFAULT_BATCH = 200;

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');
header('Content-Type: text/html; charset=utf-8');

if (str_contains(UF_MERGE_KEY, 'CHANGE-ME') || strlen(UF_MERGE_KEY) < 16) {
    http_response_code(403);
    exit('Edit UF_MERGE_KEY in this file (16+ chars, no CHANGE-ME).');
}

if (! hash_equals(UF_MERGE_KEY, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden — key mismatch.');
}

$laravelRoot = dirname(__DIR__).'/urbanfocus';
$host = $_SERVER['HTTP_HOST'] ?? 'www.urbanfocus.co.za';
$base = 'https://'.$host.'/uf-merge.php?key='.urlencode((string) $_GET['key']);

echo '<pre style="font:14px/1.5 monospace;white-space:pre-wrap">';
echo "=== UF category merge ===\n";
echo 'Time: '.date('Y-m-d H:i:s')."\n";
echo "Laravel root: {$laravelRoot}\n";
echo 'PHP: '.PHP_VERSION."\n\n";

if (! is_dir($laravelRoot.'/vendor')) {
    exit("STOP: urbanfocus/vendor missing.\n</pre>");
}

// Help — no Laravel
if (! isset($_GET['migrate']) && ! isset($_GET['dry-run']) && ! isset($_GET['run'])) {
    echo "Choose a step:\n\n";
    echo "1. Migrations:\n   {$base}&migrate=1\n\n";
    echo "2. Preview:\n   {$base}&dry-run=1\n\n";
    echo "3. Remap batch:\n   {$base}&run=1&phase=remap&offset=0&batch=".DEFAULT_BATCH."\n\n";
    echo "4. Finalize:\n   {$base}&run=1&phase=finalize\n\n";
    echo "Easier alternative (if this still fails):\n";
    echo "  migrate.php → seed-categories.php → assign-product-categories.php\n</pre>";
    exit;
}

try {
    require $laravelRoot.'/vendor/autoload.php';
    $app = require_once $laravelRoot.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
} catch (Throwable $e) {
    exit('Laravel boot failed: '.$e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n</pre>");
}

@set_time_limit(300);
@ini_set('memory_limit', '512M');

if (isset($_GET['migrate'])) {
    echo "=== Migrations ===\n";
    try {
        $code = $kernel->call('migrate', ['--force' => true]);
        echo trim($kernel->output())."\n";
        echo $code === 0 ? "✓ Done.\n\n" : "✗ Exit {$code}\n\n";
    } catch (Throwable $e) {
        exit('Migration error: '.$e->getMessage()."\n</pre>");
    }
}

try {
    $merge = $app->make(\App\Services\CategoryMergeService::class);
} catch (Throwable $e) {
    exit('CategoryMergeService error: '.$e->getMessage()."\n</pre>");
}

try {
    $preview = $merge->preview();
} catch (Throwable $e) {
    exit('Preview error: '.$e->getMessage()."\nRun {$base}&migrate=1 first.\n</pre>");
}

echo 'Products: '.number_format($preview['total_products'])."\n";
echo 'Legacy categories: '.number_format($preview['legacy_products'])."\n";
echo 'Migration tables: '.($preview['migration_tables_ready'] ? 'ready' : 'MISSING — run migrate=1')."\n\n";

if (isset($_GET['dry-run'])) {
    echo "DRY RUN — no changes.\n\n";
    foreach ($preview['reorganization']['sample_moves'] ?? [] as $move) {
        echo "  {$move['count']}× {$move['from']} → {$move['to']}\n";
    }
    echo "\nStart remap:\n{$base}&run=1&phase=remap&offset=0&batch=".DEFAULT_BATCH."\n</pre>";
    exit;
}

if (! $preview['migration_tables_ready'] && isset($_GET['run'])) {
    exit("Run migrations first:\n{$base}&migrate=1\n</pre>");
}

$phase = (string) ($_GET['phase'] ?? 'remap');
$offset = max(0, (int) ($_GET['offset'] ?? 0));
$batch = max(25, (int) ($_GET['batch'] ?? DEFAULT_BATCH));

try {
    if ($phase === 'finalize') {
        $result = $merge->finalize();
        echo "✓ Redirects: {$result['finalize']['redirects']}\n";
        echo "✓ Deactivated: {$result['finalize']['deactivated']}\n";
        echo "✓ Assigned: {$result['assign']['categorized']}\n";
        echo "✓ Legacy left: {$result['legacy_products_remaining']}\n";
    } elseif ($phase === 'assign') {
        $result = $merge->assignBatch($offset, $batch);
        echo "Processed: {$result['assign']['processed']}, assigned: {$result['assign']['categorized']}\n";
        if ($result['has_more']) {
            $next = $base.'&run=1&phase=assign&offset='.$result['next_offset'].'&batch='.$batch;
            echo "\nContinue:\n{$next}\n";
            echo '<meta http-equiv="refresh" content="2;url='.$next.'">';
        }
    } else {
        $result = $merge->mergeBatch($offset, $batch, backupOnFirst: $offset === 0);
        $reorg = $result['reorganize'];
        echo "Batch: {$reorg['processed']}/{$reorg['total']}, moved: {$reorg['moved']}\n";
        if ($reorg['has_more']) {
            $next = $base.'&run=1&phase=remap&offset='.$reorg['next_offset'].'&batch='.$batch;
            echo "\nContinue:\n{$next}\n";
            echo '<meta http-equiv="refresh" content="2;url='.$next.'">';
        } else {
            echo "\nNext: {$base}&run=1&phase=assign&offset=0&batch={$batch}\n";
        }
    }
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n";
}

echo "\nDELETE public_html/uf-merge.php now.\n</pre>";
