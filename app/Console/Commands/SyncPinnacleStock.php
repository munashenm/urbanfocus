<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * STOCK-ONLY sync of EXISTING products listed in storage/app/pinnacle/skus.txt from the Pinnacle XML feed.
 * Updates stock_quantity / in_stock (and manage_stock) only. Never touches price, cost, name, description,
 * brand, images, categories or published status, and never creates products.
 * Products are selected purely by the SKU list (supplier_name is NOT used).
 * OOS guard: a product is never flipped to out of stock while the cached Syntech/Esquire feeds on this server
 * (written by syntech:sync / esquire:sync) show stock for the same part number or EAN. The guard can only
 * prevent an out-of-stock flip; it never sets anything in stock.
 *
 *   php artisan pinnacle:sync --dry-run   # report only, no DB writes
 *   php artisan pinnacle:sync
 */
class SyncPinnacleStock extends Command
{
    protected $signature = 'pinnacle:sync
        {--dry-run : Report changes without writing}
        {--refresh : Ignore the cached feed age (still at most one download per run)}';

    protected $description = 'Stock-only sync from the Pinnacle feed for products listed in storage/app/pinnacle/skus.txt';

    public function handle(): int
    {
        @ini_set('memory_limit', '256M');
        @set_time_limit(0);
        $cfg = config('pinnacle');
        $dir = $cfg['storage_dir'];
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $lock = fopen($dir.'/sync.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            $this->warn('Another pinnacle:sync is running, exiting.');

            return self::SUCCESS;
        }

        $started = microtime(true);
        $dry = (bool) $this->option('dry-run');

        // 1. Feed (download failure = abort)
        try {
            $feedPath = $this->feedFile($cfg);
            $feed = $this->parseFeed($feedPath);
        } catch (\Throwable $e) {
            return $this->stop('Feed unavailable: '.$e->getMessage());
        }
        if ($feed['total'] < $cfg['min_feed_items']) {
            return $this->stop("Feed has only {$feed['total']} products (minimum {$cfg['min_feed_items']}); safety stop.");
        }
        if ($feed['last_updated'] === null) {
            return $this->stop('Feed has no readable <LastUpdated>; safety stop.');
        }
        $ageH = (time() - $feed['last_updated']) / 3600;
        if ($ageH > $cfg['max_feed_age_hours']) {
            return $this->stop(sprintf('Feed LastUpdated is %.1f h old (max %d h); safety stop.', $ageH, $cfg['max_feed_age_hours']));
        }

        // 2. SKU list -> products on our site
        $listed = $this->readSkuList($cfg['sku_file']);
        if ($listed === []) {
            return $this->stop('SKU list is empty or missing: storage/app/pinnacle/skus.txt');
        }
        $products = $this->loadProducts(array_keys($listed));

        // 3. Plan (no writes yet)
        [$guard, $guardNote] = $this->loadGuard($cfg);
        $plan = $this->plan($listed, $products, $feed, (bool) $cfg['missing_oos'], $guard);
        $s = $plan['stats'];
        $s['oos_guard'] = $guardNote;
        $s['feed_items'] = $feed['total'];
        $s['feed_age_h'] = round($ageH, 1);

        // 4. Mass-OOS safety cap
        if ($s['on_site'] > 0 && $s['to_oos'] / $s['on_site'] > $cfg['max_oos_fraction']) {
            $this->writeLog($cfg, $dry, 'ABORTED', $s, $plan);

            return $this->stop(sprintf('%d of %d listed products (%.0f%%) would flip to out of stock (max %.0f%%); safety stop, nothing written.',
                $s['to_oos'], $s['on_site'], 100 * $s['to_oos'] / $s['on_site'], 100 * $cfg['max_oos_fraction']));
        }

        // 5. Write (stock fields only)
        $hasSynced = $this->hasColumn('last_synced_at');
        $n = 0;
        foreach ($plan['updates'] as $id => $upd) {
            if ($hasSynced) {
                $upd['last_synced_at'] = now();
            }
            if (! $dry) {
                $this->writeUpdate((int) $id, $upd);
            }
            if (++$n % max(1, $cfg['chunk_size']) === 0) {
                usleep(max(0, $cfg['chunk_pause_ms']) * 1000);
            }
        }

        if ($cfg['price_report']) {
            $this->writePriceReport($dir.'/price_gap_report.csv', $plan['price_rows']);
        }

        $s['seconds'] = round(microtime(true) - $started, 1);
        $line = $this->writeLog($cfg, $dry, 'OK', $s, $plan);
        $this->info($line);
        Log::info($line);

        return self::SUCCESS;
    }

