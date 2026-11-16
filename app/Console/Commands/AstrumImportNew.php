<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\CatalogOosPolicy;
use App\Services\AstrumApi;
use App\Services\ProductImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

/**
 * Create hidden drafts for watched Astrum SKUs once national stock (JHB+CPT+DBN)
 * is greater than zero.
 *
 *   php -d allow_url_fopen=1 artisan astrum:import-new --dry-run
 *   php -d allow_url_fopen=1 artisan astrum:import-new
 *
 * Safety:
 *  - Only SKUs in storage/app/astrum/import_new_watch.txt are considered.
 *  - Copy and images come from the prepared catalog CSV (import_new_catalog.csv).
 *    Watch SKUs without a catalog row are skipped (no thin auto-copy).
 *  - In-stock new rows stay unpublished drafts (is_active = 0) for the existing
 *    publish path. Eligible zero-stock rows (name, 200+ char description, picture,
 *    cost) are published as visible out-of-stock. Customer copy never names a supplier.
 *  - Live cost is taken from GET /products (your_price_excl_vat) and national
 *    stock from GET /stock?skus=. Prices use the agreed tiered margin on cost
 *    (20% / 15% / 10% by resulting price, no FirstShop cap), never below no-loss.
 *  - Duplicates (SKU / MPN / barcode, including trashed) are skipped. Existing
 *    products are not touched and are not added to the Astrum sync list.
 *  - Refuses to run unless ProductImportService.php and SyncAstrumStock.php match
 *    a known md5. Default cap is 25 creates per run (--max).
 *  - Read-only GETs only. The API key is never logged or printed.
 *
 * After a real run, created SKUs are appended to storage/app/astrum/skus.txt and
 * a confidential report (with costs) is written under storage/app/astrum/.
 */
class AstrumImportNew extends Command
{
    protected $signature = 'astrum:import-new
        {--dry-run : Report only; change nothing}
        {--limit=0 : Process at most N watch rows that are in stock (0 = no extra limit)}
        {--max=25 : Safety cap on drafts created in one run}
        {--watch=storage/app/astrum/import_new_watch.txt : Watch list (path relative to the app root or absolute)}
        {--catalog=storage/app/astrum/import_new_catalog.csv : Prepared catalog CSV}';

    protected $description = 'Create hidden drafts for watched Astrum products when national stock is available, at a no-loss-safe price, and write a review report';

    public const SERVICE_HASHES = [
        '49b2b930f163630703520f8ad2547aa8',
        'fa966431c3cfa88103fd0ecde12a669c',
    ];

    public const SYNC_HASHES = [
        '0122e605203436b2d753d8847bcae286',
    ];

    /** Harness-only: skip the md5 gate when tests inject stub files. */
    public bool $skipHashGate = false;

