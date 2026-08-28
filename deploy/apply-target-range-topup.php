<?php

/**
 * Apply 15% top-up to new catalogue products (cPanel File Manager — no git pull needed)
 *
 * Git pull in cPanel often updates a different folder than the live Laravel app.
 * This script changes prices in the database directly.
 *
 * 1. Download this file (do not rely on git pull):
 *    https://raw.githubusercontent.com/munashenm/urbanfocus/master/deploy/apply-target-range-topup.php
 * 2. Upload it to public_html/apply-target-range-topup.php
 * 3. Edit APPLY_KEY below to a long secret (16+ characters)
 * 4. Preview: https://www.urbanfocus.co.za/apply-target-range-topup.php?key=YOUR_SECRET&preview=1
 * 5. Apply:   https://www.urbanfocus.co.za/apply-target-range-topup.php?key=YOUR_SECRET
 * 6. DELETE public_html/apply-target-range-topup.php when done
 */

declare(strict_types=1);

const APPLY_KEY = 'CHANGE-ME-target-range-topup-secret';
const TOPUP_PERCENT = 15.0;
const ROUND_TO = 50;

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');
header('Content-Type: text/plain; charset=utf-8');

if (str_contains(APPLY_KEY, 'CHANGE-ME') || strlen(APPLY_KEY) < 16) {
    http_response_code(403);
    exit("Refusing to run: edit this file and set a strong, unique secret key (16+ chars, no \"CHANGE-ME\") before use.\n");
}

