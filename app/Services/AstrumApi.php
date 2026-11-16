<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Read-only Astrum HTTP helper. The key is sent only as header X-Astrum-Key
 * (never Bearer). The key is never logged, printed or written to disk.
 * Never call POST /orders or /webhooks from this client.
 */
class AstrumApi
{
    public static function key(): ?string
    {
        $key = config('services.astrum.key') ?: config('astrum.key');
        $key = is_string($key) ? trim($key) : '';

        return $key !== '' ? $key : null;
    }

    public static function baseUrl(): string
    {
        $base = config('services.astrum.base_url') ?: config('astrum.base_url') ?: 'https://astrum.africa/v1';

        return rtrim((string) $base, '/');
    }

    public static function r10(float $x): int
    {
        if ($x < 10) {
            return (int) ceil($x - 1e-9);
        }

        return (int) (ceil($x / 10 - 1e-9) * 10);
    }

    /** Incl-VAT price at margin $m on ex-VAT cost, R201 shipping above R1,500, rounded up to the next R10. */
    public static function marginPrice(float $cost, float $m): int
    {
        if ($cost <= 0) {
            return 0;
        }
        $k = 1 / 1.15 - 0.039;
        $n = ($cost * (1 + $m) + 1) / $k;
        if ($n > 1500) {
            $n = ($cost * (1 + $m) + 201) / $k;
        }

        return self::r10($n);
    }

    public static function noLossPrice(float $cost): int
    {
        return self::marginPrice($cost, 0.08);
    }

    public static function fifteenPercentPrice(float $cost): int
    {
        return self::marginPrice($cost, 0.15);
    }

