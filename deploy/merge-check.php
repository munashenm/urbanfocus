<?php

/**
 * Minimal merge diagnostic — no Laravel. Use when merge-categories.php returns HTTP 500.
 *
 * 1. Copy to public_html/merge-check.php
 * 2. Set CHECK_KEY (16+ chars)
 * 3. Visit: https://www.urbanfocus.co.za/merge-check.php?key=YOUR_SECRET
 * 4. DELETE when done
 */

declare(strict_types=1);

const CHECK_KEY = 'CHANGE-ME-merge-check-secret';

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

if (str_contains(CHECK_KEY, 'CHANGE-ME') || strlen(CHECK_KEY) < 16) {
    http_response_code(403);
    exit("Edit CHECK_KEY in this file first (16+ chars).\n");
}

if (! hash_equals(CHECK_KEY, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit("Forbidden — key mismatch.\n");
}

$laravelRoot = dirname(__DIR__).'/urbanfocus';
$mergeFile = __DIR__.'/merge-categories.php';

echo "=== Urban Focus merge check ===\n";
echo 'Time: '.date('Y-m-d H:i:s')."\n";
echo 'PHP: '.PHP_VERSION."\n";
echo "Laravel root: {$laravelRoot}\n";
echo 'urbanfocus exists: '.(is_dir($laravelRoot) ? 'yes' : 'NO')."\n";
echo 'vendor exists: '.(is_dir($laravelRoot.'/vendor') ? 'yes' : 'NO')."\n";
echo '.env exists: '.(file_exists($laravelRoot.'/.env') ? 'yes' : 'NO')."\n";
echo "merge-categories.php in public_html: ".(file_exists($mergeFile) ? 'yes' : 'NO')."\n\n";

if (file_exists($mergeFile)) {
    $src = file_get_contents($mergeFile) ?: '';
    echo "merge-categories.php size: ".strlen($src)." bytes\n";
    echo 'Has help-before-boot fix: '.(str_contains($src, 'Help page only') ? 'YES (new)' : 'NO (old — re-copy from deploy/)')."\n";
    echo 'MERGE_KEY still CHANGE-ME: '.(str_contains($src, 'CHANGE-ME-merge-categories') ? 'YES — edit line 20!' : 'no')."\n\n";
}

if (! is_dir($laravelRoot.'/vendor')) {
    exit("STOP: vendor/ missing.\n");
}

echo "Booting Laravel...\n";
try {
    require $laravelRoot.'/vendor/autoload.php';
    $app = require_once $laravelRoot.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    echo "✓ Laravel boot OK\n\n";

    $merge = $app->make(\App\Services\CategoryMergeService::class);
    echo "✓ CategoryMergeService loaded\n\n";

    echo "Running preview (read-only)...\n";
    $preview = $merge->preview();
    echo 'Products: '.number_format($preview['total_products'])."\n";
    echo 'On legacy categories: '.number_format($preview['legacy_products'])."\n";
    echo 'Migration tables ready: '.($preview['migration_tables_ready'] ? 'yes' : 'NO — run migrate=1 first')."\n";
    echo "\nIf this works, merge-categories.php on the server is broken or outdated.\n";
    echo "Re-copy urbanfocus/deploy/merge-categories.php → public_html/merge-categories.php\n";
} catch (Throwable $e) {
    echo "\nERROR: ".$e->getMessage()."\n";
    echo $e->getFile().':'.$e->getLine()."\n";
}

echo "\nDELETE public_html/merge-check.php now.\n";
