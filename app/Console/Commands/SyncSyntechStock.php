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
 * Update stock, cost and selling price of Syntech-tagged products from the Syntech XML feed.
 * Never creates or publishes products. Safe to run from cron (no prompts, file lock).
 *
 *   php artisan syntech:sync            # normal run
 *   php artisan syntech:sync --dry-run  # report only, no DB writes
 */
class SyncSyntechStock extends Command
{
    protected $signature = 'syntech:sync
        {--dry-run : Report changes without writing}
        {--force-prices : Apply price changes larger than the safety limit}
        {--refresh : Ignore the cached feed age (still at most one download per run)}
        {--missing-oos : Mark tagged products that are missing from the feed as out of stock}';

    protected $description = 'Sync Syntech stock, cost and tier price for Syntech-tagged products';

    public function handle(): int
    {
        @ini_set('memory_limit', '256M');
        @set_time_limit(0);
        $cfg = config('syntech');
        $dir = $cfg['storage_dir'];
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $lock = fopen($dir.'/sync.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            $this->warn('Another syntech:sync is running, exiting.');

            return self::SUCCESS;
        }

        $started = microtime(true);
        $dry = (bool) $this->option('dry-run');

        try {
            $feedPath = $this->feedFile($cfg);
        } catch (\Throwable $e) {
            return $this->stop('Feed unavailable: '.$e->getMessage());
        }

        $tagged = $this->taggedSkus($cfg);
        if ($tagged === []) {
            return $this->stop('No Syntech-tagged products found (supplier_name / skus.txt).');
        }

        $feed = $this->parseFeed($feedPath, array_flip(array_map([$this, 'norm'], array_keys($tagged))));
        if (count($feed) < 0.5 * count($tagged) && count($tagged) > 20) {
            return $this->stop('Feed matched only '.count($feed).' of '.count($tagged).' tagged SKUs; aborting as a safety stop.');
        }

        $s = ['tagged' => count($tagged), 'in_feed' => count($feed), 'checked' => 0, 'updated' => 0,
            'stock_changed' => 0, 'price_changed' => 0, 'now_oos' => 0, 'back_in_stock' => 0,
            'price_held' => 0, 'missing' => 0, 'missing_oos' => 0, 'no_cost' => 0];
        $held = [];
        $missing = [];
        $hasSupplierCost = Schema::hasColumn('products', 'supplier_cost_price');
        $hasSynced = Schema::hasColumn('products', 'last_synced_at');

        foreach (array_chunk(array_values($tagged), max(1, $cfg['chunk_size'])) as $ids) {
            foreach (Product::whereIn('id', $ids)->get(['id', 'sku', 'supplier_sku', 'price', 'sale_price', 'cost_price', 'stock_quantity', 'in_stock']) as $p) {
                $s['checked']++;
                $f = $feed[$this->norm($p->supplier_sku ?: $p->sku)] ?? $feed[$this->norm($p->sku)] ?? null;
                $upd = [];

                if (! $f) {
                    $s['missing']++;
                    $missing[] = $p->sku;
                    if ($this->option('missing-oos') && ($p->in_stock || $p->stock_quantity > 0)) {
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

                    if ($f['cost'] > 0) {
                        $price = $this->floorPrice($f['cost'], $f['tier'], $f['rrp'] ?? 0.0);
                        $old = (float) $p->price;
                        $big = $old > 0 && abs($price - $old) / $old > $cfg['max_price_change'];
                        if (abs($price - $old) >= 0.01 || abs((float) $p->cost_price - $f['cost']) >= 0.01) {
                            if ($big && ! $this->option('force-prices')) {
                                $s['price_held']++;
                                $held[] = sprintf('%s R%s -> R%s', $p->sku, $old, $price);
                            } else {
                                $upd['cost_price'] = $f['cost'];
                                if ($hasSupplierCost) {
                                    $upd['supplier_cost_price'] = $f['cost'];
                                }
                                if (abs($price - $old) >= 0.01 && ! app(BlackFridaySpecialService::class)->preservesSellingPrice($p)) {
                                    $upd['price'] = $price;
                                    $s['price_changed']++;
                                    if ($p->sale_price && (float) $p->sale_price >= $price) {
                                        $upd['sale_price'] = null;
                                    }
                                }
                            }
                        }
                    } else {
                        $s['no_cost']++;
                    }
                }

                if ($upd) {
                    if ($hasSynced) {
                        $upd['last_synced_at'] = now();
                    }
                    $s['updated']++;
                    if (! $dry) {
                        // Query-builder update: no model events, no image/SEO side effects.
                        Product::whereKey($p->id)->update($upd);
                    }
                }
            }
            usleep(max(0, $cfg['chunk_pause_ms']) * 1000);
        }

        $s['seconds'] = round(microtime(true) - $started, 1);
        $line = ($dry ? '[DRY RUN] ' : '').'Syntech sync: '.json_encode($s);
        $this->info($line);
        Log::info($line);
        $report = $line."\n\nPrice changes held for review (>".($cfg['max_price_change'] * 100)."%):\n".implode("\n", $held)
            ."\n\nTagged SKUs missing from feed:\n".implode("\n", $missing)."\n";
        file_put_contents($dir.'/last_sync_report.txt', date('c')."\n".$report);
        file_put_contents($dir.'/sync_history.log', date('c').' '.$line."\n", FILE_APPEND);

        return self::SUCCESS;
    }

    /** cost excl VAT × (1 + tier) × 1.15, up to next R10 (next R1 under R10). No cap. */
    public function tierPrice(float $cost, float $tier): float
    {
        $x = round($cost * (1 + $tier) * (1 + config('syntech.vat', 0.15)), 6);

        return (float) ($x < 10 ? ceil($x) : ceil($x / 10) * 10);
    }

    /**
     * v3 rule: highest of
     *  A tier price = cost x (1+tier) x 1.15
     *  B Syntech RRP incl VAT x 1.05 (ignored if RRP > 1.6 x A)
     *  C floor = true 30% profit on cost x 1.05 buffer after VAT, Paystack 2.9%+R1, bank 1%;
     *    shipping (R200 excl) phased in rand-for-rand above R1,500 until fully included.
     * Rounded up to next R10 (next R1 under R10).
     */
    public function floorPrice(float $cost, float $tier, float $rrp = 0.0): float
    {
        $vat = 1 + config('syntech.vat', 0.15);
        $fee = config('syntech.fee_percent', 0.039);
        $feeFixed = config('syntech.fee_fixed', 1.0);
        $threshold = config('syntech.free_shipping_threshold', 1500);
        $ship = config('syntech.shipping_excl', 200);
        $a = $cost * (1 + $tier) * $vat;
        $b = ($rrp > 0 && $rrp <= 1.6 * $a) ? $rrp * 1.05 : 0.0;
        // Solve price/VAT - cost - ship - fee%*price - feeFixed >= minProfit*cost
        $k = 1 / $vat - $fee;
        $n = ((1 + config('syntech.min_profit', 0.30)) * $cost * (1 + config('syntech.cost_buffer', 0.05)) + $feeFixed) / $k;
        $c = $n < $threshold ? $n : min($n + $ship / $k, $n + ($n - $threshold));
        $x = round(max($a, $b, $c), 6);

        return (float) ($x < 10 ? ceil($x) : ceil($x / 10) * 10);
    }

    /** Same tier rule as the import build script. */
    public function tier(string $top, string $sub, string $brand): float
    {
        $b = strtoupper(trim($brand));
        if ($top === 'Computers') {
            return 0.13;
        }
        if ($b === 'CUDY' || in_array($sub, ['Storage', 'Solid state drives', 'Hard disk drives'], true)) {
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

    protected function norm(?string $s): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $s));
    }

