<?php

/**
 * Apply markup + price rounding to all products (cPanel)
 *
 * Uses PRICE_MARKUP_PERCENT and PRICE_ROUND_TO from urbanfocus/.env
 * Default: 40% markup, round UP to next R50 (cost under R20: markup only)
 *
 * 1. Set in .env: PRICE_MARKUP_PERCENT=40, PRICE_ROUND_TO=50, PRICE_ROUND_MODE=up, PRICE_LOW_COST_THRESHOLD=20
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
$roundTo = config('pricing.round_to', 50);
$mode = config('pricing.round_mode', 'up');
$threshold = config('pricing.low_cost_threshold', 20);

echo "Apply pricing: {$markup}% markup";
if ($threshold > 0) {
    echo ", cost under R{$threshold}: markup only, else round {$mode} to R{$roundTo}";
} else {
    echo ", round {$mode} to R{$roundTo}";
}
echo "\n";
echo str_repeat('-', 40)."\n";

$pricing = $app->make(App\Services\ProductPricingService::class);
$result = $pricing->applyToAllProducts();

echo "Updated: {$result['updated']}\n";
echo "Skipped (no cost/price): {$result['skipped']}\n";
echo "\nExamples (cost → retail):\n";
echo "  R4 → R".number_format($pricing->retailPrice(4), 2)." (under R{$threshold})\n";
echo "  R100 → R".number_format($pricing->retailPrice(100), 0)."\n";
echo "  R250 → R".number_format($pricing->retailPrice(250), 0)."\n";
echo "  R500 → R".number_format($pricing->retailPrice(500), 0)."\n";
echo "\nDELETE public_html/apply-markup.php now.\n";
