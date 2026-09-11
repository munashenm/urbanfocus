<?php

/**
 * Remove internal pricing / margin language from customer-facing product copy.
 *
 * 1. Git pull latest code (or upload this file plus InternalPricingCopySanitizer.php)
 * 2. Copy this file to public_html/scrub-internal-copy.php and set SCRUB_KEY
 * 3. Preview: https://www.urbanfocus.co.za/scrub-internal-copy.php?key=YOUR_SECRET&preview=1
 * 4. Apply:   https://www.urbanfocus.co.za/scrub-internal-copy.php?key=YOUR_SECRET
 * 5. DELETE public_html/scrub-internal-copy.php
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

$dryRun = isset($_GET['preview']);
$sanitizer = $app->make(InternalPricingCopySanitizer::class);

echo "Urban Focus — scrub internal pricing copy\n";
echo 'Laravel: '.$laravelRoot."\n";
echo $dryRun ? "PREVIEW (no changes written)\n\n" : "APPLYING\n\n";

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

echo "\nDELETE public_html/scrub-internal-copy.php now.\n";
