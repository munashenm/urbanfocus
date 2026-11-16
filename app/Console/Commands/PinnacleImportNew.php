<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\CatalogOosPolicy;
use App\Services\ProductImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Create hidden drafts for watched Pinnacle stock codes once the live feed shows stock.
 *
 *   php -d allow_url_fopen=1 artisan pinnacle:import-new --dry-run
 *   php -d allow_url_fopen=1 artisan pinnacle:import-new
 *
 * Safety:
 *  - Only StockCodes listed in storage/app/pinnacle/import_new_watch.txt are considered.
 *  - In-stock new rows stay unpublished drafts (is_active = 0) for the existing
 *    publish path. Eligible zero-stock rows (name, 200+ char description, picture,
 *    cost) are published as visible out-of-stock. Customer copy never names a supplier.
 *  - Prices at the no-loss floor (~8% true profit, rounded up to the next R10,
 *    with R201 shipping built in above R1,500). Never below break-even.
 *  - Skips no image, no cost, PinnPOS/PinnSec branding, and any SKU/MPN/barcode
 *    match against ALL products including trashed. Existing products are not touched
 *    and are not added to the Pinnacle sync list (so ownership is not stolen).
 *  - Feed LastUpdated / min-item rules match pinnacle:sync. The feed URL is never logged.
 *  - Refuses to run unless ProductImportService.php and SyncPinnacleStock.php match a
 *    known md5 (the versions this command was tested against).
 *  - Default cap is 40 creates per run (--max). --dry-run writes nothing.
 *
 * After a real run, created SKUs are appended to storage/app/pinnacle/skus.txt and a
 * confidential report (with costs) is written under storage/app/pinnacle/.
 */
class PinnacleImportNew extends Command
{
    protected $signature = 'pinnacle:import-new
        {--dry-run : Report only; change nothing}
        {--limit=0 : Process at most N watch rows that are in stock (0 = no extra limit)}
        {--max=40 : Safety cap on drafts created in one run}
        {--watch=storage/app/pinnacle/import_new_watch.txt : Watch list (path relative to the app root or absolute)}
        {--refresh : Ignore the cached feed age (still at most one download per run)}';

    protected $description = 'Create hidden drafts for watched Pinnacle products when the feed shows stock, at the no-loss price, and write a review report';

    public const SERVICE_HASHES = [
        '49b2b930f163630703520f8ad2547aa8',
        'fa966431c3cfa88103fd0ecde12a669c',
    ];

