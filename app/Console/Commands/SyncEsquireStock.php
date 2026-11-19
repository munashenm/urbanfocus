<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\BlackFridaySpecialService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use XMLReader;

/**
 * Update stock (and, once the price basis is confirmed, cost + selling price) of EXISTING
 * Esquire products from the Esquire XML plugin feed. Never creates, publishes, renames or
 * recategorises products.
 *
 *   php artisan esquire:sync --dry-run
 *   php artisan esquire:sync
 */
class SyncEsquireStock extends Command
{
    protected $signature = 'esquire:sync
        {--dry-run : Report changes without writing}
        {--force-prices : Apply price changes larger than the safety limit}
        {--refresh : Ignore the cached feed age (still at most one download per run)}
        {--missing-oos : Mark linked products missing from the feed as out of stock}';

    protected $description = 'Sync Esquire stock (and v3 price when enabled) for existing Esquire products';

    public function handle(): int
    {
        @ini_set('memory_limit', '256M');
        @set_time_limit(0);
        $cfg = config('esquire');
        $dir = $cfg['storage_dir'];
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $lock = fopen($dir.'/sync.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            $this->warn('Another esquire:sync is running, exiting.');

            return self::SUCCESS;
        }

        $started = microtime(true);
        $dry = (bool) $this->option('dry-run');
        $doPrices = $cfg['price_basis'] === 'cost_excl';
        $missingOos = $this->option('missing-oos') || $cfg['missing_oos'];

        try {
            $feedPath = $this->feedFile($cfg);
            [$feed, $total] = $this->parseFeed($feedPath);
        } catch (\Throwable $e) {
            return $this->stop('Feed unavailable: '.$e->getMessage());
        }
        if ($total < $cfg['min_feed_items']) {
            return $this->stop("Feed has only $total products (minimum {$cfg['min_feed_items']}); safety stop.");
        }

        $linked = $this->linkedSkus($cfg);
        if ($linked === []) {
            return $this->stop('No Esquire-linked products found (supplier_name / skus.txt).');
        }
        $inFeed = 0;
        foreach (array_keys($linked) as $sku) {
            if (isset($feed[$this->norm($sku)])) {
                $inFeed++;
            }
        }
        if ($inFeed < 0.5 * count($linked) && count($linked) > 20) {
            return $this->stop("Feed matched only $inFeed of ".count($linked).' linked SKUs; safety stop.');
        }

        $products = Product::whereIn('id', array_values($linked))
            ->get(['id', 'sku', 'supplier_sku', 'brand', 'price', 'sale_price', 'cost_price', 'stock_quantity', 'in_stock']);

        // Price sanity: compare proposed v3 prices with current prices before touching anything.
        $priceNote = $doPrices ? 'prices ON' : 'prices OFF (ESQUIRE_PRICE_BASIS=stock_only)';
        if ($doPrices) {
            $ratios = [];
            foreach ($products as $p) {
                $f = $feed[$this->norm($p->supplier_sku ?: $p->sku)] ?? null;
                if ($f && $f['price'] > 0 && $p->price > 0) {
                    $ratios[] = $this->floorPrice($f['price'], $f['tier']) / (float) $p->price;
                }
            }
            sort($ratios);
            $median = $ratios ? $ratios[intdiv(count($ratios), 2)] : 1.0;
            if ($median < $cfg['sanity_ratio_min'] || $median > $cfg['sanity_ratio_max']) {
                $doPrices = false;
                $priceNote = sprintf('prices SKIPPED: median new/old ratio %.2f outside %.2f-%.2f (feed price basis looks wrong)',
                    $median, $cfg['sanity_ratio_min'], $cfg['sanity_ratio_max']);
                Log::warning('Esquire sync: '.$priceNote);
            }
        }

        $s = ['feed_items' => $total, 'linked' => count($linked), 'in_feed' => $inFeed, 'checked' => 0, 'updated' => 0,
            'stock_changed' => 0, 'now_oos' => 0, 'back_in_stock' => 0, 'price_up' => 0, 'price_down' => 0,
            'price_held' => 0, 'missing' => 0, 'missing_oos' => 0];
        $held = [];
        $missing = [];
        $hasSupplierCost = Schema::hasColumn('products', 'supplier_cost_price');
        $hasSynced = Schema::hasColumn('products', 'last_synced_at');

        foreach ($products->chunk(max(1, $cfg['chunk_size'])) as $chunk) {
            foreach ($chunk as $p) {
                $s['checked']++;
                $f = $feed[$this->norm($p->supplier_sku ?: $p->sku)] ?? $feed[$this->norm($p->sku)] ?? null;
                $upd = [];
                if (! $f) {
                    $s['missing']++;
                    $missing[] = $p->sku;
                    if ($missingOos && ($p->in_stock || $p->stock_quantity > 0)) {
                        $upd = ['stock_quantity' => 0, 'in_stock' => false];
                        $s['missing_oos']++;
                    }
                } else {
                    if ((int) $p->stock_quantity !== $f['stock'] || (bool) $p->in_stock !== ($f['stock'] > 0)) {
                        $upd['stock_quantity'] = $f['stock'];
                        $upd['in_stock'] = $f['stock'] > 0;
                        $upd['manage_stock'] = true;
                        $s['stock_changed']++;
                        if ($f['stock'] <= 0 && $p->in_stock) {
                            $s['now_oos']++;
                        } elseif ($f['stock'] > 0 && ! $p->in_stock) {
                            $s['back_in_stock']++;
                        }
                    }
                    if ($doPrices && $f['price'] > 0) {
                        $cost = $f['price'];
                        $price = $this->floorPrice($cost, $f['tier']);
                        $old = (float) $p->price;
                        if ($this->salePriceIsProtected($p)) {
                            if (abs((float) $p->cost_price - $cost) >= 0.01) {
                                $upd['cost_price'] = $cost;
                                if ($hasSupplierCost) {
                                    $upd['supplier_cost_price'] = $cost;
                                }
                            }
                        } else {
                            $big = $old > 0 && abs($price - $old) / $old > $cfg['max_price_change'];
                            if ($big && ! $this->option('force-prices')) {
                                $s['price_held']++;
                                $held[] = sprintf('%s R%s -> R%s', $p->sku, $old, $price);
                            } elseif (abs($price - $old) >= 0.01 || abs((float) $p->cost_price - $cost) >= 0.01) {
                                $upd['cost_price'] = $cost;
                                if ($hasSupplierCost) {
                                    $upd['supplier_cost_price'] = $cost;
                                }
                                if (abs($price - $old) >= 0.01) {
                                    $upd['price'] = $price;
                                    $s[$price > $old ? 'price_up' : 'price_down']++;
                                    if ($p->sale_price && (float) $p->sale_price >= $price) {
                                        $upd['sale_price'] = null;
                                    }
                                }
                            }
                        }
                    }
                }
                if ($upd) {
                    if ($hasSynced) {
                        $upd['last_synced_at'] = now();
                    }
                    $s['updated']++;
                    if (! $dry) {
                        Product::whereKey($p->id)->update($upd); // no model events / side effects
                    }
                }
            }
            usleep(max(0, $cfg['chunk_pause_ms']) * 1000);
        }

        $s['seconds'] = round(microtime(true) - $started, 1);
        $line = ($dry ? '[DRY RUN] ' : '').'Esquire sync ('.$priceNote.'): '.json_encode($s);
        $this->info($line);
        Log::info($line);
        file_put_contents($dir.'/last_sync_report.txt', date('c')."\n".$line
            ."\n\nPrice changes held for review (>".($cfg['max_price_change'] * 100)."%):\n".implode("\n", $held)
            ."\n\nLinked SKUs missing from feed:\n".implode("\n", $missing)."\n");
        file_put_contents($dir.'/sync_history.log', date('c').' '.$line."\n", FILE_APPEND);

        return self::SUCCESS;
    }

