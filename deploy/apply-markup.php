<?php

/**
 * Apply markup + price rounding to all products (cPanel)
 *
 * Uses competitive category/brand markups from config/pricing.php
 * Laptops and Dell/HP/Lenovo (R4000+): 8%. Networking/CCTV: 12%. Fallback: PRICE_MARKUP_PERCENT.
 *
 * 1. Set in .env: PRICE_MARKUP_PERCENT=40, PRICE_ROUND_TO=50, PRICE_ROUND_MODE=up, PRICE_LOW_COST_THRESHOLD=20
 * 2. Copy to public_html/apply-markup.php and set APPLY_KEY
 * 3. Visit: https://www.urbanfocus.co.za/apply-markup.php?key=YOUR_SECRET
 * 4. DELETE the file when done — safe to re-run (uses stored cost_price when set)
 */

declare(strict_types=1);

const APPLY_KEY = 'CHANGE-ME-apply-markup-secret';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');

if (str_contains(APPLY_KEY, 'CHANGE-ME') || strlen(APPLY_KEY) < 16) {
    http_response_code(403);
    exit('Refusing to run: edit this file and set a strong, unique secret key (16+ chars, no "CHANGE-ME") before use.');
}

if (! hash_equals(APPLY_KEY, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden');
}

$laravelRoot = dirname(__DIR__).'/urbanfocus';

header('Content-Type: text/plain; charset=utf-8');

require $laravelRoot.'/vendor/autoload.php';
$app = require_once $laravelRoot.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$pricing = $app->make(App\Services\ProductPricingService::class);
$roundTo = config('pricing.round_to', 50);
$mode = config('pricing.round_mode', 'up');
$threshold = config('pricing.low_cost_threshold', 20);

echo "Apply competitive pricing (laptops 8%, networking/CCTV 12%, fallback ".config('pricing.markup_percent')."%)\n";
echo "Round {$mode} to R{$roundTo}; cost under R{$threshold}: markup only\n";
echo str_repeat('-', 40)."\n";

$result = $pricing->applyToAllProducts();

echo "Updated: {$result['updated']}\n";
echo "Prices reduced: {$result['reduced']}\n";
echo "Unchanged: {$result['unchanged']}\n";
echo "Skipped (no cost/price): {$result['skipped']}\n";
echo "\nExamples:\n";
echo "  Accessory R4 → R".number_format($pricing->retailPrice(4), 2)."\n";
echo "  Generic R100 → R".number_format($pricing->retailPrice(100), 0)."\n";
echo "  Dell laptop R17821 → R".number_format($pricing->retailPrice(17821, null, ['name' => 'Dell Pro 15 Essential', 'brand' => 'Dell']), 0)."\n";
echo "\nDELETE public_html/apply-markup.php now.\n";
