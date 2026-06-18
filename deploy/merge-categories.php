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
 *    c) merge-categories.php?key=YOUR_SECRET&run=1&batch=200&offset=0
 *    d) Follow "Continue" links until complete, then run finalize=1
 * 5. DELETE public_html/merge-categories.php after success
 */

declare(strict_types=1);

const MERGE_KEY = 'CHANGE-ME-merge-categories-secret';
const DEFAULT_BATCH = 200;

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

$stream = static function (string $message): void {
    echo $message;
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();
};

$stream("=== Merge products into canonical categories ===\n");
$stream('Time: '.date('Y-m-d H:i:s')."\n");
$stream("Laravel root: {$laravelRoot}\n");
$stream('Folder exists: '.(is_dir($laravelRoot) ? 'yes' : 'NO')."\n");
$stream('vendor exists: '.(is_dir($laravelRoot.'/vendor') ? 'yes' : 'NO')."\n");
$stream('PHP version: '.PHP_VERSION."\n\n");

if (! is_dir($laravelRoot)) {
    exit("STOP: urbanfocus folder not found at {$laravelRoot}\nExpected: /home/youruser/urbanfocus next to public_html\n</pre>");
}

if (! is_dir($laravelRoot.'/vendor')) {
    exit("STOP: urbanfocus/vendor missing — run Composer install or upload vendor/ first.\n</pre>");
}

// Help page only — no Laravel boot (avoids 500 when you just need the URL list)
if (! isset($_GET['migrate']) && ! isset($_GET['dry-run']) && ! isset($_GET['run'])) {
    $stream("Run these URLs in order:\n\n");
    $stream("1. Migrations (first time only):\n   {$base}&migrate=1\n\n");
    $stream("2. Preview (no changes):\n   {$base}&dry-run=1\n\n");
    $stream("3. Batch remap (start here):\n   {$base}&run=1&phase=remap&offset=0&batch=".DEFAULT_BATCH."\n\n");
    $stream("4. When remap finishes, finalize:\n   {$base}&run=1&phase=finalize\n\n");
    $stream("Tip: back up your database in phpMyAdmin before step 3.\n");
    $stream("If you see HTTP 500 on step 2+, run fix-500.php first or read urbanfocus/storage/logs/laravel.log\n");
    $stream("DELETE this file from public_html when finished.\n</pre>");
    exit;
}

try {
    require $laravelRoot.'/vendor/autoload.php';
    $app = require_once $laravelRoot.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
} catch (Throwable $e) {
    $stream("\nLARAVEL BOOT FAILED:\n");
    $stream($e->getMessage()."\n");
    $stream($e->getFile().':'.$e->getLine()."\n\n");
    $logFile = $laravelRoot.'/storage/logs/laravel.log';
    if (file_exists($logFile)) {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES) ?: [];
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            if (str_contains($lines[$i], '.ERROR:') || str_contains($lines[$i], 'local.ERROR')) {
                $stream("Last laravel.log error:\n".implode("\n", array_slice($lines, $i, min(12, count($lines) - $i)))."\n");
                break;
            }
        }
    }
    $stream("\nTry fix-500.php in public_html, or clear bootstrap/cache/*.php in File Manager.\n</pre>");
    exit;
}

@set_time_limit(300);
@ini_set('memory_limit', '512M');

/** @var \App\Services\CategoryMergeService $merge */
$merge = $app->make(\App\Services\CategoryMergeService::class);

if (isset($_GET['migrate'])) {
    $stream("=== Running database migrations ===\n");
    try {
        $exitCode = $kernel->call('migrate', ['--force' => true]);
        $out = trim($kernel->output());
        if ($out !== '') {
            $stream($out."\n");
        }
        $stream($exitCode === 0 ? "✓ Migrations complete.\n\n" : "✗ Migration exit code: {$exitCode}\n\n");
    } catch (Throwable $e) {
        $stream('Migration ERROR: '.$e->getMessage()."\n\n");
    }
}

$preview = $merge->preview();
$stream('Catalog: '.number_format($preview['total_products'])." products, ".number_format($preview['categorized_products'])." categorised\n");
$stream('On legacy categories: '.number_format($preview['legacy_products'])."\n");
$stream('Reorg would move: '.number_format($preview['reorganization']['products_to_move'])."\n");
$stream('Migration tables ready: '.($preview['migration_tables_ready'] ? 'yes' : 'NO — run migrate=1 first')."\n\n");

if (! $preview['migration_tables_ready'] && isset($_GET['run'])) {
    $stream("STOP: Run migrations first:\n{$base}&migrate=1\n</pre>");
    exit;
}

