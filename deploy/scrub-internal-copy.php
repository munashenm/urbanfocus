<?php

/**
 * Remove internal pricing / margin language from customer-facing product copy.
 *
 * 1. Git pull latest code
 * 2. Copy this file to public_html/scrub-internal-copy.php and set SCRUB_KEY
 * 3. Preview (default): https://www.urbanfocus.co.za/scrub-internal-copy.php?key=YOUR_SECRET
 *    or ?preview=1
 * 4. Apply:             https://www.urbanfocus.co.za/scrub-internal-copy.php?key=YOUR_SECRET&apply=1
 * 5. DELETE public_html/scrub-internal-copy.php
 *
 * Apply writes a JSON backup of affected rows to storage/app/backups/ first.
 */

declare(strict_types=1);

use App\Services\InternalPricingCopySanitizer;
use App\Services\SeoService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;

const SCRUB_KEY = 'CHANGE-ME-scrub-internal-copy-secret';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');
header('Content-Type: text/plain; charset=utf-8');

if (str_contains(SCRUB_KEY, 'CHANGE-ME') || strlen(SCRUB_KEY) < 16) {
    http_response_code(403);
    exit("Refusing to run: set a strong unique SCRUB_KEY (16+ chars, no CHANGE-ME).\n");
}

if (! hash_equals(SCRUB_KEY, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit("Forbidden\n");
}

@set_time_limit(0);
@ini_set('memory_limit', '512M');

$candidates = [
    dirname(__DIR__).'/urbanfocus',
    dirname(__DIR__),
    __DIR__,
];

$laravelRoot = null;
foreach ($candidates as $candidate) {
    if (is_file($candidate.'/bootstrap/app.php') && is_file($candidate.'/vendor/autoload.php')) {
        $laravelRoot = $candidate;
        break;
    }
}

if ($laravelRoot === null) {
    exit("Laravel root not found. Expected urbanfocus/ next to public_html.\n");
}

require $laravelRoot.'/vendor/autoload.php';
$app = require_once $laravelRoot.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$apply = isset($_GET['apply']) && (string) $_GET['apply'] === '1';
$dryRun = ! $apply;
$sanitizer = $app->make(InternalPricingCopySanitizer::class);

echo "Urban Focus — scrub internal pricing copy\n";
echo 'Laravel: '.$laravelRoot."\n";
echo $dryRun ? "PREVIEW (no changes written). Add &apply=1 to persist.\n\n" : "APPLYING\n\n";

$u7 = $sanitizer->inspectSku('U7-ENTERPRISE');
echo "=== U7-ENTERPRISE before ===\n";
if ($u7 === null) {
    echo "SKU not found.\n\n";
} else {
    echo 'Needs scrub: '.($u7['needs_scrub'] ? 'YES' : 'no')."\n";
    echo 'Phrases: '.($u7['phrases'] === [] ? '(none)' : implode(' | ', $u7['phrases']))."\n";
    echo 'Excerpt: '.$u7['excerpt']."\n\n";
}

if (! $dryRun) {
    $backup = $sanitizer->backupAffectedCopy();
    echo 'Backup: '.$backup."\n\n";
}

$stats = $sanitizer->scrubCatalog($dryRun);

foreach ($stats['samples'] as $sample) {
    echo ($dryRun ? 'WOULD     ' : 'UPDATED   ').$sample."\n";
}

echo "\nScanned: {$stats['processed']}\n";
echo ($dryRun ? 'Would update: ' : 'Updated: ').$stats['updated']."\n";

if (! $dryRun && $stats['updated'] > 0) {
    try {
        $app->make(SeoService::class)->clearCache();
        Cache::forget('home.product_rows_v1');
        Cache::forget('home.product_rows_v2');
        echo "Storefront and feed caches cleared.\n";
    } catch (Throwable $e) {
        echo 'Cache clear warning: '.$e->getMessage()."\n";
    }
}

if (! $dryRun) {
    $remaining = $sanitizer->auditCatalog();
    $after = $sanitizer->inspectSku('U7-ENTERPRISE');
    echo "\n=== U7-ENTERPRISE after ===\n";
    if ($after === null) {
        echo "SKU not found.\n";
    } else {
        echo 'Needs scrub: '.($after['needs_scrub'] ? 'YES' : 'no')."\n";
        echo 'Phrases: '.($after['phrases'] === [] ? '(none)' : implode(' | ', $after['phrases']))."\n";
        echo 'Excerpt: '.$after['excerpt']."\n";
    }
    echo "\nPost-scrub catalogue leaks: ".count($remaining)."\n";
}

echo "\nDELETE public_html/scrub-internal-copy.php now.\n";
