<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\AstrumApi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Planned one-off repricing of existing Astrum products to the agreed tiered
 * margin (20% / 15% / 10% by resulting price, no FirstShop cap, never below
 * the no-loss floor). Does not change published/hidden status, cost, stock,
 * name, description or images. Only the selling price is written.
 *
 *   php artisan astrum:reprice storage/app/astrum/reprice_1115.csv --dry-run
 *   php artisan astrum:reprice storage/app/astrum/reprice_1115.csv
 *
 * Default safety cap is 200% (ASTRUM_REPRICE_MAX_CHANGE / --max-change). A
 * product whose move would exceed the cap is skipped and reported, not applied.
 */
class RepriceAstrumProducts extends Command
{
    protected $signature = 'astrum:reprice
        {csv=storage/app/astrum/reprice_1115.csv : CSV with a sku column (our site SKU)}
        {--dry-run : Report changes without writing}
        {--max-change= : Max relative price move (default 2.0 = 200%)}';

    protected $description = 'Reprice listed Astrum products to the agreed tiered margin; never below no-loss; does not change visibility';

    /** Harness-only. */
    public bool $skipLock = false;

    public function handle(): int
    {
        @ini_set('memory_limit', '256M');
        @set_time_limit(0);

        $cfg = config('astrum') ?: [];
        $dir = $cfg['storage_dir'] ?? storage_path('app/astrum');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        if (! $this->skipLock) {
            $lock = fopen($dir.'/reprice.lock', 'c');
            if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
                $this->warn('Another astrum:reprice is running, exiting.');

                return self::SUCCESS;
            }
        }

        $dry = (bool) $this->option('dry-run');
        $maxChange = $this->option('max-change');
        $maxChange = $maxChange === null || $maxChange === ''
            ? (float) ($cfg['reprice_max_change'] ?? 2.0)
            : (float) $maxChange;
        if ($maxChange <= 0) {
            $maxChange = 2.0;
        }

        $file = (string) $this->argument('csv');
        $path = str_starts_with($file, '/') ? $file : base_path($file);
        if (! is_readable($path)) {
            $this->error('CSV not found: '.$file);

            return self::FAILURE;
        }

        $fh = fopen($path, 'r');
        $header = fgetcsv($fh);
        if (! $header) {
            $this->error('CSV is empty.');

            return self::FAILURE;
        }
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
        $iS = array_search('sku', $header, true);
        if ($iS === false) {
            $this->error('CSV needs a sku column');

            return self::FAILURE;
        }
        $skus = [];
        while (($r = fgetcsv($fh)) !== false) {
            $sku = trim((string) ($r[$iS] ?? ''));
            if ($sku !== '' && ! str_starts_with($sku, '#')) {
                $skus[$sku] = true;
            }
        }
        fclose($fh);
        $listed = array_keys($skus);
        if ($listed === []) {
            $this->error('CSV has no SKUs.');

            return self::FAILURE;
        }

        $products = $this->loadProducts($listed);
        $plan = $this->plan($listed, $products, $maxChange);
        $s = $plan['stats'];
        $s['max_change'] = $maxChange;
        $s['dry_run'] = $dry;

        $stamp = date('Ymd_His');
        $n = 0;
        foreach ($plan['updates'] as $id => $upd) {
            if (! $dry) {
                Product::whereKey($id)->update($upd);
            }
            if (++$n % 50 === 0) {
                usleep(50000);
            }
        }

        $report = $dir.'/reprice_report_'.($dry ? 'dry_' : '').$stamp.'.csv';
        $this->writeReport($report, $plan['rows']);
        $s['report'] = str_replace(base_path().'/', '', $report);
        $s['written'] = $dry ? 0 : count($plan['updates']);
        // Console / log: counts only, no costs.
        $public = $s;
        $line = ($dry ? '[DRY RUN] ' : '').'Astrum reprice: '.json_encode($public, JSON_UNESCAPED_SLASHES);
        $this->info($line);
        Log::info($line);
        @file_put_contents($dir.'/last_reprice_report.txt', date('c').' '.$line."\n", FILE_APPEND);