if (isset($_GET['dry-run'])) {
    $reorg = $preview['reorganization'];
    $stream("DRY RUN — no changes made\n\n");

    if ($reorg['sample_moves'] !== []) {
        $stream("Sample moves:\n");
        foreach ($reorg['sample_moves'] as $move) {
            $stream("  {$move['count']}× {$move['from']} → {$move['to']}\n");
        }
        $stream("\n");
    } elseif ($preview['legacy_products'] === 0) {
        $stream("Nothing to merge — products already use canonical categories.\n");
        $stream("If shop pages are still empty, run assign only:\n");
        $stream("  {$base}&run=1&phase=assign&offset=0&batch=".DEFAULT_BATCH."\n\n");
    }

    $stream("Start batch remap:\n{$base}&run=1&phase=remap&offset=0&batch=".DEFAULT_BATCH."\n</pre>");
    exit;
}

$phase = (string) ($_GET['phase'] ?? 'remap');
$offset = max(0, (int) ($_GET['offset'] ?? 0));
$batch = max(25, (int) ($_GET['batch'] ?? DEFAULT_BATCH));
$started = microtime(true);

try {
    if ($phase === 'finalize') {
        $stream("Phase: FINALIZE (redirects, deactivate orphans, heuristic sweep)\n\n");
        $result = $merge->finalize();

        $stream('✓ Redirects created: '.$result['finalize']['redirects']."\n");
        $stream('✓ Legacy categories deactivated: '.$result['finalize']['deactivated']."\n");
        $stream('✓ Heuristic assigned: '.$result['assign']['categorized']."\n");
        $stream('✓ Products still on legacy categories: '.$result['legacy_products_remaining']."\n\n");

        $stream("Clearing caches...\n");
        foreach (['config:clear', 'route:clear', 'view:clear', 'cache:clear'] as $cmd) {
            $kernel->call($cmd);
            $out = trim($kernel->output());
            if ($out !== '') {
                $stream($out."\n");
            }
        }

        $stream("\n✓ Category merge complete in ".number_format(microtime(true) - $started, 2)."s\n");
        $stream("Check /category/computing-office/laptops and Admin → Categories.\n");
    } elseif ($phase === 'assign') {
        $stream("Phase: ASSIGN heuristics — offset {$offset}, batch {$batch}\n\n");
        $result = $merge->assignBatch($offset, $batch);

        $stream('✓ Processed: '.$result['assign']['processed']."\n");
        $stream('✓ Assigned: '.$result['assign']['categorized']."\n");
        $stream('✓ Skipped: '.$result['assign']['skipped']."\n");
        $stream('✓ Legacy products remaining: '.$result['legacy_products_remaining']."\n\n");

        if ($result['has_more']) {
            $next = $base.'&run=1&phase=assign&offset='.$result['next_offset'].'&batch='.$batch;
            $stream("Continue assign batch:\n{$next}\n\n");
            $stream('<meta http-equiv="refresh" content="2;url='.$next.'">Auto-continuing in 2s...</pre>');
            exit;
        }

        $stream("Assign complete. Finalize:\n{$base}&run=1&phase=finalize\n");
    } else {
        $stream("Phase: REMAP legacy categories — offset {$offset}, batch {$batch}\n\n");
        $result = $merge->mergeBatch($offset, $batch, backupOnFirst: $offset === 0);
        $reorg = $result['reorganize'];

        $stream('✓ Processed: '.$reorg['processed'].' / '.number_format($reorg['total'])."\n");
        $stream('✓ Remapped this batch: '.$reorg['moved']."\n");
        $stream('✓ Legacy products remaining: '.$result['legacy_products_remaining']."\n");
        $stream('Elapsed: '.number_format(microtime(true) - $started, 2)."s\n\n");

        if ($reorg['has_more']) {
            $next = $base.'&run=1&phase=remap&offset='.$reorg['next_offset'].'&batch='.$batch;
            $stream("Continue remap batch:\n{$next}\n\n");
            $stream('<meta http-equiv="refresh" content="2;url='.$next.'">Auto-continuing in 2s...</pre>');
            exit;
        }

        $stream("Remap complete. Run heuristic assign:\n{$base}&run=1&phase=assign&offset=0&batch={$batch}\n\n");
        $stream("Or skip to finalize:\n{$base}&run=1&phase=finalize\n");
    }
} catch (Throwable $e) {
    $stream("\nERROR: ".$e->getMessage()."\n");
    $stream($e->getFile().':'.$e->getLine()."\n");

    if (str_contains($e->getMessage(), 'category_migration') || str_contains($e->getMessage(), 'category_slug_redirects')) {
        $stream("\nMigration tables may be missing. Run:\n{$base}&migrate=1\n");
    }
}

$stream("\nDELETE public_html/merge-categories.php now.\n</pre>";