    /**
     * Pure planning step (unit-testable).
     *
     * @param  array<string,string|null>  $listed  our SKU => Pinnacle StockCode (null = same as our SKU)
     * @param  array<string,object>  $products  our SKU (upper-cased) => {id, sku, stock_quantity, in_stock, price}
     */
    public function plan(array $listed, array $products, array $feed, bool $missingOos, array $guard = []): array
    {
        $s = ['listed' => count($listed), 'on_site' => 0, 'not_on_site' => 0, 'in_feed' => 0, 'missing_from_feed' => 0,
            'updated' => 0, 'qty_changed' => 0, 'now_oos' => 0, 'back_in_stock' => 0, 'missing_oos' => 0, 'to_oos' => 0, 'unchanged' => 0, 'kept_other_supplier' => 0];
        $updates = [];
        $lists = ['now_oos' => [], 'back_in_stock' => [], 'qty_changed' => [], 'missing' => [], 'not_on_site' => [], 'kept_other_supplier' => []];
        $priceRows = [];

        foreach ($listed as $sku => $code) {
            $p = $products[strtoupper(trim($sku))] ?? null;
            if (! $p) {
                $s['not_on_site']++;
                $lists['not_on_site'][] = $sku;
                continue;
            }
            $s['on_site']++;
            $oldQty = (int) $p->stock_quantity;
            $oldIn = (bool) $p->in_stock;
            $f = $this->findInFeed($feed, $code ?: $sku);
            $wouldGoOos = $oldIn && (! $f || $f['stock'] <= 0) && ($f || $missingOos);
            if ($wouldGoOos && $guard !== [] && ($held = $this->guardHit($guard, [$sku, $code ?: $sku], $f['ean'] ?? ''))) {
                $s['kept_other_supplier']++;
                $lists['kept_other_supplier'][] = "$sku ($held)";
                $f ? $s['in_feed']++ : $s['missing_from_feed']++;
                continue; // leave the product untouched
            }
            if (! $f) {
                $s['missing_from_feed']++;
                $lists['missing'][] = $sku;
                if ($missingOos && ($oldIn || $oldQty > 0)) {
                    $updates[$p->id] = ['stock_quantity' => 0, 'in_stock' => false];
                    $s['missing_oos']++;
                    if ($oldIn) {
                        $s['to_oos']++;
                    }
                }
                continue;
            }
            $s['in_feed']++;
            $qty = $f['stock'];
            $newIn = $qty > 0;
            if ($oldQty !== $qty || $oldIn !== $newIn) {
                $updates[$p->id] = ['stock_quantity' => $qty, 'in_stock' => $newIn, 'manage_stock' => true];
                if ($oldIn && ! $newIn) {
                    $s['now_oos']++;
                    $s['to_oos']++;
                    $lists['now_oos'][] = $sku;
                } elseif (! $oldIn && $newIn) {
                    $s['back_in_stock']++;
                    $lists['back_in_stock'][] = "$sku ($qty)";
                } else {
                    $s['qty_changed']++;
                    $lists['qty_changed'][] = "$sku $oldQty->$qty";
                }
            } else {
                $s['unchanged']++;
            }
            $price = (float) $p->price;
            if ($f['cost'] > 0 && $price > 0) {
                $excl = $price / 1.15;
                $priceRows[] = [$p->sku, $f['code'], $f['cost'], $price, round($excl, 2), round(($excl - $f['cost']) / $excl * 100, 1), $qty];
            }
        }
        $s['updated'] = count($updates);

        return ['stats' => $s, 'updates' => $updates, 'lists' => $lists, 'price_rows' => $priceRows];
    }

    /** Exact StockCode first; punctuation-insensitive only when that key is unique in the feed. */
    public function findInFeed(array $feed, string $code): ?array
    {
        $exact = strtoupper(trim($code));
        if (isset($feed['exact'][$exact])) {
            return $feed['exact'][$exact];
        }
        $n = $this->norm($code);
        $hit = $feed['norm'][$n] ?? null;

        return is_array($hit) ? $hit : null; // false = ambiguous normalised key
    }

