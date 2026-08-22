<?php

/**
 * Add curated target-range products (cPanel — no Terminal / no artisan)
 *
 * Skips any SKU or model already on the store. Safe to re-run.
 *
 * 1. Git pull latest master (or copy the new files into urbanfocus/)
 * 2. Copy this file to public_html/sync-target-range.php
 * 3. Edit SYNC_KEY below to a long secret (16+ characters)
 * 4. Preview: https://www.urbanfocus.co.za/sync-target-range.php?key=YOUR_SECRET&preview=1
 * 5. Run:    https://www.urbanfocus.co.za/sync-target-range.php?key=YOUR_SECRET
 * 6. DELETE public_html/sync-target-range.php when done
 */

declare(strict_types=1);

const SYNC_KEY = 'CHANGE-ME-sync-target-range-secret';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');

if (str_contains(SYNC_KEY, 'CHANGE-ME') || strlen(SYNC_KEY) < 16) {
    http_response_code(403);
    exit('Refusing to run: edit this file and set a strong, unique secret key (16+ chars, no "CHANGE-ME") before use.');
}

if (! hash_equals(SYNC_KEY, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden');
}

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

header('Content-Type: text/plain; charset=utf-8');
@set_time_limit(0);
@ini_set('memory_limit', '512M');

if ($laravelRoot === null) {
    exit("Laravel root not found. Expected urbanfocus/ next to public_html.\n");
}

echo "Urban Focus target-range sync\n";
echo "Laravel: {$laravelRoot}\n";
echo str_repeat('-', 40)."\n";

require $laravelRoot.'/vendor/autoload.php';
$app = require_once $laravelRoot.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

if (! class_exists(App\Services\TargetRangeCatalogService::class)) {
    exit("TargetRangeCatalogService is missing. Pull latest master first.\n");
}

$catalog = $app->make(App\Services\TargetRangeCatalogService::class);
$dryRun = isset($_GET['preview']);

try {
    $result = $catalog->sync($dryRun);
} catch (Throwable $e) {
    exit('Failed: '.$e->getMessage()."\n");
}

echo ($dryRun ? "PREVIEW (no changes made)\n" : "APPLIED\n");
echo "Created / would create: {$result['created']}\n";
echo "Already on store: {$result['skipped']}\n";
echo "Errors: {$result['errors']}\n\n";

foreach ($result['samples'] as $sample) {
    echo sprintf(
        "%s  %s  %s%s\n",
        str_pad(strtoupper((string) ($sample['action'] ?? '')), 12),
        $sample['sku'] ?? '—',
        $sample['name'] ?? '',
        isset($sample['reason']) ? '  — '.$sample['reason'] : (isset($sample['price']) ? '  R'.number_format((float) $sample['price'], 2) : '')
    );
}

echo "\nDELETE public_html/sync-target-range.php now.\n";