    public static function v4Price(float $cost): int
    {
        if ($cost <= 0) {
            return 0;
        }
        $k = 1 / 1.15 - 0.039;
        $n = (1.30 * $cost * 1.05 + 1) / $k;
        if ($n >= 1500) {
            $n = min($n + 200 / $k, $n + ($n - 1500));
        }

        return self::r10($n);
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

    /**
     * Agreed Astrum selling price: a tiered margin on ex-VAT cost, chosen by the
     * resulting incl-VAT price (after shipping and rounding), never below no-loss.
     * There is no FirstShop or v4 cap.
     *
     * Walk-down:
     *  - price at 20% is used when that result is under R500;
     *  - otherwise price at 15% is used when that result is at most R5,000;
     *  - otherwise price at 10% is used.
     * At the R500 / R5,000 boundaries the lower margin is chosen, so the 15%
     * (or 10%) result may land slightly below the band because of the lower margin
     * and rounding. That is intentional and deterministic.
     *
     * @return array{price:int,floor:int,tier:string,basis:string,p20:int,p15:int,p10:int,margin:float}
     */
    public static function tieredPrice(float $cost): array
    {
        $m20 = (float) (config('astrum.margin_20') ?? 0.20);
        $m15 = (float) (config('astrum.margin_15') ?? 0.15);
        $m10 = (float) (config('astrum.margin_10') ?? 0.10);
        $floor = self::noLossPrice($cost);
        $p20 = self::marginPrice($cost, $m20);
        $p15 = self::marginPrice($cost, $m15);
        $p10 = self::marginPrice($cost, $m10);
        $band500 = (float) (config('astrum.price_band_20') ?? 500);
        $band5k = (float) (config('astrum.price_band_15') ?? 5000);
        if ($p20 < $band500) {
            $price = $p20;
            $tier = '20%';
            $margin = $m20;
            $basis = '20% tier (selling price under R'.(int) $band500.')';
        } elseif ($p15 <= $band5k) {
            $price = $p15;
            $tier = '15%';
            $margin = $m15;
            $basis = '15% tier (selling price R'.(int) $band500.'–R'.number_format($band5k, 0, '.', ',').')';
        } else {
            $price = $p10;
            $tier = '10%';
            $margin = $m10;
            $basis = '10% tier (selling price above R'.number_format($band5k, 0, '.', ',').')';
        }
        if ($price < $floor) {
            $price = $floor;
            $tier = 'no-loss';
            $margin = 0.08;
            $basis = 'no-loss floor (tiered price was below floor)';
        }

        return [
            'price' => (int) $price,
            'floor' => $floor,
            'tier' => $tier,
            'basis' => $basis,
            'margin' => $margin,
            'p20' => $p20,
            'p15' => $p15,
            'p10' => $p10,
        ];
    }

    /**
     * Sync/import helper. The target is the tiered price (no FirstShop / v4 cap),
     * never below the no-loss floor.
     *
     * If $current is missing or 0, use the target. If $current is below the floor
     * or below the target, raise to the target (sync will not keep an old FirstShop
     * cap or the old 15% ceiling on a product that now belongs in the 20% band).
     * A raise larger than $maxIncrease (default 200%) is refused and the current
     * price is kept, so a bad cost cannot blow up a price in one run.
     * If $current is above the target, ease down toward it within $maxChange
     * (default 40%), still not below the floor.
     */
    public static function proposePrice(float $cost, ?float $current = null, float $maxChange = 0.40, ?float $maxIncrease = null): array
    {
        $t = self::tieredPrice($cost);
        $floor = $t['floor'];
        $target = (int) $t['price'];
        $maxIncrease = $maxIncrease ?? (float) (config('astrum.max_price_increase') ?? 2.0);
        $price = $target;
        $basis = $t['basis'];
        if ($current && $current > 0) {
            if ($current < $floor || $current < $target) {
                $price = $target;
                $basis = ($current < $floor ? 'raised to no-loss / tiered target' : 'raised to '.$t['basis']);
                if ($current > 0 && $target > $current * (1 + $maxIncrease) + 0.5) {
                    $price = (int) round($current);
                    $basis = 'kept (raise exceeds max increase)';
                }
            } elseif ($current > $target) {
                $minAllowed = max($floor, $current * (1 - $maxChange));
                if ($target >= $minAllowed) {
                    $price = $target;
                    $basis = 'lowered toward '.$t['basis'];
                } elseif ($minAllowed < $current) {
                    $price = self::r10($minAllowed);
                    if ($price < $floor) {
                        $price = $floor;
                    }
                    $basis = 'lowered within max change, not below no-loss';
                } else {
                    $price = (int) round($current);
                    $basis = 'kept (at or above no-loss)';
                }
            } else {
                $price = $target;
                $basis = 'kept (already at '.$t['basis'].')';
            }
        }
        if ($price < $floor && ! str_contains($basis, 'max increase')) {
            $price = $floor;
            $basis = 'no-loss floor';
        }

        return [
            'price' => (int) $price,
            'floor' => $floor,
            'target' => $target,
            'tier' => $t['tier'],
            'p20' => $t['p20'],
            'p15' => $t['p15'],
            'p10' => $t['p10'],
            'v4' => self::v4Price($cost),
            'basis' => $basis,
        ];
    }

    /**
     * GET a path under the Astrum base URL. Read-only. Never logs the key or the header.
     *
     * @return array{status:int,json:?array,error:?string}
     */
    public function get(string $path, array $query = []): array
    {
        $key = self::key();
        if (! $key) {
            return ['status' => 0, 'json' => null, 'error' => 'ASTRUM_API_KEY is not set in .env'];
        }
        $path = '/'.ltrim($path, '/');
        if (preg_match('#^/(orders|webhooks)(/|$)#i', $path)) {
            return ['status' => 0, 'json' => null, 'error' => 'Refusing to call a non-catalogue path'];
        }
        $url = self::baseUrl().$path;
        $timeout = (int) (config('astrum.http_timeout') ?: 45);
        try {
            $resp = Http::timeout($timeout)
                ->acceptJson()
                ->withHeaders(['X-Astrum-Key' => $key, 'User-Agent' => 'UrbanFocusAstrumSync/1.0'])
                ->get($url, $query);
        } catch (\Throwable $e) {
            return ['status' => 0, 'json' => null, 'error' => 'request failed ('.class_basename($e).')'];
        }
        $json = $resp->json();
        if (! is_array($json)) {
            $json = null;
        }

        return ['status' => $resp->status(), 'json' => $json, 'error' => $resp->successful() ? null : 'HTTP '.$resp->status()];
    }

    /** Paginate GET /products. */
    public function allProducts(int $limit = 100): array
    {
        $all = [];
        $offset = 0;
        $pages = 0;
        $pause = max(0, (int) (config('astrum.chunk_pause_ms') ?: 600));
        $total = null;
        while ($pages < 30) {
            $res = $this->get('/products', ['limit' => $limit, 'offset' => $offset]);
            if ($res['error'] || ! is_array($res['json'])) {
                return ['ok' => false, 'error' => $res['error'] ?: 'bad products response', 'products' => $all];
            }
            $batch = $res['json']['products'] ?? [];
            $pag = $res['json']['pagination'] ?? [];
            $total = $pag['total'] ?? $total;
            $all = array_merge($all, is_array($batch) ? $batch : []);
            $pages++;
            if (empty($pag['has_more']) || ! $batch) {
                break;
            }
            $offset += $limit;
            if ($pause) {
                usleep($pause * 1000);
            }
        }

        return ['ok' => true, 'error' => null, 'products' => $all, 'total' => $total, 'pages' => $pages];
    }

    /**
     * GET /stock?skus= in batches. Each item includes all branches (jhb, cpt, dbn).
     *
     * @param  array<int,string>  $skus
     * @return array{ok:bool,error:?string,by_sku:array<string,array>}
     */
    public function stockFor(array $skus): array
    {
        $skus = array_values(array_unique(array_filter(array_map('strval', $skus), fn ($s) => $s !== '')));
        $by = [];
        $batchSize = max(1, (int) (config('astrum.stock_batch') ?: 40));
        $pause = max(0, (int) (config('astrum.chunk_pause_ms') ?: 600));
        foreach (array_chunk($skus, $batchSize) as $i => $chunk) {
            if ($i > 0 && $pause) {
                usleep($pause * 1000);
            }
            $res = $this->get('/stock', ['skus' => implode(',', $chunk)]);
            if ($res['error'] || ! is_array($res['json'])) {
                return ['ok' => false, 'error' => $res['error'] ?: 'bad stock response', 'by_sku' => $by];
            }
            $rows = $res['json']['stock'] ?? [];
            if (! is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                if (! is_array($row) || empty($row['sku'])) {
                    continue;
                }
                $sku = (string) $row['sku'];
                if (! isset($by[$sku])) {
                    $by[$sku] = ['sku' => $sku, 'branches' => [], 'qty' => 0, 'in_stock' => false];
                }
                $qty = max(0, (int) ($row['qty_capped'] ?? 0));
                $branch = (string) ($row['branch'] ?? '');
                $by[$sku]['branches'][$branch] = ['qty' => $qty, 'status' => $row['status'] ?? null, 'capped' => (bool) ($row['capped'] ?? false)];
                $by[$sku]['qty'] += $qty;
                if ($qty > 0) {
                    $by[$sku]['in_stock'] = true;
                }
            }
        }

        return ['ok' => true, 'error' => null, 'by_sku' => $by];
    }
}
