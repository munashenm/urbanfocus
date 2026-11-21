<?php
// Margin check for Black Friday reprices. Prints SKU, proposed price, margin %, pass/fail.
// Does not print supplier costs or names. Uses the established specials formula.
use App\Models\Product;

if ($argc < 4) {
    fwrite(STDERR, "usage: bf1121_profitcheck.php IN.csv OUT.csv SKIPS.txt\n");
    exit(1);
}
$inPath = $argv[1];
$outPath = $argv[2];
$skipPath = $argv[3];

require '/home/urbanzdc/urbanfocus/vendor/autoload.php';
$app = require '/home/urbanzdc/urbanfocus/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

function netProfitRand(float $priceIncl, float $costExclVat): float
{
    $shipping = $priceIncl > 1500 ? 201.0 : 1.0;
    return ($priceIncl / 1.15) - (0.039 * $priceIncl) - $shipping - ($costExclVat * 1.05);
}

function marginPct(float $priceIncl, float $costExclVat): float
{
    $denom = $costExclVat * 1.05;
    if ($denom <= 0) {
        return -100.0;
    }
    return netProfitRand($priceIncl, $costExclVat) / $denom * 100.0;
}

$fh = fopen($inPath, 'rb');
if ($fh === false) {
    fwrite(STDERR, "cannot read $inPath\n");
    exit(1);
}
$header = fgetcsv($fh);
$keys = array_map(fn ($c) => strtolower(trim((string) $c)), $header ?: []);
$rows = [];
while (($line = fgetcsv($fh)) !== false) {
    if ($line === [null] || $line === []) {
        continue;
    }
    $row = [];
    foreach ($keys as $i => $key) {
        $row[$key] = trim((string) ($line[$i] ?? ''));
    }
    $rows[] = $row;
}
fclose($fh);

$out = fopen($outPath, 'w');
fputcsv($out, $header ?: ['SKU', 'sale_price', 'sale_starts_at', 'sale_ends_at', 'promo_label']);
$skips = [];

foreach ($rows as $row) {
    $sku = $row['sku'] ?? '';
    $price = isset($row['sale_price']) ? (float) $row['sale_price'] : 0;
    $product = Product::query()->whereRaw('LOWER(sku) = ?', [mb_strtolower($sku)])->first();
    if (! $product) {
        $skips[] = "$sku SKIP not_found";
        echo "$sku SKIP not_found\n";
        continue;
    }
    $cost = $product->supplier_cost_price !== null && (float) $product->supplier_cost_price > 0
        ? (float) $product->supplier_cost_price
        : (float) $product->cost_price;
    if ($cost <= 0) {
        $skips[] = "$sku SKIP no_cost proposed={$price}";
        echo "$sku SKIP no_cost proposed={$price}\n";
        continue;
    }
    $pct = marginPct($price, $cost);
    $pass = $pct >= 10.0;
    echo sprintf("%s proposed=%.0f margin=%.2f%% %s\n", $sku, $price, $pct, $pass ? 'PASS' : 'SKIP below_10');
    if ($pass) {
        $lineOut = [];
        foreach ($keys as $key) {
            $lineOut[] = $row[$key] ?? '';
        }
        fputcsv($out, $lineOut);
    } else {
        $skips[] = sprintf('%s SKIP below_10 proposed=%.0f margin=%.2f%%', $sku, $price, $pct);
    }
}
fclose($out);
file_put_contents($skipPath, $skips === [] ? "none\n" : implode("\n", $skips)."\n");
