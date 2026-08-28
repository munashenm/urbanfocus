<?php

/**
 * Refresh professional SEO descriptions + competitive prices on new catalogue products.
 *
 * Use this when cPanel git pull says there is nothing to pull.
 *
 * 1. Download BOTH files:
 *    https://raw.githubusercontent.com/munashenm/urbanfocus/master/app/Services/TargetRangeListingCopy.php
 *    https://raw.githubusercontent.com/munashenm/urbanfocus/master/deploy/refresh-target-range-listings.php
 * 2. Upload TargetRangeListingCopy.php to urbanfocus/app/Services/TargetRangeListingCopy.php
 * 3. Upload this file to public_html/refresh-target-range-listings.php
 * 4. Set REFRESH_KEY below
 * 5. Preview: https://www.urbanfocus.co.za/refresh-target-range-listings.php?key=YOUR_SECRET&preview=1
 * 6. Apply:   https://www.urbanfocus.co.za/refresh-target-range-listings.php?key=YOUR_SECRET
 * 7. DELETE public_html/refresh-target-range-listings.php
 */

declare(strict_types=1);

const REFRESH_KEY = 'CHANGE-ME-refresh-listings-secret';
const TOPUP_PERCENT = 15.0;
const ROUND_TO = 50;

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');
header('Content-Type: text/plain; charset=utf-8');

if (str_contains(REFRESH_KEY, 'CHANGE-ME') || strlen(REFRESH_KEY) < 16) {
    http_response_code(403);
    exit("Refusing to run: set a strong unique REFRESH_KEY (16+ chars, no CHANGE-ME).\n");
}

if (! hash_equals(REFRESH_KEY, (string) ($_GET['key'] ?? ''))) {
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

echo "Urban Focus — refresh catalogue descriptions and prices\n";
echo "Laravel: {$laravelRoot}\n";

require $laravelRoot.'/vendor/autoload.php';

$copyFile = $laravelRoot.'/app/Services/TargetRangeListingCopy.php';
if (! class_exists(App\Services\TargetRangeListingCopy::class) && is_readable($copyFile)) {
    require_once $copyFile;
}

$app = require_once $laravelRoot.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

if (! class_exists(App\Services\TargetRangeListingCopy::class)) {
    exit("Upload app/Services/TargetRangeListingCopy.php into urbanfocus/app/Services/ first.\n");
}

$jsonPath = $laravelRoot.'/database/data/target-range-products.json';
if (! is_readable($jsonPath)) {
    exit("Catalog JSON missing: {$jsonPath}\n");
}

$items = json_decode((string) file_get_contents($jsonPath), true);
if (! is_array($items)) {
    exit("Catalog JSON is invalid.\n");
}

$copy = $app->make(App\Services\TargetRangeListingCopy::class);
$dryRun = isset($_GET['preview']);
$updated = 0;
$already = 0;
$missing = 0;
$skipped = 0;

echo $dryRun ? "PREVIEW (no changes written)\n\n" : "APPLYING\n\n";

foreach ($items as $item) {
    if (! is_array($item) || empty($item['sku'])) {
        continue;
    }

    $sku = (string) $item['sku'];
    $street = (float) ($item['street_price'] ?? 0);
    $retail = refresh_round_up($street * (1 + TOPUP_PERCENT / 100), ROUND_TO);
    $tenPercent = refresh_round_up($street * 1.10, ROUND_TO);

    $product = App\Models\Product::withTrashed()->where('sku', $sku)->first();
    if (! $product) {
        $missing++;
        echo "MISSING   {$sku}  {$item['name']}\n";
        continue;
    }

    $current = (float) $product->price;
    $specs = is_array($product->specifications) ? $product->specifications : [];
    $ours = ($specs['Urban Focus range'] ?? '') === 'Target catalogue'
        || abs($current - $street) < 0.51
        || abs($current - $retail) < 0.51
        || abs($current - $tenPercent) < 0.51;

    $html = $copy->descriptionHtml($item);
    $needsCopy = trim((string) $product->description) !== trim($html);
    $needsPrice = $ours && abs($current - $retail) >= 0.01;

    if (! $needsCopy && ! $needsPrice) {
        $already++;
        echo "OK        {$sku}  copy and R".number_format($retail, 0)." already current\n";
        continue;
    }

    if (! $dryRun) {
        $cost = $retail;
        try {
            $pricing = $app->make(App\Services\ProductPricingService::class);
            $markup = $pricing->markupPercentFor($retail, null, [
                'name' => (string) ($item['name'] ?? ''),
                'brand' => (string) ($item['brand'] ?? ''),
                'category_path' => (string) ($item['category_path'] ?? ''),
            ]);
            $fee = (float) config('pricing.payment_fee_percent', 3.9);
            $divisor = (1 + ($markup / 100)) * (1 + ($fee / 100));
            $cost = $divisor > 0 ? round($retail / $divisor, 2) : $retail;
        } catch (Throwable) {
        }

        $payload = [
            'short_description' => $copy->shortDescription($item),
            'description' => $html,
            'meta_title' => $copy->metaTitle($item),
            'meta_description' => $copy->metaDescription($item),
            'meta_keywords' => $copy->metaKeywords($item),
            'specifications' => $copy->specifications($item),
            'warranty_months' => $copy->warrantyMonths($item),
        ];
        if ($needsPrice) {
            $payload['price'] = $retail;
            $payload['cost_price'] = $cost;
        }
        $product->update($payload);
    }

    $updated++;
    $bits = [];
    if ($needsCopy) {
        $bits[] = 'SEO description';
    }
    if ($needsPrice) {
        $bits[] = 'R'.number_format($current, 0).' → R'.number_format($retail, 0);
    }
    echo ($dryRun ? 'WOULD     ' : 'UPDATED   ')."{$sku}  ".implode(' + ', $bits)."\n";
}

if (! $dryRun && $updated > 0) {
    try {
        Illuminate\Support\Facades\Cache::forget('home.product_rows_v1');
        Illuminate\Support\Facades\Cache::forget('home.product_rows_v2');
    } catch (Throwable) {
    }
}

echo "\nUpdated / would update: {$updated}\nAlready current: {$already}\nSkipped store SKUs: {$skipped}\nNot on store: {$missing}\n";
echo "\nDELETE public_html/refresh-target-range-listings.php now.\n";

function refresh_round_up(float $price, int $step): float
{
    if ($price <= 0) {
        return 0.0;
    }

    return (float) max($step, (int) (ceil($price / $step) * $step));
}