    /**
     * Product nodes are any element with a <StockCode> child, whatever the element is called.
     *
     * @return array{exact: array, norm: array, total: int, last_updated: ?int}
     */
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
            $row = [
                'code' => $code,
                'stock' => max(0, (int) floor((float) $x->ProdQty)),
                'cost' => round((float) $x->ProdPriceExclVAT, 2),
                'ean' => ltrim(preg_replace('/\D/', '', (string) ($x->BarcodeEAN ?: $x->BarcodeUPC)), '0'),
            ];
            $exact[strtoupper($code)] = $row;
            $n = $this->norm($code);
            $norm[$n] = isset($norm[$n]) ? false : $row;
            $ts = strtotime(trim((string) $x->LastUpdated));
            if ($ts !== false && ($last === null || $ts > $last)) {
                $last = $ts;
            }
        }

        return ['exact' => $exact, 'norm' => $norm, 'total' => $total, 'last_updated' => $last];
    }

    /** @return string|null "Supplier qty N" when another supplier's cached feed shows stock for any key */
    public function guardHit(array $guard, array $codes, string $ean): ?string
    {
        foreach ($codes as $c) {
            $n = $this->norm($c);
            if ($n !== '' && isset($guard['code'][$n])) {
                return $guard['code'][$n];
            }
        }
        if (strlen($ean) >= 8 && isset($guard['ean'][$ean])) {
            return $guard['ean'][$ean].' via EAN';
        }

        return null;
    }

    /**
     * Read the other syncs' cached feeds (local files only, no download). Only entries with stock > 0 are kept.
     * A missing, stale or unreadable feed just disables that source (reported in the log); it never aborts the run.
     *
     * @return array{0: array{code: array<string,string>, ean: array<string,string>}, 1: string}
     */
    public function loadGuard(array $cfg): array
    {
        $guard = ['code' => [], 'ean' => []];
        $notes = [];
        foreach ($cfg['oos_guard_feeds'] ?? [] as $g) {
            $path = $g['path'];
            if (! is_file($path)) {
                $notes[] = $g['name'].': no cached feed';
                continue;
            }
            $ageH = (time() - filemtime($path)) / 3600;
            if ($ageH > ($cfg['oos_guard_max_age_hours'] ?? 24)) {
                $notes[] = sprintf('%s: cached feed %.0f h old, ignored', $g['name'], $ageH);
                continue;
            }
            try {
                $n = 0;
                $r = new \XMLReader();
                if (! $r->open($path, null, LIBXML_NONET | LIBXML_COMPACT)) {
                    throw new \RuntimeException('cannot open');
                }
                while ($r->read()) {
                    if ($r->nodeType !== \XMLReader::ELEMENT || $r->name !== $g['item']) {
                        continue;
                    }
                    $x = @simplexml_load_string($r->readOuterXml(), 'SimpleXMLElement', LIBXML_NOCDATA);
                    if (! $x) {
                        continue;
                    }
                    $qty = 0;
                    foreach ($g['qty'] as $q) {
                        $qty += max(0, (int) $x->{$q});
                    }
                    if ($qty <= 0) {
                        continue;
                    }
                    $label = $g['name'].' qty '.$qty;
                    $code = $this->norm((string) $x->{$g['code']});
                    if ($code !== '') {
                        $guard['code'][$code] = $label;
                        if (ctype_digit($code) && strlen($code) >= 8) {
                            $guard['ean'][ltrim($code, '0')] = $label; // some codes are EANs
                        }
                    }
                    if (! empty($g['ean'])) {
                        $ean = ltrim(preg_replace('/\D/', '', (string) $x->{$g['ean']}), '0');
                        if (strlen($ean) >= 8) {
                            $guard['ean'][$ean] = $label;
                        }
                    }
                    $n++;
                }
                $r->close();
                $notes[] = sprintf('%s: %d in-stock items (feed %.1f h old)', $g['name'], $n, $ageH);
            } catch (\Throwable $e) {
                $notes[] = $g['name'].': unreadable, ignored';
            }
        }

        return [$guard, implode('; ', $notes) ?: 'off'];
    }

    /** @return array<string,string|null> our SKU => Pinnacle StockCode (or null) */
    public function readSkuList(string $file): array
    {
        if (! is_readable($file)) {
            return [];
        }
        $out = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
            if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }
            $parts = explode("\t", rtrim($line, "\r"));
            $sku = trim($parts[0]);
            if ($sku !== '') {
                $out[$sku] = isset($parts[1]) && trim($parts[1]) !== '' ? trim($parts[1]) : null;
            }
        }

        return $out;
    }

    /** @return array<string,object> upper(sku) => product (stock fields + price for the review report) */
    protected function loadProducts(array $skus): array
    {
        $out = [];
        foreach (array_chunk($skus, 500) as $part) {
            foreach (Product::whereIn('sku', $part)->get(['id', 'sku', 'price', 'stock_quantity', 'in_stock']) as $p) {
                $out[strtoupper(trim((string) $p->sku))] = $p;
            }
        }

        return $out;
    }

    protected function writeUpdate(int $id, array $upd): void
    {
        Product::whereKey($id)->update($upd); // query builder: no model events / side effects
    }

    protected function hasColumn(string $col): bool
    {
        return Schema::hasColumn('products', $col);
    }

    protected function feedFile(array $cfg): string
    {
        $path = $cfg['storage_dir'].'/feed.xml';
        $fresh = is_file($path) && filesize($path) > 100000
            && (time() - filemtime($path)) < $cfg['feed_max_age_minutes'] * 60;
        if ($fresh && ! $this->option('refresh')) {
            return $path;
        }
        if (! $cfg['feed_url']) {
            throw new \RuntimeException('PINNACLE_FEED_URL is not set in .env');
        }
        $tmp = $path.'.part';
        try {
            $resp = Http::timeout($cfg['http_timeout'])->withOptions(['sink' => $tmp])->get($cfg['feed_url']);
        } catch (\Throwable $e) {
            @unlink($tmp);
            throw new \RuntimeException('download failed ('.class_basename($e).')'); // URL never logged
        }
        if (! $resp->successful() || ! is_file($tmp) || filesize($tmp) < 100000) {
            @unlink($tmp);
            throw new \RuntimeException('download failed (HTTP '.$resp->status().')'); // URL never logged
        }
        rename($tmp, $path);
        @file_put_contents($cfg['storage_dir'].'/downloads.log', date('c')."\n", FILE_APPEND);

        return $path;
    }

    protected function writeLog(array $cfg, bool $dry, string $status, array $s, array $plan): string
    {
        $line = ($dry ? '[DRY RUN] ' : '').'Pinnacle stock sync '.$status.': '.json_encode($s);
        $l = $plan['lists'];
        $body = date('c').' '.$line."\n"
            .'  now_oos: '.implode(', ', $l['now_oos'])."\n"
            .'  back_in_stock: '.implode(', ', $l['back_in_stock'])."\n"
            .'  qty_changed: '.implode(', ', $l['qty_changed'])."\n"
            .'  missing_from_feed: '.implode(', ', $l['missing'])."\n"
            .'  listed_not_on_site: '.implode(', ', $l['not_on_site'])."\n"
            .'  kept_in_stock_other_supplier: '.implode(', ', $l['kept_other_supplier'])."\n";
        $logDir = dirname($cfg['log_file']);
        if (! is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        @file_put_contents($cfg['log_file'], $body, FILE_APPEND);
        @file_put_contents($cfg['storage_dir'].'/last_sync_report.txt', $body);

        return $line;
    }

    /** Review only (CONFIDENTIAL, private storage). Never used to change prices. */
    protected function writePriceReport(string $path, array $rows): void
    {
        $fh = @fopen($path, 'w');
        if (! $fh) {
            return;
        }
        fputcsv($fh, ['sku', 'pinnacle_stockcode', 'pinnacle_cost_excl', 'our_price_incl', 'our_price_excl', 'gross_margin_pct_excl', 'pinnacle_qty']);
        foreach ($rows as $r) {
            fputcsv($fh, $r);
        }
        fclose($fh);
    }

    protected function norm(?string $s): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $s));
    }

    protected function stop(string $msg): int
    {
        $this->error($msg);
        Log::error('Pinnacle sync: '.$msg);
        @file_put_contents(config('pinnacle.log_file'), date('c').' ERROR '.$msg."\n", FILE_APPEND);

        return self::FAILURE;
    }
}
