<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\AstrumApi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Price + stock sync of EXISTING products listed in storage/app/astrum/skus.txt.
 *
 * National stock is the sum of JHB + CPT + DBN from GET /stock?skus= (not the
 * Johannesburg-biased GET /products stock_status). Cost comes from GET /products
 * (your_price_excl_vat). Prices follow the agreed tiered margin (20% / 15% / 10%
 * by resulting price, no FirstShop cap) and are never set below the no-loss floor.
 * Sync will raise a stale FirstShop-capped or old-ceiling price up to the new
 * target; it will not push a price back to the old rule. A product
 * is never flipped to out of stock while a cached Syntech/Esquire feed shows
 * stock for the same part number or EAN.
 *
 *   php artisan astrum:sync --dry-run
 *   php artisan astrum:sync --stock-only   # stock fields only; leave prices
 *   php artisan astrum:sync
 *
 * Read-only GETs only. The API key is never logged or printed.
 */
class SyncAstrumStock extends Command
{
    protected $signature = 'astrum:sync
        {--dry-run : Report changes without writing}
        {--stock-only : Update stock fields only; do not change price or cost}';

    protected $description = 'Price+stock sync from Astrum for products listed in storage/app/astrum/skus.txt';

    public function handle(): int
    {
        @ini_set('memory_limit', '256M');
        @set_time_limit(0);
        $cfg = config('astrum');
        $dir = $cfg['storage_dir'] ?? storage_path('app/astrum');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $lock = fopen($dir.'/sync.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            $this->warn('Another astrum:sync is running, exiting.');

            return self::SUCCESS;
        }

        $started = microtime(true);
        $dry = (bool) $this->option('dry-run');
        $stockOnly = (bool) $this->option('stock-only');

        if (! AstrumApi::key()) {
            return $this->stop('ASTRUM_API_KEY is not set in .env');
        }

        $api = new AstrumApi;
        $catalog = $api->allProducts();
        if (! $catalog['ok']) {
            return $this->stop('Products feed unavailable: '.($catalog['error'] ?: 'unknown error'));
        }
        $min = (int) ($cfg['min_products'] ?? 500);
        if (count($catalog['products']) < $min) {
            return $this->stop('Products feed has only '.count($catalog['products'])." products (minimum {$min}); safety stop.");
        }
        $costBySku = [];
        foreach ($catalog['products'] as $row) {
            if (! is_array($row) || empty($row['sku'])) {
                continue;
            }
            $costBySku[strtoupper(trim((string) $row['sku']))] = (float) ($row['your_price_excl_vat'] ?? 0);
        }

        $listed = $this->readSkuList($cfg['sku_file'] ?? ($dir.'/skus.txt'));
        if ($listed === []) {
            return $this->stop('SKU list is empty or missing: storage/app/astrum/skus.txt');
        }
        $products = $this->loadProducts(array_keys($listed));

        $codes = [];
        foreach ($listed as $sku => $code) {
            $codes[] = $code ?: $sku;
        }
        $stockRes = $api->stockFor($codes);
        if (! $stockRes['ok']) {
            return $this->stop('Stock feed unavailable: '.($stockRes['error'] ?: 'unknown error'));
        }
        $stockBySku = [];
        foreach ($stockRes['by_sku'] as $sku => $row) {
            $stockBySku[strtoupper(trim((string) $sku))] = $row;
        }

        [$guard, $guardNote] = $this->loadGuard($cfg);
        $plan = $this->plan(
            $listed,
            $products,
            $stockBySku,
            $costBySku,
            (bool) ($cfg['missing_oos'] ?? true),
            $guard,
            $stockOnly,
            (float) ($cfg['max_price_change'] ?? 0.40)
        );
        $s = $plan['stats'];
        $s['oos_guard'] = $guardNote;
        $s['api_products'] = count($catalog['products']);
        $s['stock_only'] = $stockOnly;

        if ($s['on_site'] > 0 && $s['to_oos'] / $s['on_site'] > ($cfg['max_oos_fraction'] ?? 0.30)) {
            $this->writeLog($cfg, $dry, 'ABORTED', $s, $plan);

            return $this->stop(sprintf('%d of %d listed products (%.0f%%) would flip to out of stock (max %.0f%%); safety stop, nothing written.',
                $s['to_oos'], $s['on_site'], 100 * $s['to_oos'] / $s['on_site'], 100 * ($cfg['max_oos_fraction'] ?? 0.30)));
        }

        $hasSynced = $this->hasColumn('last_synced_at');
        $hasSupplierCost = $this->hasColumn('supplier_cost_price');
        $n = 0;
        $pause = max(0, (int) ($cfg['chunk_pause_ms'] ?? 600));
        foreach ($plan['updates'] as $id => $upd) {
            if ($hasSynced) {
                $upd['last_synced_at'] = now();
            }
            if (! $hasSupplierCost) {
                unset($upd['supplier_cost_price']);
            }
            if (! $dry) {
                $this->writeUpdate((int) $id, $upd);
            }
            if (++$n % 50 === 0 && $pause) {
                usleep($pause * 1000);
            }
        }

        $s['seconds'] = round(microtime(true) - $started, 1);
        $line = $this->writeLog($cfg, $dry, 'OK', $s, $plan);
        $this->info($line);
        Log::info($line);

        return self::SUCCESS;
    }