        return self::SUCCESS;
    }

    /**
     * Pure planning step (unit-testable). Never writes, never changes is_active.
     *
     * @param  array<int,string>  $listed
     * @param  array<string,object>  $products  upper(sku) => product
     */
    public function plan(array $listed, array $products, float $maxChange = 2.0): array
    {
        $s = [
            'listed' => count($listed), 'on_site' => 0, 'missing' => 0, 'no_cost' => 0,
            'would_change' => 0, 'unchanged' => 0, 'skipped_cap' => 0, 'skipped_below_floor' => 0,
            'raised' => 0, 'lowered' => 0, 'tier_20' => 0, 'tier_15' => 0, 'tier_10' => 0, 'tier_noloss' => 0,
        ];
        $updates = [];
        $rows = [];
        foreach ($listed as $sku) {
            $p = $products[strtoupper(trim($sku))] ?? null;
            if (! $p) {
                $s['missing']++;
                $rows[] = ['decision' => 'MISSING', 'sku' => $sku];
                continue;
            }
            $s['on_site']++;
            $cost = (float) ($p->cost_price ?? 0);
            $current = (float) ($p->price ?? 0);
            if ($cost <= 0) {
                $s['no_cost']++;
                $rows[] = ['decision' => 'NO_COST', 'sku' => $sku, 'id' => $p->id, 'old_price' => $current, 'is_active' => (int) (bool) $p->is_active];
                continue;
            }
            $t = AstrumApi::tieredPrice($cost);
            $new = (int) $t['price'];
            if (($t['tier'] ?? '') === '20%') {
                $s['tier_20']++;
            } elseif (($t['tier'] ?? '') === '15%') {
                $s['tier_15']++;
            } elseif (($t['tier'] ?? '') === '10%') {
                $s['tier_10']++;
            } elseif (($t['tier'] ?? '') === 'no-loss') {
                $s['tier_noloss']++;
            }
            if ($new < $t['floor']) {
                $s['skipped_below_floor']++;
                $rows[] = ['decision' => 'SKIP_BELOW_FLOOR', 'sku' => $sku, 'id' => $p->id, 'old_price' => $current, 'new_price' => $new, 'tier' => $t['tier']];
                continue;
            }
            if ($current > 0 && abs($new - $current) > $current * $maxChange + 0.5) {
                $s['skipped_cap']++;
                $rows[] = [
                    'decision' => 'SKIP_MAX_CHANGE', 'sku' => $sku, 'id' => $p->id,
                    'old_price' => $current, 'new_price' => $new, 'tier' => $t['tier'],
                    'basis' => $t['basis'], 'is_active' => (int) (bool) $p->is_active,
                    'note' => 'would exceed --max-change; left unchanged',
                ];
                continue;
            }
            $row = [
                'decision' => 'UNCHANGED', 'sku' => $sku, 'id' => $p->id,
                'old_price' => $current, 'new_price' => $new, 'tier' => $t['tier'],
                'basis' => $t['basis'], 'is_active' => (int) (bool) $p->is_active,
            ];
            if ((int) round($current) !== $new) {
                $updates[$p->id] = ['price' => $new];
                $s['would_change']++;
                if ($new > $current) {
                    $s['raised']++;
                    $row['decision'] = 'RAISE';
                } else {
                    $s['lowered']++;
                    $row['decision'] = 'LOWER';
                }
            } else {
                $s['unchanged']++;
            }
            $rows[] = $row;
        }

        return ['stats' => $s, 'updates' => $updates, 'rows' => $rows];
    }

    /** @return array<string,object> */
    protected function loadProducts(array $skus): array
    {
        $out = [];
        foreach (array_chunk($skus, 500) as $part) {
            foreach (Product::whereIn('sku', $part)->get(['id', 'sku', 'price', 'cost_price', 'is_active']) as $p) {
                $out[strtoupper(trim((string) $p->sku))] = $p;
            }
        }

        return $out;
    }

    protected function writeReport(string $path, array $rows): void
    {
        $fh = fopen($path, 'w');
        $cols = ['decision', 'sku', 'id', 'old_price', 'new_price', 'tier', 'basis', 'is_active', 'note'];
        fputcsv($fh, $cols);
        foreach ($rows as $r) {
            fputcsv($fh, array_map(fn ($c) => $r[$c] ?? '', $cols));
        }
        fclose($fh);
        @chmod($path, 0600);
    }
}
