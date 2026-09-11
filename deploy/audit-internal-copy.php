<?php

/**
 * Report-only scan of customer-facing product copy for internal pricing language.
 * Does not write to the database.
 *
 * 1. Git pull latest code
 * 2. Copy this file to public_html/audit-internal-copy.php and set AUDIT_KEY
 * 3. Visit: https://www.urbanfocus.co.za/audit-internal-copy.php?key=YOUR_SECRET
 * 4. DELETE public_html/audit-internal-copy.php
 */

declare(strict_types=1);

use App\Services\InternalPricingCopySanitizer;
use Illuminate\Contracts\Console\Kernel;

const AUDIT_KEY = 'CHANGE-ME-audit-internal-copy-secret';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');
header('Content-Type: text/plain; charset=utf-8');

if (str_contains(AUDIT_KEY, 'CHANGE-ME') || strlen(AUDIT_KEY) < 16) {
    http_response_code(403);
    exit("Refusing to run: set a strong unique AUDIT_KEY (16+ chars, no CHANGE-ME).\n");
}

if (! hash_equals(AUDIT_KEY, (string) ($_GET['key'] ?? ''))) {
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

$sanitizer = $app->make(InternalPricingCopySanitizer::class);

echo "Urban Focus — audit internal pricing copy (READ ONLY)\n";
echo 'Laravel: '.$laravelRoot."\n\n";

$u7 = $sanitizer->inspectSku('U7-ENTERPRISE');
echo "=== U7-ENTERPRISE ===\n";
if ($u7 === null) {
    echo "SKU not found.\n\n";
} else {
    echo 'Name: '.$u7['name']."\n";
    echo 'URL: '.$u7['url']."\n";
    echo 'Needs scrub: '.($u7['needs_scrub'] ? 'YES' : 'no')."\n";
    echo 'Phrases: '.($u7['phrases'] === [] ? '(none)' : implode(' | ', $u7['phrases']))."\n";
    echo 'Excerpt: '.$u7['excerpt']."\n";
    echo 'Clean preview: '.$u7['clean_preview']."\n\n";
}

$csv = $laravelRoot.'/storage/app/backups/internal-copy-audit-'.date('Y-m-d-His').'.csv';
$count = $sanitizer->writeAuditCsv($csv);
$unique = [];
foreach ($sanitizer->auditCatalog() as $row) {
    $unique[$row['id']] = true;
}

echo "Catalogue findings: {$count} phrase match(es) across ".count($unique)." product(s).\n";
echo 'CSV: '.$csv."\n";
echo "Database was not changed.\n";
echo "\nDELETE public_html/audit-internal-copy.php now.\n";