    public const SYNC_HASHES = [
        '22ed016d062ec9d91736fb59157e9b23',
        '428a9543e28bc37441c4eb8addb46ad0',
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

    public static function noLossPrice(float $cost): float
    {
        if ($cost <= 0) {
            return 0;
        }
        $k = 1 / 1.15 - 0.039;
        $n = ($cost * 1.08 + 1) / $k;
        if ($n > 1500) {
            $n = ($cost * 1.08 + 201) / $k;
        }

        return $n < 10 ? ceil($n - 1e-9) : ceil($n / 10 - 1e-9) * 10;
    }

    public static function trueProfit(float $price, float $cost): float
    {
        return $price / 1.15 - 0.039 * $price - 1 - $cost - ($price > 1500 ? 200 : 0);
    }

    public static function breakEvenOk(float $price, float $cost): bool
    {
        $net = $price / 1.15 - 0.039 * $price - 1 - ($price > 1500 ? 200 : 0);

        return $price > 0 && $cost > 0 && $net + 1e-6 >= $cost;
    }

    public static function cleanCustomerText(string $s): string
    {
        $s = preg_replace('/\bPinn(acle|POS|Sec)\b/i', '', $s) ?? $s;
        $s = preg_replace('/Worldwilde/i', '', $s) ?? $s;
        $s = preg_replace('/\b\d+\s*[-]?\s*(year|yr|years)\s+warranty\b/i', '', $s) ?? $s;
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;

        return trim($s);
    }

    public static function brandName(string $brand): string
    {
        $b = trim($brand);
        $map = [
            'SANDISK' => 'SanDisk', 'Qnap' => 'QNAP', 'QNAP' => 'QNAP', 'ASUS' => 'Asus',
            'Asus' => 'Asus', 'HP' => 'HP', 'HyperX' => 'HyperX', 'ATEN' => 'Aten',
            'Aten' => 'Aten', 'TP-LINK' => 'TP-Link', 'Tp-link' => 'TP-Link',
            'Western Digital' => 'Western Digital', 'WD' => 'Western Digital',
            'Hiksemi' => 'Hiksemi', 'HIKSEMI' => 'Hiksemi', 'Port' => 'Port Designs',
        ];
        foreach ($map as $k => $v) {
            if (strcasecmp($k, $b) === 0) {
                return $v;
            }
        }

        return $b;
    }

    public static function categoryFor(string $tree, string $name, string $brand = ''): string
    {
        $CO = 'Computing & Office Technology';
        $NC = 'Networking & Connectivity';
        $ES = 'Electronics, Security & Surveillance';
        $BR = 'Business & Retail Solutions';
        $GE = 'Gaming & Entertainment';
        $t = strtolower(trim($tree));
        $n = $name;
        $has = fn ($p) => (bool) preg_match('/'.$p.'/i', $n);
        $gaming = (bool) preg_match('/\b(ROG|TUF|Gaming|Strix|Zephyrus|Alienware|Omen|Victus|Legion|HyperX)\b/i', $n);
        if ($has('rail kit|rack rail')) {
            return $CO.' > Computer Accessories';
        }
        if ($has('solar') && $has('poe') && $has('switch')) {
            return $NC.' > Switches';
        }
        $m = [
            'computing/client-devices/notebooks' => $gaming ? $GE.' > Gaming Laptops' : $CO.' > Laptops',
            'computing/displays/monitors' => $gaming ? $GE.' > Gaming Monitors' : $CO.' > Monitors',
            'computing/displays/large-format-displays' => 'Digital Signage & Displays > '.($has('interactive|touch|ideahub') ? 'Interactive Displays' : 'Indoor Displays'),
            'computing/peripherals/headsets' => $gaming ? $GE.' > Gaming Headsets' : $CO.' > Audio & Headsets',
            'computing/peripherals/mice' => $gaming ? $GE.' > Gaming Mice' : $CO.' > Mice',
            'computing/peripherals/joysticks' => $GE.' > Console Accessories',
            'computing/power/ups' => 'Solar & Power Solutions > UPS Systems',
            'infrastructure/ups' => 'Solar & Power Solutions > UPS Systems',
            'computing/software' => $CO.' > Software',
            'computing/storage' => $CO.' > Storage Devices',
            'computing/bags-sleeves' => $CO.' > Bags & Cases',
            'computing/accessories/docks-hubs' => $CO.' > Computer Accessories',
            'computing/accessories' => $CO.' > Computer Accessories',
            'networking/access-points' => $NC.' > Access Points',
            'networking/range-extenders' => $NC.' > Access Points',
            'networking/routers' => $NC.' > Routers',
            'networking/switches' => $NC.' > Switches',
            'networking' => $NC,
            'security/cctv-ip/network-video-recorders' => $ES.' > NVR Systems',
            'security/cctv-ip/cameras' => $ES.' > IP Cameras',
            'security/cctv-analogue/digital-video-recorders' => $ES.' > DVR Systems',
            'security/cctv-analogue/cameras' => $ES.' > CCTV Cameras',
            'security/cctv-accessories/cables-connectors' => $NC.' > Network Cables',
            'security/cctv-accessories' => $ES.' > CCTV Accessories',
            'security/access-control/intercoms' => $ES.' > Intercom Systems',
            'security/access-control' => $ES.' > Access Control',
            'security' => $ES,
            'point-of-sale' => $BR.' > POS Systems',
        ];
        foreach ($m as $p => $v) {
            if ($t === $p || str_starts_with($t, $p.'/')) {
                return $v;
            }
        }

        return $CO.' > Computer Accessories';
    }

    public static function buildCopy(array $item): array
    {
        $sku = $item['code'];
        $brand = self::brandName($item['brand'] ?? '');
        $name = self::cleanCustomerText(($item['name'] ?? '').'');
        if ($brand !== '' && ! preg_match('/^'.preg_quote($brand, '/').'/i', $name)) {
            $name = trim($brand.' '.$name);
        }
        if (! str_contains(strtoupper($name), strtoupper($sku))) {
            $name = trim($name.' '.$sku);
        }
        $highlights = $item['highlights'] ?? [];
        $bits = [];
        foreach ($highlights as $h) {
            $opt = self::cleanCustomerText((string) ($h['option'] ?? ''));
            $val = self::cleanCustomerText((string) ($h['value'] ?? ''));
            if ($opt !== '' && $val !== '') {
                $bits[] = [$opt, $val];
            }
        }
        $thin = $bits === [];
        $short = $name;
        if ($bits) {
            $short .= '. '.implode('; ', array_map(fn ($b) => $b[0].': '.$b[1], $bits)).'.';
        }
        $lis = '';
        foreach ($bits as $b) {
            $lis .= '<li><strong>'.htmlspecialchars($b[0], ENT_QUOTES).':</strong> '.htmlspecialchars($b[1], ENT_QUOTES).'</li>';
        }
        $safeName = htmlspecialchars($name, ENT_QUOTES);
        $html = '<p>The '.$safeName.' is listed with the specifications below. '
            .'See the key specifications for the details confirmed on the product record.</p>'
            .'<h3>Key specifications</h3><ul>'.$lis
            .'<li><strong>Part number:</strong> '.htmlspecialchars($sku, ENT_QUOTES).'</li></ul>';

        return [
            'name' => $name,
            'brand' => $brand,
            'short' => $short,
            'html' => $html,
            'thin' => $thin,
            'category' => self::categoryFor($item['tree'] ?? '', $name, $brand),
        ];
    }

    public function handle(ProductImportService $import): int
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(0);

        $gate = $this->checkHashes();
        if ($gate !== null) {
            $this->error($gate);
            Log::error('pinnacle:import-new '.$gate);

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $cfg = config('pinnacle');
        $dir = $cfg['storage_dir'] ?? storage_path('app/pinnacle');
        @mkdir($dir, 0775, true);
        $stamp = date('Ymd_His');

        $lock = fopen($dir.'/import_new.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            $this->warn('Another pinnacle:import-new is running, exiting.');

            return self::SUCCESS;
        }

        $urlFopen = filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN);
        if (! $dry && ! $urlFopen) {
            $this->error('allow_url_fopen is OFF: every image download would fail. Re-run with: php -d allow_url_fopen=1 artisan pinnacle:import-new ...');

            return self::FAILURE;
        }

        try {
            $feedPath = $this->feedFile($cfg);
            $feed = $this->parseFeed($feedPath);
        } catch (\Throwable $e) {
            $this->error('Feed unavailable: '.$e->getMessage());

            return self::FAILURE;
        }
        if ($feed['total'] < ($cfg['min_feed_items'] ?? 1000)) {
            $this->error("Feed has only {$feed['total']} products (minimum ".($cfg['min_feed_items'] ?? 1000).'); safety stop.');

            return self::FAILURE;
        }
        if ($feed['last_updated'] === null) {
            $this->error('Feed has no readable <LastUpdated>; safety stop.');

            return self::FAILURE;
        }
        $ageH = (time() - $feed['last_updated']) / 3600;
        if ($ageH > ($cfg['max_feed_age_hours'] ?? 24)) {
            $this->error(sprintf('Feed LastUpdated is %.1f h old (max %d h); safety stop.', $ageH, $cfg['max_feed_age_hours'] ?? 24));

            return self::FAILURE;
        }

        $watchPath = (string) $this->option('watch');
        $watchPath = str_starts_with($watchPath, '/') ? $watchPath : base_path($watchPath);
        $watch = $this->readWatch($watchPath);
        if ($watch === []) {
            $this->error('Watch list is empty or missing.');

            return self::FAILURE;
        }

        [$bySku, $byMpn, $byBarcode] = $this->indexCatalogue();
        $existingSync = $this->syncSkuSet($dir.'/skus.txt');

        $limit = max(0, (int) $this->option('limit'));
        $max = max(1, (int) $this->option('max'));
        if ($limit > 0) {
            $max = min($max, $limit);
        }

        $counts = [
            'watch' => count($watch), 'feed_items' => $feed['total'],
            'feed_age_h' => round($ageH, 1), 'still_oos' => 0, 'missing_from_feed' => 0,
            'exists' => 0, 'skip_no_image' => 0, 'skip_brand' => 0, 'skip_no_cost' => 0,
            'skip_invalid' => 0, 'eligible' => 0, 'capped' => 0, 'allow_url_fopen' => $urlFopen ? 'on' : 'off',
        ];
        $report = [];
        $eligible = [];
        $inStockSeen = 0;
        foreach ($watch as $code) {
            $item = $this->findInFeed($feed, $code);
            if (! $item) {
                $counts['missing_from_feed']++;
                $report[] = ['decision' => 'MISSING_FROM_FEED', 'sku' => $code];
                continue;
            }
            if (($item['stock'] ?? 0) <= 0) {
                $counts['still_oos']++;
                $report[] = ['decision' => 'STILL_OOS', 'sku' => $code, 'qty' => 0, 'cost_excl' => $item['cost']];
                continue;
            }
            $inStockSeen++;
            $reason = $this->rejectReason($item, $bySku, $byMpn, $byBarcode);
            if ($reason !== null) {
                $key = $reason[0];
                $counts[$key] = ($counts[$key] ?? 0) + 1;
                $report[] = ['decision' => $reason[1], 'sku' => $item['code'], 'note' => $reason[2], 'qty' => $item['stock'], 'cost_excl' => $item['cost']];
                continue;
            }
            if (count($eligible) >= $max) {
                $counts['capped']++;
                $report[] = ['decision' => 'CAPPED', 'sku' => $item['code'], 'qty' => $item['stock'], 'cost_excl' => $item['cost'], 'note' => "in stock but over the --max={$max} cap this run"];
                continue;
            }
            $price = self::noLossPrice($item['cost']);
            $copy = self::buildCopy($item);
            $row = [
                'sku' => $item['code'],
                'name' => $copy['name'],
                'brand' => $copy['brand'],
                'categories' => $copy['category'],
                'cost price' => sprintf('%.2f', $item['cost']),
                'selling price' => (string) (int) $price,
                'stock' => (string) $item['stock'],
                'images' => $item['image'],
                'short description' => $copy['short'],
                'description' => $copy['html'],
                'barcode' => $item['ean_raw'],
                'mpn' => $item['code'],
                'supplier sku' => $item['code'],
            ];
            $eligible[] = $row;
            $report[] = [
                'decision' => $copy['thin'] ? 'CREATE_DRAFT_THIN_TEXT' : 'CREATE_DRAFT',
                'sku' => $item['code'],
                'name' => $copy['name'],
                'qty' => $item['stock'],
                'cost_excl' => $item['cost'],
                'no_loss_price_incl' => (int) $price,
                'true_profit_R' => round(self::trueProfit($price, $item['cost']), 2),
                'thin_text' => $copy['thin'] ? 'yes' : 'no',
                'note' => $copy['thin'] ? 'Draft will be created with a thin description from the feed name; review before publishing.' : '',
            ];
        }
        $counts['eligible'] = count($eligible);
        $counts['in_stock_on_watch'] = $inStockSeen;

        // Reuse the existing importer so image handling and product rows stay consistent.
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
                    $product->import_source = 'pinnacle';
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
                $txt = (is_file($skuFile) && filesize($skuFile) > 0 && ! str_ends_with(file_get_contents($skuFile), "\n")) ? "\n" : '';
                $txt .= '# pinnacle:import-new '.date('Y-m-d H:i').' — '.count($add)." hidden drafts\n".implode("\n", $add)."\n";
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
        // Console / log line: counts only, no costs or feed URL.
        $public = $summary;
        unset($public['errors']);
        $line = json_encode($public, JSON_UNESCAPED_SLASHES);
        Log::info('pinnacle:import-new '.$line);
        $this->line($line);

        return self::SUCCESS;
    }

