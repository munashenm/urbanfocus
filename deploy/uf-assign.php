<?php

/**
 * Assign products to categories — short filename for cPanel (use if merge scripts 500).
 *
 * 1. Copy urbanfocus/deploy/uf-assign.php → public_html/uf-assign.php
 * 2. Set UF_ASSIGN_KEY below (16+ chars)
 * 3. Visit: https://www.urbanfocus.co.za/uf-assign.php?key=YOUR_SECRET
 * 4. DELETE when finished
 */

declare(strict_types=1);

const UF_ASSIGN_KEY = 'CHANGE-ME-uf-assign-secret';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');
header('Content-Type: text/html; charset=utf-8');

if (str_contains(UF_ASSIGN_KEY, 'CHANGE-ME') || strlen(UF_ASSIGN_KEY) < 16) {
    http_response_code(403);
    exit('Edit UF_ASSIGN_KEY in this file (16+ chars, no CHANGE-ME).');
}

if (! hash_equals(UF_ASSIGN_KEY, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden — key mismatch.');
}

$laravelRoot = dirname(__DIR__).'/urbanfocus';
$host = $_SERVER['HTTP_HOST'] ?? 'www.urbanfocus.co.za';
$base = 'https://'.$host.'/uf-assign.php?key='.urlencode((string) $_GET['key']);

echo '<pre style="font:14px/1.5 monospace;white-space:pre-wrap">';
echo "=== Assign products to categories ===\n";
echo 'Time: '.date('Y-m-d H:i:s')."\n";
echo "Laravel root: {$laravelRoot}\n";
echo 'PHP: '.PHP_VERSION."\n";
echo 'vendor: '.(is_dir($laravelRoot.'/vendor') ? 'yes' : 'NO')."\n\n";

if (! is_dir($laravelRoot.'/vendor')) {
    exit("STOP: urbanfocus/vendor missing.\n</pre>");
}

if (! isset($_GET['dry-run']) && ! isset($_GET['run'])) {
    echo "Run in order:\n\n";
    echo "1. Preview:\n   {$base}&dry-run=1\n\n";
    echo "2. Test 25 products:\n   {$base}&run=1&limit=25\n\n";
    echo "3. Assign all:\n   {$base}&run=1\n\n";
    echo "Then check /category/computing-office/laptops\n";
    echo "DELETE this file when done.\n</pre>";
    exit;
}

try {
    require $laravelRoot.'/vendor/autoload.php';
    $app = require_once $laravelRoot.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    $seo = $app->make(\App\Services\ProductSeoService::class);
} catch (Throwable $e) {
    exit('Boot failed: '.$e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n</pre>");
}

@set_time_limit(0);
@ini_set('memory_limit', '512M');

$dryRun = isset($_GET['dry-run']);
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : null;

echo $dryRun ? "Mode: DRY RUN\n\n" : "Mode: LIVE\n\n";

try {
    $stats = $seo->assignProductCategories($dryRun, $limit);
    echo "Processed: {$stats['processed']}\n";
    echo "Assigned:  {$stats['categorized']}\n";
    echo "Skipped:   {$stats['skipped']}\n\n";
    if ($stats['samples'] !== []) {
        echo "Samples:\n";
        foreach ($stats['samples'] as $line) {
            echo "  • {$line}\n";
        }
        echo "\n";
    }
    if ($dryRun) {
        echo "Next: {$base}&run=1&limit=25\n";
    } else {
        echo "✓ Done. Browse /category/computing-office/laptops\n";
    }
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n";
}

echo "\nDELETE public_html/uf-assign.php now.\n</pre>";
