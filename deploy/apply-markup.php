<?php

/**
 * Apply markup + price rounding to all products (cPanel)
 *
 * Uses PRICE_MARKUP_PERCENT and PRICE_ROUND_TO from urbanfocus/.env
 * Default: 40% markup, round to nearest R100
 *
 * 1. Set in .env: PRICE_MARKUP_PERCENT=40 and PRICE_ROUND_TO=100
 * 2. Copy to public_html/apply-markup.php and set APPLY_KEY
 * 3. Visit: https://www.urbanfocus.co.za/apply-markup.php?key=YOUR_SECRET
 * 4. DELETE the file when done — safe to re-run (uses stored cost_price when set)
 */

declare(strict_types=1);

const APPLY_KEY = 'CHANGE-ME-apply-markup-secret';

if (($_GET['key'] ?? '') !== APPLY_KEY) {
    http_response_code(403);
    exit('Forbidden');
}

$laravelRoot = dirname(__DIR__).'/urbanfocus';

header('Content-Type: text/plain; charset=utf-8');

require $laravelRoot.'/vendor/autoload.php';
$app = require_once $laravelRoot.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$markup = config('pricing.markup_percent', 40);
$roundTo = config('pricing.round_to', 100);

echo "Apply pricing: {$markup}% markup, round to nearest R{$roundTo}\n";
echo str_repeat('-', 40)."\n";

$pricing = $app->make(App\Services\ProductPricingService::class);
$result = $pricing->applyToAllProducts();

echo "Updated: {$result['updated']}\n";
echo "Skipped (no cost/price): {$result['skipped']}\n";
echo "\nExample: R100 cost → R".number_format($pricing->retailPrice(100), 0)."\n";
echo "Example: R250 cost → R".number_format($pricing->retailPrice(250), 0)."\n";
echo "\nDELETE public_html/apply-markup.php now.\n";