    public function checkHashes(): ?string
    {
        if ($this->skipHashGate) {
            return null;
        }
        $checks = [
            'app/Services/ProductImportService.php' => config('pinnacle.import_new_service_hashes') ?: self::SERVICE_HASHES,
            'app/Console/Commands/SyncPinnacleStock.php' => config('pinnacle.import_new_sync_hashes') ?: self::SYNC_HASHES,
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

    public function parseFeed(string $path): array
    {
        $prev = libxml_use_internal_errors(true);
        $xml = simplexml_load_file($path, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT);
        libxml_use_internal_errors($prev);
        if ($xml === false) {
            throw new \RuntimeException('feed is not valid XML');
        }
        $exact = [];
        $norm = [];
        $total = 0;
        $last = null;
        foreach ($xml->xpath('//*[StockCode]') ?: [] as $x) {
            $code = trim((string) $x->StockCode);
            if ($code === '') {
                continue;
            }
            $total++;
            $highlights = [];
            for ($i = 1; $i <= 6; $i++) {
                $opt = trim((string) $x->{'highlight_feature_'.$i.'_option'});
                $val = trim((string) $x->{'highlight_feature_'.$i.'_value'});
                if ($opt !== '' && $val !== '') {
                    $highlights[] = ['option' => $opt, 'value' => $val];
                }
            }
            $eanRaw = trim((string) ($x->BarcodeEAN ?: $x->BarcodeUPC));
            $row = [
                'code' => $code,
                'stock' => max(0, (int) floor((float) $x->ProdQty)),
                'cost' => round((float) $x->ProdPriceExclVAT, 2),
                'name' => trim((string) $x->ProdName),
                'brand' => trim((string) $x->Brand),
                'image' => trim((string) $x->ProdImg),
                'tree' => trim((string) $x->category_tree),
                'ean' => self::normBarcode($eanRaw),
                'ean_raw' => $eanRaw,
                'highlights' => $highlights,
            ];
            $exact[strtoupper($code)] = $row;
            $n = self::norm($code);
            $norm[$n] = isset($norm[$n]) ? false : $row;
            $ts = strtotime(trim((string) $x->LastUpdated));
            if ($ts !== false && ($last === null || $ts > $last)) {
                $last = $ts;
            }
        }

        return ['exact' => $exact, 'norm' => $norm, 'total' => $total, 'last_updated' => $last];
    }

    public function findInFeed(array $feed, string $code): ?array
    {
        $exact = strtoupper(trim($code));
        if (isset($feed['exact'][$exact])) {
            return $feed['exact'][$exact];
        }
        $n = self::norm($code);
        $hit = $feed['norm'][$n] ?? null;

        return is_array($hit) ? $hit : null;
    }

    public function readWatch(string $file): array
    {
        if (! is_readable($file)) {
            return [];
        }
        $out = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
            if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }
            $sku = trim(explode("\t", rtrim($line, "\r"))[0]);
            if ($sku !== '') {
                $out[$sku] = $sku;
            }
        }

        return array_values($out);
    }

