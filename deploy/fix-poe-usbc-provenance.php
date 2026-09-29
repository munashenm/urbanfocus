<?php

/**
 * One-off fix for product 10873 / SKU POE-USBC (Scoop → PROCET provenance).
 *
 * 1. Git pull latest code and run migrations
 * 2. Copy this file to public_html/fix-poe-usbc-provenance.php and set FIX_KEY
 * 3. Preview: https://www.urbanfocus.co.za/fix-poe-usbc-provenance.php?key=YOUR_SECRET&dry_run=1
 * 4. Run:    https://www.urbanfocus.co.za/fix-poe-usbc-provenance.php?key=YOUR_SECRET
 * 5. DELETE public_html/fix-poe-usbc-provenance.php when done
 *
 * Does not change the R650 selling price. Does not touch other products.
 */

declare(strict_types=1);

const FIX_KEY = 'CHANGE-ME-fix-poe-usbc-secret';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');

if (str_contains(FIX_KEY, 'CHANGE-ME') || strlen(FIX_KEY) < 16) {
    http_response_code(403);
    exit('Refusing to run: edit this file and set a strong, unique secret key (16+ chars, no "CHANGE-ME") before use.');
}

if (! hash_equals(FIX_KEY, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden');
}

$laravelRoot = dirname(__DIR__).'/urbanfocus';

header('Content-Type: text/plain; charset=utf-8');

require $laravelRoot.'/vendor/autoload.php';
$app = require_once $laravelRoot.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$dryRun = isset($_GET['dry_run']) || isset($_GET['preview']);
$fixer = $app->make(App\Services\FixPoeUsbcProvenanceService::class);
$result = $fixer->run($dryRun);

echo "Urban Focus POE-USBC provenance fix\n";
echo str_repeat('-', 40)."\n";
echo ($dryRun ? "DRY RUN\n" : "APPLY\n");
foreach ($result as $key => $value) {
    if (is_bool($value)) {
        $value = $value ? 'yes' : 'no';
    }
    echo $key.': '.((string) $value)."\n";
}
echo "\nDELETE public_html/fix-poe-usbc-provenance.php when finished.\n";