    /**
     * v3 rule, highest of: A tier price cost x (1+tier) x 1.15; C true 25% profit on cost after VAT,
     * Paystack 2.9%+R1, bank 1%, R200 shipping phased in above R1,500. (B, supplier retail x1.05, is
     * not available: the Esquire feed has no separate retail field.) Rounded up to R10 (R1 under R10).
     */
    public function floorPrice(float $cost, float $tier): float
    {
        $vat = 1 + config('esquire.vat', 0.15);
        $k = 1 / $vat - config('esquire.fee_percent', 0.039);
        $threshold = config('esquire.free_shipping_threshold', 1500);
        $a = $cost * (1 + $tier) * $vat;
        $n = ((1 + config('esquire.min_profit', 0.25)) * $cost + config('esquire.fee_fixed', 1.0)) / $k;
        $c = $n < $threshold ? $n : min($n + config('esquire.shipping_excl', 200) / $k, $n + ($n - $threshold));
        $x = round(max($a, $c), 6);

        return (float) ($x < 10 ? ceil($x) : ceil($x / 10) * 10);
    }

    public function tier(string $category, string $head, string $brand): float
    {
        $b = strtoupper(trim($brand));
        $c = strtolower($category.' '.$head);
        if (preg_match('/notebook|laptop|desktop pc|all.in.one|computer: /', $c)) {
            return 0.13;
        }
        if ($b === 'CUDY' || preg_match('/storage|ssd|solid state|hard dri|hdd/', $c)) {
            return 0.20;
        }
        if (in_array($b, ['HIKVISION', 'LOGITECH', 'FANVIL'], true)) {
            return 0.23;
        }
        if (in_array($b, ['UBIQUITI', 'MIKROTIK', 'REYEE', 'GRANDSTREAM'], true)) {
            return 0.17;
        }

        return 0.30;
    }