    /**
     * Pure planning step (unit-testable). No HTTP, no writes.
     *
     * @param  array<string,string|null>  $listed  our SKU => Astrum SKU (null = same)
     * @param  array<string,object>  $products  upper(our SKU) => product
     * @param  array<string,array>  $stockBySku  upper(Astrum SKU) => {qty, in_stock}
     * @param  array<string,float>  $costBySku  upper(Astrum SKU) => excl-VAT cost
     */
    public function plan(array $listed, array $products, array $stockBySku, array $costBySku, bool $missingOos, array $guard = [], bool $stockOnly = false, float $maxChange = 0.40): array
    {
        $s = [
            'listed' => count($listed), 'on_site' => 0, 'not_on_site' => 0, 'in_feed' => 0, 'missing_from_feed' => 0,
            'updated' => 0, 'qty_changed' => 0, 'now_oos' => 0, 'back_in_stock' => 0, 'missing_oos' => 0,
            'to_oos' => 0, 'unchanged' => 0, 'kept_other_supplier' => 0,
            'price_raised' => 0, 'price_lowered' => 0, 'price_set' => 0, 'cost_updated' => 0, 'price_held_floor' => 0,
        ];
        $updates = [];
        $lists = [
            'now_oos' => [], 'back_in_stock' => [], 'qty_changed' => [], 'missing' => [],
            'not_on_site' => [], 'kept_other_supplier' => [], 'price_raised' => [], 'price_lowered' => [],
        ];

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
            $astrumSku = strtoupper(trim((string) ($code ?: $sku)));
            $f = $stockBySku[$astrumSku] ?? null;
            $ean = $this->barcodeOf($p);
            $wouldGoOos = $oldIn && (! $f || (int) ($f['qty'] ?? 0) <= 0) && ($f || $missingOos);
            if ($wouldGoOos && $guard !== [] && ($held = $this->guardHit($guard, [$sku, $code ?: $sku], $ean))) {
                $s['kept_other_supplier']++;
                $lists['kept_other_supplier'][] = "$sku ($held)";
                $f ? $s['in_feed']++ : $s['missing_from_feed']++;
                $upd = $this->priceUpdate($p, $costBySku[$astrumSku] ?? null, $stockOnly, $maxChange, $s, $lists, $sku);
                if ($upd) {
                    $updates[$p->id] = $upd;
                }
                continue;
            }

            $upd = [];
            if (! $f) {
                $s['missing_from_feed']++;
                $lists['missing'][] = $sku;
                if ($missingOos && ($oldIn || $oldQty > 0)) {
                    $upd = ['stock_quantity' => 0, 'in_stock' => false];
                    $s['missing_oos']++;
                    if ($oldIn) {
                        $s['to_oos']++;
                    }
                }
            } else {
                $s['in_feed']++;
                $qty = max(0, (int) $f['qty']);
                $newIn = $qty > 0;
                if ($oldQty !== $qty || $oldIn !== $newIn) {
                    $upd = ['stock_quantity' => $qty, 'in_stock' => $newIn, 'manage_stock' => true];
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
            }

            $priceUpd = $this->priceUpdate($p, $costBySku[$astrumSku] ?? null, $stockOnly, $maxChange, $s, $lists, $sku);
            if ($priceUpd) {
                $upd = array_merge($upd, $priceUpd);
            }
            if ($upd) {
                $updates[$p->id] = $upd;
            }
        }
        $s['updated'] = count($updates);

        return ['stats' => $s, 'updates' => $updates, 'lists' => $lists];
    }