    public static function norm(?string $v): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string) $v)));
    }

    public static function normBarcode(?string $v): string
    {
        $d = ltrim(preg_replace('/\D/', '', (string) $v), '0');

        return strlen($d) >= 8 ? $d : '';
    }

    public function handle(ProductImportService $import): int
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(0);

        $gate = $this->checkHashes();
        if ($gate !== null) {
            $this->error($gate);
            Log::error('astrum:import-new '.$gate);

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $cfg = config('astrum') ?: [];
        $dir = $cfg['storage_dir'] ?? storage_path('app/astrum');
        @mkdir($dir, 0775, true);
        $stamp = date('Ymd_His');

        $lock = fopen($dir.'/import_new.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            $this->warn('Another astrum:import-new is running, exiting.');

            return self::SUCCESS;
        }

        $urlFopen = filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN);
        if (! $dry && ! $urlFopen) {
            $this->error('allow_url_fopen is OFF: every image download would fail. Re-run with: php -d allow_url_fopen=1 artisan astrum:import-new ...');

            return self::FAILURE;
        }

        if (! AstrumApi::key()) {
            $this->error('ASTRUM_API_KEY is not set in .env');

            return self::FAILURE;
        }

        $api = new AstrumApi;
        $catalogLive = $api->allProducts();
        if (! ($catalogLive['ok'] ?? false)) {
            $this->error('Products feed unavailable: '.($catalogLive['error'] ?? 'unknown error'));

            return self::FAILURE;
        }
        $min = (int) ($cfg['min_products'] ?? 500);
        if (count($catalogLive['products']) < $min) {
            $this->error('Products feed has only '.count($catalogLive['products'])." products (minimum {$min}); safety stop.");

            return self::FAILURE;
        }
        $costBySku = [];
        $apiBySku = [];
        foreach ($catalogLive['products'] as $row) {
            if (! is_array($row) || empty($row['sku'])) {
                continue;
            }
            $k = strtoupper(trim((string) $row['sku']));
            $apiBySku[$k] = $row;
            $costBySku[$k] = (float) ($row['your_price_excl_vat'] ?? 0);
        }

        $watchPath = (string) $this->option('watch');
        $watchPath = str_starts_with($watchPath, '/') ? $watchPath : base_path($watchPath);
        $watch = $this->readWatch($watchPath);
        if ($watch === []) {
            $this->error('Watch list is empty or missing.');

            return self::FAILURE;
        }

        $catPath = (string) $this->option('catalog');
        $catPath = str_starts_with($catPath, '/') ? $catPath : base_path($catPath);
        $prepared = $this->readCatalog($catPath);

        $stockRes = $api->stockFor($watch);
        if (! ($stockRes['ok'] ?? false)) {
            $this->error('Stock feed unavailable: '.($stockRes['error'] ?? 'unknown error'));

            return self::FAILURE;
        }
        $stockBySku = [];
        foreach ($stockRes['by_sku'] as $sku => $row) {
            $stockBySku[strtoupper(trim((string) $sku))] = $row;
        }

        [$bySku, $byMpn, $byBarcode] = $this->indexCatalogue();
        $existingSync = $this->syncSkuSet($dir.'/skus.txt');

        $limit = max(0, (int) $this->option('limit'));
        $max = max(1, (int) $this->option('max'));
        $cfgMax = (int) ($cfg['import_new_max'] ?? 0);
        if ($cfgMax > 0) {
            $max = min($max, $cfgMax);
        }
        if ($limit > 0) {
            $max = min($max, $limit);
        }

        $counts = [
            'watch' => count($watch), 'api_products' => count($catalogLive['products']),
            'still_oos' => 0, 'missing_from_feed' => 0, 'exists' => 0,
            'skip_no_catalog' => 0, 'skip_no_image' => 0, 'skip_brand' => 0,
            'skip_no_cost' => 0, 'skip_invalid' => 0, 'eligible' => 0, 'capped' => 0,
            'allow_url_fopen' => $urlFopen ? 'on' : 'off',
        ];
        $report = [];
        $eligible = [];
        $inStockSeen = 0;

        foreach ($watch as $code) {
            $key = strtoupper(trim($code));
            $stock = $stockBySku[$key] ?? null;
            if (! $stock) {
                $counts['missing_from_feed']++;
                $report[] = ['decision' => 'MISSING_FROM_FEED', 'sku' => $code];
                continue;
            }
            $qty = max(0, (int) ($stock['qty'] ?? 0));
            if ($qty <= 0) {
                $counts['still_oos']++;
                $report[] = ['decision' => 'STILL_OOS', 'sku' => $code, 'qty' => 0, 'cost_excl' => $costBySku[$key] ?? null];
                continue;
            }
            $inStockSeen++;
            $prep = $prepared[$key] ?? null;
            if (! $prep) {
                $counts['skip_no_catalog']++;
                $report[] = ['decision' => 'SKIP_NO_CATALOG', 'sku' => $code, 'qty' => $qty, 'note' => 'in stock but no prepared catalog row (thin/held copy)'];
                continue;
            }
            $cost = $costBySku[$key] ?? 0.0;
            if ($cost <= 0) {
                $cost = (float) str_replace([',', 'R', ' '], '', (string) ($prep['cost price'] ?? 0));
            }
            $reason = $this->rejectReason($prep, $cost, $bySku, $byMpn, $byBarcode);
            if ($reason !== null) {
                $counts[$reason[0]] = ($counts[$reason[0]] ?? 0) + 1;
                $report[] = ['decision' => $reason[1], 'sku' => $code, 'note' => $reason[2], 'qty' => $qty, 'cost_excl' => $cost];
                continue;
            }
            if (count($eligible) >= $max) {
                $counts['capped']++;
                $report[] = ['decision' => 'CAPPED', 'sku' => $code, 'qty' => $qty, 'cost_excl' => $cost, 'note' => "in stock but over the --max={$max} cap this run"];
                continue;
            }

            $prop = AstrumApi::tieredPrice($cost);
            $price = (int) $prop['price'];
            if (! AstrumApi::breakEvenOk($price, $cost)) {
                $counts['skip_invalid']++;
                $report[] = ['decision' => 'SKIP_BELOW_NO_LOSS', 'sku' => $code, 'qty' => $qty, 'cost_excl' => $cost, 'note' => 'proposed price failed break-even'];
                continue;
            }

            $row = [
                'sku' => trim($prep['sku'] ?: $code),
                'name' => trim($prep['name']),
                'brand' => trim($prep['brand']),
                'categories' => trim($prep['categories']),
                'cost price' => sprintf('%.2f', $cost),
                'selling price' => (string) $price,
                'stock' => (string) $qty,
                'images' => trim($prep['images']),
                'short description' => trim($prep['short description'] ?? ''),
                'description' => trim($prep['description'] ?? ''),
                'barcode' => trim($prep['barcode'] ?? ''),
                'mpn' => trim($prep['mpn'] ?? '') ?: $code,
                'supplier sku' => trim($prep['supplier sku'] ?? '') ?: $code,
            ];
            $eligible[] = $row;
            $report[] = [
                'decision' => 'CREATE_DRAFT',
                'sku' => $row['sku'],
                'name' => $row['name'],
                'qty' => $qty,
                'cost_excl' => $cost,
                'sell_incl' => $price,
                'basis' => $prop['basis'],
                'true_profit_R' => round(AstrumApi::trueProfit($price, $cost), 2),
                'no_loss_floor' => $prop['floor'],
            ];
        }
        $counts['eligible'] = count($eligible);
        $counts['in_stock_on_watch'] = $inStockSeen;

        $runCsv = $dir."/import_new_run_{$stamp}.csv";
        $this->writeRunCsv($runCsv, $eligible);

        $created = [];
        $skuFile = $dir.'/skus.txt';
        if ($dry) {
            $result = $counts['eligible'] ? $import->previewFromPath($runCsv, 20) : [];
            $toAdd = array_values(array_filter(array_map(fn ($r) => $r['sku'], $eligible), fn ($s) => ! isset($existingSync[strtoupper($s)])));
            $summary = ['mode' => 'DRY-RUN'] + $counts + [
                'would_create' => $result['would_create'] ?? 0,
                'would_update' => $result['would_update'] ?? 0,
                'skus_txt_would_add' => count($toAdd),
            ];
            @unlink($runCsv);
        } else {
            $t0 = now()->subSecond();
            $result = $counts['eligible'] ? $import->importFromPath($runCsv) : [];
            $skus = array_map(fn ($r) => $r['sku'], $eligible);
            foreach (array_chunk($skus ?: [''], 500) as $part) {
                $part = array_values(array_filter($part));
                if ($part === []) {
                    continue;
                }
                Product::query()->whereIn('sku', $part)->where('created_at', '>=', $t0)
                    ->get(['id', 'sku', 'is_active'])->each(function ($p) use (&$created) {
                        $created[$p->id] = $p->sku;
                    });
            }
            $visibleOos = 0;
            if ($created) {
                $newProducts = Product::query()->with('images')->whereIn('id', array_keys($created))->get();
                foreach ($newProducts as $product) {
                    $product->import_source = 'astrum';
                    if (CatalogOosPolicy::shouldPublishNewAsVisibleOos($product)) {
                        $product->is_active = true;
                        $product->in_stock = false;
                        if (Schema::hasColumn('products', 'is_oos_listable')) {
                            $product->is_oos_listable = true;
                        }
                        $visibleOos++;
                    } else {
                        $product->is_active = false;
                    }
                    $product->save();
                }
            }
            $add = [];
            foreach ($created as $sku) {
                if (! isset($existingSync[strtoupper($sku)])) {
                    $add[] = $sku."\t".$sku;
                }
            }
            if ($add) {
                if (is_file($skuFile)) {
                    copy($skuFile, $dir."/skus.txt.bak_{$stamp}");
                }
                $txt = (is_file($skuFile) && filesize($skuFile) > 0 && ! str_ends_with((string) file_get_contents($skuFile), "\n")) ? "\n" : '';
                $txt .= '# astrum:import-new '.date('Y-m-d H:i').' — '.count($add)." hidden drafts\n".implode("\n", $add)."\n";
                file_put_contents($skuFile, $txt, FILE_APPEND | LOCK_EX);
            }
            $summary = ['mode' => 'IMPORT'] + $counts + [
                'created' => count($created),
                'published_visible_oos' => $visibleOos,
                'skus_txt_added' => count($add ?? []),
            ];
        }

        $reportFile = $dir.'/import_new_report_'.($dry ? 'dry_' : '').$stamp.'.csv';
        $this->writeReport($reportFile, $report);
        $summary['report'] = str_replace(base_path().'/', '', $reportFile);
        $public = $summary;
        unset($public['errors']);
        $line = json_encode($public, JSON_UNESCAPED_SLASHES);
        Log::info('astrum:import-new '.$line);
        $this->line($line);

        return self::SUCCESS;
    }

    public function checkHashes(): ?string
    {
        if ($this->skipHashGate) {
            return null;
        }
        $checks = [
            'app/Services/ProductImportService.php' => config('astrum.import_new_service_hashes') ?: self::SERVICE_HASHES,
            'app/Console/Commands/SyncAstrumStock.php' => config('astrum.import_new_sync_hashes') ?: self::SYNC_HASHES,
        ];
        foreach ($checks as $rel => $allowed) {
            $path = base_path($rel);
            if (! is_file($path)) {
                return "md5 gate: {$rel} is missing. Refusing to run.";
            }
            $hash = md5_file($path);
            if (! in_array($hash, $allowed, true)) {
                return "md5 gate: {$rel} is {$hash}, which is not a known tested version. Refusing to run. Do not overwrite it.";
            }
        }

        return null;
    }

    public function readWatch(string $file): array
    {
        if (! is_readable($file)) {
            return [];
        }
        $out = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $sku = trim(explode("\t", $line)[0]);
            if ($sku !== '') {
                $out[] = $sku;
            }
        }

        return array_values(array_unique($out));
    }

    /** @return array<string,array<string,string>> upper(SKU) => catalog row */
    public function readCatalog(string $file): array
    {
        if (! is_readable($file)) {
            return [];
        }
        $fh = fopen($file, 'r');
        $header = fgetcsv($fh);
        if (! $header) {
            fclose($fh);

            return [];
        }
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
        $out = [];
        while (($r = fgetcsv($fh)) !== false) {
            if (count(array_filter($r, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $r = array_pad(array_slice($r, 0, count($header)), count($header), '');
            $row = array_combine($header, $r);
            $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
            if ($sku !== '') {
                $out[$sku] = $row;
            }
        }
        fclose($fh);

        return $out;
    }

    /**
     * @return array{0:string,1:string,2:string}|null  [count key, decision, note]
     */
    public function rejectReason(array $prep, float $cost, array $bySku, array $byMpn, array $byBarcode): ?array
    {
        $sku = trim($prep['sku'] ?? '');
        $name = trim($prep['name'] ?? '');
        $brand = trim($prep['brand'] ?? '');
        $images = trim($prep['images'] ?? '');
        $mpn = trim($prep['mpn'] ?? '');
        $bc = self::normBarcode($prep['barcode'] ?? '');
        $blob = $brand.' '.$name.' '.($prep['short description'] ?? '').' '.($prep['description'] ?? '');
        if ($sku === '' || $name === '' || $brand === '' || preg_match('/\s/', $sku)) {
            return ['skip_invalid', 'SKIP_INVALID', 'missing sku/name/brand or whitespace in SKU'];
        }
        if ($images === '' || ! preg_match('#https?://#i', $images)) {
            return ['skip_no_image', 'SKIP_NO_IMAGE', 'no http(s) image URL'];
        }
        if ($cost <= 0) {
            return ['skip_no_cost', 'SKIP_NO_COST', 'no live or catalog cost'];
        }
        if (preg_match('/\b(pinn(acle|pos|sec)|firstshop)\b/i', $blob)) {
            return ['skip_brand', 'SKIP_BRAND', 'forbidden supplier wording in customer text'];
        }
        $keys = array_filter(['sku' => self::norm($sku), 'mpn' => self::norm($mpn)]);
        foreach ($keys as $k) {
            if (isset($bySku[$k])) {
                return ['exists', 'EXISTS', 'SKU/MPN '.$k.' = existing '.$bySku[$k].' (left untouched)'];
            }
            if (strlen($k) >= 4 && isset($byMpn[$k])) {
                return ['exists', 'EXISTS', 'SKU/MPN '.$k.' = existing model '.$byMpn[$k].' (left untouched)'];
            }
        }
        if ($bc !== '' && isset($byBarcode[$bc])) {
            return ['exists', 'EXISTS', 'barcode '.$bc.' = existing '.$byBarcode[$bc].' (left untouched)'];
        }

        return null;
    }

    protected function indexCatalogue(): array
    {
        $bySku = $byMpn = $byBarcode = [];
        Product::withTrashed()->select(['id', 'sku', 'model_number', 'barcode', 'supplier_sku'])
            ->orderBy('id')->chunk(2000, function ($chunk) use (&$bySku, &$byMpn, &$byBarcode) {
                foreach ($chunk as $p) {
                    foreach ([$p->sku, $p->supplier_sku] as $s) {
                        if (($k = self::norm($s)) !== '') {
                            $bySku[$k] ??= $p->sku ?: ('#'.$p->id);
                        }
                    }
                    if (strlen($k = self::norm($p->model_number)) >= 4) {
                        $byMpn[$k] ??= $p->sku ?: ('#'.$p->id);
                    }
                    if (($b = self::normBarcode($p->barcode)) !== '') {
                        $byBarcode[$b] ??= $p->sku ?: ('#'.$p->id);
                    }
                }
            });

        return [$bySku, $byMpn, $byBarcode];
    }

    protected function syncSkuSet(string $skuFile): array
    {
        $out = [];
        if (! is_readable($skuFile)) {
            return $out;
        }
        foreach (file($skuFile, FILE_IGNORE_NEW_LINES) as $line) {
            if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }
            $sku = trim(explode("\t", $line)[0]);
            if ($sku !== '') {
                $out[strtoupper($sku)] = true;
            }
        }

        return $out;
    }

    protected function writeRunCsv(string $path, array $eligible): void
    {
        $out = fopen($path, 'w');
        $cols = ['SKU', 'Name', 'Brand', 'Categories', 'Cost Price', 'Selling Price', 'Published', 'In stock?', 'Stock',
            'Images', 'Short Description', 'Description', 'Barcode', 'MPN', 'Supplier Name', 'Supplier SKU'];
        fputcsv($out, $cols);
        foreach ($eligible as $r) {
            $stock = (int) ($r['stock'] ?? 0);
            fputcsv($out, [
                $r['sku'], $r['name'], $r['brand'], $r['categories'],
                $r['cost price'], $r['selling price'],
                '0',
                $stock > 0 ? '1' : '0', (string) $stock,
                $r['images'], $r['short description'], $r['description'],
                $r['barcode'], $r['mpn'],
                'Astrum', $r['supplier sku'],
            ]);
        }
        fclose($out);
        @chmod($path, 0600);
    }

    protected function writeReport(string $path, array $rows): void
    {
        $fh = fopen($path, 'w');
        $cols = ['decision', 'sku', 'name', 'qty', 'cost_excl', 'sell_incl', 'basis', 'true_profit_R', 'no_loss_floor', 'note'];
        fputcsv($fh, $cols);
        foreach ($rows as $r) {
            fputcsv($fh, array_map(fn ($c) => $r[$c] ?? '', $cols));
        }
        fclose($fh);
        @chmod($path, 0600);
    }
}