    /**
     * Black Friday specials keep the price the customer pays. Stock and cost still sync.
     */
    protected function salePriceIsProtected(Product $product): bool
    {
        return app(BlackFridaySpecialService::class)->preservesSellingPrice($product);
    }

    protected function norm(?string $s): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $s));
    }

    /** @return array<string,int> sku => id. supplier_name = Esquire OR sku in skus.txt */
    protected function linkedSkus(array $cfg): array
    {
        $hasSupplier = Schema::hasColumn('products', 'supplier_name');
        $listed = is_readable($cfg['sku_file'])
            ? array_values(array_filter(array_map('trim', file($cfg['sku_file']))))
            : [];
        if (! $hasSupplier && $listed === []) {
            return [];
        }
        $out = [];
        $q = Product::query()->select(['id', 'sku'])->where(function ($w) use ($hasSupplier, $cfg) {
            if ($hasSupplier) {
                $w->where('supplier_name', $cfg['supplier_name']);
            } else {
                $w->whereRaw('1=0');
            }
        });
        foreach ($q->get() as $p) {
            $out[$p->sku] = $p->id;
        }
        foreach (array_chunk($listed, 500) as $part) {
            foreach (Product::whereIn('sku', $part)->get(['id', 'sku']) as $p) {
                $out[$p->sku] = $p->id;
            }
        }

        return $out;
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
            throw new \RuntimeException('ESQUIRE_FEED_URL is not set in .env');
        }
        $tmp = $path.'.part';
        $resp = Http::timeout($cfg['http_timeout'])->withOptions(['sink' => $tmp])->get($cfg['feed_url']);
        if (! $resp->successful() || ! is_file($tmp) || filesize($tmp) < 100000) {
            @unlink($tmp);
            throw new \RuntimeException('download failed (HTTP '.$resp->status().')'); // URL never logged
        }
        rename($tmp, $path);

        return $path;
    }

    /** @return array{0: array<string,array>, 1: int} */
    protected function parseFeed(string $path): array
    {
        $out = [];
        $total = 0;
        $r = new XMLReader;
        if (! $r->open($path, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new \RuntimeException('cannot open feed');
        }
        while ($r->read()) {
            if ($r->nodeType !== XMLReader::ELEMENT || $r->name !== 'Product') {
                continue;
            }
            $x = @simplexml_load_string($r->readOuterXml());
            if (! $x) {
                continue;
            }
            $total++;
            $key = $this->norm((string) $x->ProductCode);
            if ($key === '') {
                continue;
            }
            $out[$key] = [
                'stock' => max(0, (int) $x->AvailableQty),
                'price' => round((float) $x->Price, 2),
                'tier' => $this->tier((string) $x->Category, (string) $x->CategoryHead, (string) ($x->ProductAttributes->Brand ?? '')),
            ];
        }
        $r->close();

        return [$out, $total];
    }

    protected function stop(string $msg): int
    {
        $this->error($msg);
        Log::error('Esquire sync: '.$msg);
        @file_put_contents(config('esquire.storage_dir').'/sync_history.log', date('c').' ERROR '.$msg."\n", FILE_APPEND);

        return self::FAILURE;
    }
}