    /** @return array<string,int> sku => product id */
    protected function taggedSkus(array $cfg): array
    {
        $q = Product::query()->select(['id', 'sku']);
        $hasSupplier = Schema::hasColumn('products', 'supplier_name');
        $listed = is_readable($cfg['sku_file'])
            ? array_values(array_filter(array_map('trim', file($cfg['sku_file']))))
            : [];
        if (! $hasSupplier && $listed === []) {
            return [];
        }
        $q->where(function ($w) use ($hasSupplier, $listed, $cfg) {
            if ($hasSupplier) {
                $w->orWhere('supplier_name', $cfg['supplier_name']);
            }
            if ($listed) {
                $w->orWhereIn('sku', $listed);
            }
        });

        return $q->pluck('id', 'sku')->all();
    }

    protected function feedFile(array $cfg): string
    {
        $path = $cfg['storage_dir'].'/feed.xml';
        $fresh = is_file($path) && filesize($path) > 100000
            && (time() - filemtime($path)) < $cfg['feed_max_age_minutes'] * 60;
        if ($fresh && ! $this->option('refresh')) {
            $this->line('Using cached feed ('.round((time() - filemtime($path)) / 60).' min old).');

            return $path;
        }
        $url = $cfg['feed_url'];
        if (! $url) {
            throw new \RuntimeException('SYNTECH_FEED_URL is not set in .env');
        }
        $tmp = $path.'.part';
        $resp = Http::timeout($cfg['http_timeout'])->withOptions(['sink' => $tmp])->get($url);
        if (! $resp->successful() || ! is_file($tmp) || filesize($tmp) < 100000) {
            @unlink($tmp);
            throw new \RuntimeException('download failed (HTTP '.$resp->status().')'); // URL never logged
        }
        rename($tmp, $path);
        file_put_contents($cfg['storage_dir'].'/downloads.log', date('c')."\n", FILE_APPEND);

        return $path;
    }

    /** Streaming parse; keeps only products whose normalised SKU is wanted. */
    protected function parseFeed(string $path, array $wanted): array
    {
        $out = [];
        $r = new XMLReader;
        if (! $r->open($path, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new \RuntimeException('cannot open feed');
        }
        while ($r->read()) {
            if ($r->nodeType !== XMLReader::ELEMENT || $r->name !== 'product' || $r->depth < 2) {
                continue;
            }
            $x = @simplexml_load_string($r->readOuterXml());
            if (! $x) {
                continue;
            }
            $key = $this->norm((string) $x->sku);
            if ($key === '' || ! isset($wanted[$key])) {
                continue;
            }
            $tree = explode('|', (string) $x->categorytree)[0];
            $parts = array_map('trim', explode('>', $tree));
            $brand = trim((string) ($x->attributes->brand ?? '')) ?: 'Unknown';
            $out[$key] = [
                'stock' => (int) $x->cptstock + (int) $x->jhbstock + (int) $x->dbnstock,
                'cost' => round((float) $x->price, 2),
                'rrp' => round((float) $x->rrp_incl, 2),
                'tier' => $this->tier($parts[0] ?? '', $parts[1] ?? '', $brand),
            ];
        }
        $r->close();

        return $out;
    }

    protected function stop(string $msg): int
    {
        $this->error($msg);
        Log::error('Syntech sync: '.$msg);
        @file_put_contents(config('syntech.storage_dir').'/sync_history.log', date('c').' ERROR '.$msg."\n", FILE_APPEND);

        return self::FAILURE;
    }
}