if (! hash_equals(APPLY_KEY, (string) ($_GET['key'] ?? ''))) {
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

echo "Urban Focus target-range 10% top-up\n";
echo "Laravel: {$laravelRoot}\n";
echo str_repeat('-', 50)."\n";

echo git_report($laravelRoot);
echo 'TargetRangeCatalogService.php has 10% code: '.(service_has_topup($laravelRoot) ? 'YES' : 'NO — using this script instead')."\n";
echo str_repeat('-', 50)."\n";

require $laravelRoot.'/vendor/autoload.php';
$app = require_once $laravelRoot.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$jsonPath = $laravelRoot.'/database/data/target-range-products.json';
if (! is_readable($jsonPath)) {
    exit("Catalog JSON missing: {$jsonPath}\nUpload database/data/target-range-products.json into urbanfocus/ first.\n");
}

$items = json_decode((string) file_get_contents($jsonPath), true);
if (! is_array($items)) {
    exit("Catalog JSON is invalid.\n");
}

$dryRun = isset($_GET['preview']);
$updated = 0;
$already = 0;
$missing = 0;
$skipped = 0;

echo $dryRun ? "PREVIEW (no prices written)\n\n" : "APPLYING\n\n";

foreach ($items as $item) {
    if (! is_array($item) || empty($item['sku']) || empty($item['street_price'])) {
        continue;
    }

    $sku = (string) $item['sku'];
    $street = (float) $item['street_price'];
    $retail = round_up($street * (1 + TOPUP_PERCENT / 100), ROUND_TO);

    $product = App\Models\Product::withTrashed()->where('sku', $sku)->first();
    if (! $product) {
        $missing++;
        echo "MISSING   {$sku}  {$item['name']}  — not on store yet\n";
        continue;
    }

    $current = (float) $product->price;
    if (abs($current - $retail) < 0.01) {
        $already++;
        echo "OK        {$sku}  already R".number_format($retail, 0)."\n";
        continue;
    }

    if (! is_ours($product, $item, $street, $retail)) {
        $skipped++;
        echo "SKIP      {$sku}  store listing at R".number_format($current, 0)." (not one we added)\n";
        continue;
    }

    if (! $dryRun) {
        $specs = is_array($product->specifications) ? $product->specifications : [];
        $specs['Urban Focus range'] = 'Target catalogue';
        $product->update([
            'price' => $retail,
            'cost_price' => implied_cost($app, $item, $retail),
            'specifications' => $specs,
        ]);
    }

    $updated++;
    echo ($dryRun ? 'WOULD     ' : 'UPDATED   ')."{$sku}  R".number_format($current, 0).' → R'.number_format($retail, 0)."  {$item['name']}\n";
}

if (! $dryRun && $updated > 0) {
    try {
        Illuminate\Support\Facades\Cache::forget('home.product_rows_v1');
        Illuminate\Support\Facades\Cache::forget('home.product_rows_v2');
    } catch (Throwable) {
    }
}

echo "\n".str_repeat('-', 50)."\n";
echo ($dryRun ? 'Would update' : 'Updated').": {$updated}\n";
echo "Already at new price: {$already}\n";
echo "Skipped (existing store SKUs): {$skipped}\n";
echo "Not on store yet: {$missing}\n";
echo "\nDELETE public_html/apply-target-range-topup.php now.\n";

function round_up(float $price, int $step): float
{
    if ($price <= 0) {
        return 0.0;
    }

    return (float) max($step, (int) (ceil($price / $step) * $step));
}

function is_ours(App\Models\Product $product, array $item, float $street, float $retail): bool
{
    $specs = is_array($product->specifications) ? $product->specifications : [];
    if (($specs['Urban Focus range'] ?? '') === 'Target catalogue') {
        return true;
    }

    $current = (float) $product->price;

    return abs($current - $street) < 0.51 || abs($current - $retail) < 0.51;
}

function implied_cost($app, array $item, float $retail): float
{
    try {
        $pricing = $app->make(App\Services\ProductPricingService::class);
        $markup = $pricing->markupPercentFor($retail, null, [
            'name' => (string) ($item['name'] ?? ''),
            'brand' => (string) ($item['brand'] ?? ''),
            'category_path' => (string) ($item['category_path'] ?? ''),
        ]);
    } catch (Throwable) {
        $markup = 12.0;
    }

    $fee = 3.9;
    try {
        $fee = (float) config('pricing.payment_fee_percent', 3.9);
    } catch (Throwable) {
    }

    $divisor = (1 + ($markup / 100)) * (1 + ($fee / 100));

    return $divisor > 0 ? round($retail / $divisor, 2) : $retail;
}

function service_has_topup(string $laravelRoot): bool
{
    $path = $laravelRoot.'/app/Services/TargetRangeCatalogService.php';
    if (! is_readable($path)) {
        return false;
    }

    return str_contains((string) file_get_contents($path), 'target_range_topup_percent')
        || str_contains((string) file_get_contents($path), 'retailStreetPrice');
}

function git_report(string $laravelRoot): string
{
    $out = '';
    if (! is_dir($laravelRoot.'/.git')) {
        return "Git: no .git inside Laravel folder (cPanel Git may live elsewhere).\n";
    }

    $cmd = 'git -C '.escapeshellarg($laravelRoot);
    $head = trim((string) shell_exec($cmd.' rev-parse --short HEAD 2>/dev/null'));
    $branch = trim((string) shell_exec($cmd.' rev-parse --abbrev-ref HEAD 2>/dev/null'));
    $remote = trim((string) shell_exec($cmd.' remote get-url origin 2>/dev/null'));
    $log = trim((string) shell_exec($cmd.' log -1 --oneline 2>/dev/null'));

    $out .= "Git HEAD: ".($head !== '' ? $head : 'unknown')." on ".($branch !== '' ? $branch : 'unknown')."\n";
    $out .= "Git remote: ".($remote !== '' ? $remote : 'none')."\n";
    $out .= "Git log: ".($log !== '' ? $log : 'none')."\n";
    $out .= "Expected on GitHub master: 0f189a1  fix: load full product before applying catalogue price top-up\n";
    $expected = '0f189a160950237b015353d1e14fbd811e06e18a';
    if ($head !== '' && ! str_starts_with($expected, $head) && ! str_starts_with($head, '0f189a1')) {
        $out .= "This folder is not on the latest GitHub master — that is why git pull can say nothing (wrong folder/remote).\n";
    }

    return $out;
}