    protected function rejectReason(array $item, array $bySku, array $byMpn, array $byBarcode): ?array
    {
        $sku = $item['code'];
        $brandName = ($item['brand'] ?? '').' '.($item['name'] ?? '');
        if (preg_match('/\bpinn(pos|sec)\b/i', $brandName)) {
            return ['skip_brand', 'SKIP_BRAND', 'PinnPOS/PinnSec branding is not used on the storefront'];
        }
        if (trim($item['image'] ?? '') === '' || trim($item['name'] ?? '') === '' || trim($item['brand'] ?? '') === '') {
            return ['skip_no_image', 'SKIP_NO_IMAGE', 'missing name, brand or image'];
        }
        if ($item['cost'] <= 0) {
            return ['skip_no_cost', 'SKIP_NO_COST', 'missing cost'];
        }
        if (preg_match('/\s/', $sku)) {
            return ['skip_invalid', 'INVALID', 'whitespace in SKU'];
        }
        $price = self::noLossPrice($item['cost']);
        if (! self::breakEvenOk($price, $item['cost'])) {
            return ['skip_invalid', 'INVALID', 'no-loss price is below break-even'];
        }
        $keys = array_filter([self::norm($sku), self::norm($item['code'])]);
        foreach ($keys as $k) {
            if (isset($bySku[$k])) {
                return ['exists', 'EXISTS', 'SKU/MPN '.$k.' = existing '.$bySku[$k].' (left untouched, not added to Pinnacle sync)'];
            }
            if (strlen($k) >= 4 && isset($byMpn[$k])) {
                return ['exists', 'EXISTS', 'SKU/MPN '.$k.' = existing model '.$byMpn[$k].' (left untouched, not added to Pinnacle sync)'];
            }
        }
        $bc = $item['ean'] ?? '';
        if ($bc !== '' && isset($byBarcode[$bc])) {
            return ['exists', 'EXISTS', 'barcode '.$bc.' = existing '.$byBarcode[$bc].' (left untouched, not added to Pinnacle sync)'];
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
        $existing = [];
        if (is_readable($skuFile)) {
            foreach (file($skuFile, FILE_IGNORE_NEW_LINES) as $line) {
                $p = explode("\t", trim($line));
                if ($p[0] !== '' && ! str_starts_with($p[0], '#')) {
                    $existing[strtoupper($p[0])] = true;
                }
            }
        }

        return $existing;
    }

    protected function writeRunCsv(string $path, array $eligible): void
    {
        $out = fopen($path, 'w');
        $cols = ['SKU', 'Name', 'Brand', 'Categories', 'Cost Price', 'Selling Price', 'Published', 'In stock?', 'Stock',
            'Images', 'Short Description', 'Description', 'Barcode', 'MPN', 'Supplier Name', 'Supplier SKU'];
        fputcsv($out, $cols);
        foreach ($eligible as $r) {
            $stock = (int) $r['stock'];
            fputcsv($out, [
                $r['sku'], $r['name'], $r['brand'], $r['categories'],
                $r['cost price'], $r['selling price'],
                '0', $stock > 0 ? '1' : '0', (string) $stock,
                $r['images'], $r['short description'], $r['description'],
                $r['barcode'] ?? '', $r['mpn'] ?? '',
                'Pinnacle', $r['supplier sku'] ?? $r['sku'],
            ]);
        }
        fclose($out);
        @chmod($path, 0600);
    }

    protected function writeReport(string $path, array $rows): void
    {
        $fh = fopen($path, 'w');
        $cols = ['decision', 'sku', 'name', 'qty', 'cost_excl', 'no_loss_price_incl', 'true_profit_R', 'thin_text', 'note'];
        fputcsv($fh, $cols);
        foreach ($rows as $r) {
            fputcsv($fh, array_map(fn ($c) => $r[$c] ?? '', $cols));
        }
        fclose($fh);
        @chmod($path, 0600);
    }

    protected function feedFile(array $cfg): string
    {
        $path = ($cfg['storage_dir'] ?? storage_path('app/pinnacle')).'/feed.xml';
        $fresh = is_file($path) && filesize($path) > 100000
            && (time() - filemtime($path)) < ($cfg['feed_max_age_minutes'] ?? 60) * 60;
        if ($fresh && ! $this->option('refresh')) {
            return $path;
        }
        if (! ($cfg['feed_url'] ?? '')) {
            // Harness / offline: use an already-placed feed if it exists.
            if (is_file($path) && filesize($path) > 100) {
                return $path;
            }
            throw new \RuntimeException('PINNACLE_FEED_URL is not set');
        }
        $tmp = $path.'.part';
        try {
            $resp = Http::timeout($cfg['http_timeout'] ?? 180)->withOptions(['sink' => $tmp])->get($cfg['feed_url']);
        } catch (\Throwable $e) {
            @unlink($tmp);
            throw new \RuntimeException('download failed ('.class_basename($e).')');
        }
        if (! $resp->successful() || ! is_file($tmp) || filesize($tmp) < 100000) {
            @unlink($tmp);
            throw new \RuntimeException('download failed (HTTP '.$resp->status().')');
        }
        rename($tmp, $path);
        @file_put_contents(($cfg['storage_dir'] ?? storage_path('app/pinnacle')).'/downloads.log', date('c')."\n", FILE_APPEND);

        return $path;
    }
}