    private function priceUpdate(object $p, ?float $cost, bool $stockOnly, float $maxChange, array &$s, array &$lists, string $sku): array
    {
        if ($stockOnly || $cost === null || $cost <= 0) {
            return [];
        }
        $upd = [];
        $oldCost = (float) ($p->cost_price ?? 0);
        if (abs($oldCost - $cost) >= 0.01) {
            $upd['cost_price'] = round($cost, 2);
            $upd['supplier_cost_price'] = round($cost, 2);
            $s['cost_updated']++;
        }
        $current = (float) ($p->price ?? 0);
        $prop = AstrumApi::proposePrice($cost, $current > 0 ? $current : null, $maxChange);
        $new = (int) $prop['price'];
        if ($current <= 0) {
            $upd['price'] = $new;
            $s['price_set']++;
            $lists['price_raised'][] = "$sku 0->$new ({$prop['basis']})";
        } elseif ($new != (int) round($current)) {
            $upd['price'] = $new;
            if ($new > $current) {
                $s['price_raised']++;
                $lists['price_raised'][] = "$sku ".(int) $current."->$new ({$prop['basis']})";
            } else {
                $s['price_lowered']++;
                $lists['price_lowered'][] = "$sku ".(int) $current."->$new ({$prop['basis']})";
            }
        } elseif ($prop['basis'] === 'kept (at or above no-loss)' || $current >= $prop['floor']) {
            // no price write
        }
        if ($current > 0 && $current < $prop['floor'] && $new == $prop['floor']) {
            $s['price_held_floor']++;
        }

        return $upd;
    }

    /** @return string|null "Supplier qty N" when another supplier's cached feed shows stock */
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
                            $guard['ean'][ltrim($code, '0')] = $label;
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

    /** @return array<string,string|null> our SKU => Astrum SKU (or null) */
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

    /** @return array<string,object> upper(sku) => product */
    protected function loadProducts(array $skus): array
    {
        $cols = ['id', 'sku', 'price', 'cost_price', 'stock_quantity', 'in_stock', 'barcode'];
        $out = [];
        foreach (array_chunk($skus, 500) as $part) {
            foreach (Product::whereIn('sku', $part)->get($cols) as $p) {
                $out[strtoupper(trim((string) $p->sku))] = $p;
            }
        }

        return $out;
    }

    protected function writeUpdate(int $id, array $upd): void
    {
        Product::whereKey($id)->update($upd);
    }

    protected function hasColumn(string $col): bool
    {
        return Schema::hasColumn('products', $col);
    }

    protected function writeLog(array $cfg, bool $dry, string $status, array $s, array $plan): string
    {
        $public = $s;
        // Never put costs, prices, or the API key in the console/log line.
        $line = ($dry ? '[DRY RUN] ' : '').'Astrum sync '.$status.': '.json_encode($public);
        $l = $plan['lists'] ?? [];
        $body = date('c').' '.$line."\n"
            .'  now_oos: '.implode(', ', $l['now_oos'] ?? [])."\n"
            .'  back_in_stock: '.implode(', ', $l['back_in_stock'] ?? [])."\n"
            .'  qty_changed: '.implode(', ', $l['qty_changed'] ?? [])."\n"
            .'  missing_from_feed: '.implode(', ', $l['missing'] ?? [])."\n"
            .'  listed_not_on_site: '.implode(', ', $l['not_on_site'] ?? [])."\n"
            .'  kept_in_stock_other_supplier: '.implode(', ', $l['kept_other_supplier'] ?? [])."\n"
            .'  price_raised: '.implode(', ', $l['price_raised'] ?? [])."\n"
            .'  price_lowered: '.implode(', ', $l['price_lowered'] ?? [])."\n";
        $logFile = $cfg['log_file'] ?? storage_path('logs/astrum_sync.log');
        $logDir = dirname($logFile);
        if (! is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }
        @file_put_contents($logFile, $body, FILE_APPEND);
        $dir = $cfg['storage_dir'] ?? storage_path('app/astrum');
        @file_put_contents($dir.'/last_sync_report.txt', $body);

        return $line;
    }

    protected function barcodeOf(object $p): string
    {
        $d = ltrim(preg_replace('/\D/', '', (string) ($p->barcode ?? '')), '0');

        return strlen($d) >= 8 ? $d : '';
    }

    public function norm(?string $s): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $s));
    }

    protected function stop(string $msg): int
    {
        $this->error($msg);
        Log::error('Astrum sync: '.$msg);
        $log = config('astrum.log_file') ?: storage_path('logs/astrum_sync.log');
        @file_put_contents($log, date('c').' ERROR '.$msg."\n", FILE_APPEND);

        return self::FAILURE;
    }
}
