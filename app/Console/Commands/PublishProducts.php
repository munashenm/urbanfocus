<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * products:publish {csv} [--dry-run]
 * CSV header: sku (other columns are ignored).
 * Matches products by exact SKU. Soft-deleted (archived) products are skipped
 * by the default Eloquent scope and counted as missing.
 *
 * Publishes (is_active = true) only products that are currently unpublished
 * and pass all of: price > 0, at least one real (non-placeholder) image,
 * a non-empty name, and a description of 200+ characters (tags stripped).
 * With --no-loss, the selling price must also be at or above the ~8% no-loss
 * floor for cost_price (R201 shipping built in above R1,500, rounded up to the
 * next R10). Products without a usable cost are skipped when that flag is set.
 * Never touches price, cost, stock, text or images. Uses save() so model
 * events / observers run (sitemap cache, IndexNow, CatalogDeduper).
 *
 * Backup: storage/app/publish_backup_<ts>.jsonl (old visibility only).
 * Report: storage/app/publish_report.txt
 */
class PublishProducts extends Command
{
    protected $signature = 'products:publish {csv : CSV with a sku column} {--dry-run : Report only} {--no-loss : Also require selling price at or above the ~8% no-loss floor}';

    protected $description = 'Publish (is_active=true) unpublished products listed by SKU, after quality checks';

    public function handle(): int
    {
        $path = $this->argument('csv');
        $path = is_file($path) ? $path : base_path($path);
        if (! is_file($path)) { $this->error('CSV not found'); return 1; }
        $fh = fopen($path, 'r');
        $h = array_map(fn ($x) => strtolower(trim((string) $x, " \t\n\r\0\x0B\xEF\xBB\xBF")), fgetcsv($fh) ?: []);
        $iS = array_search('sku', $h, true);
        if ($iS === false) { $this->error('CSV needs a sku column'); return 1; }
        $dry = (bool) $this->option('dry-run');
        $backup = storage_path('app/publish_backup_'.now()->format('Ymd_His').'.jsonl');
        $n = ['published' => 0, 'already_published' => 0, 'missing' => 0, 'failed_checks' => 0];
        $failedReasons = [];
        $lines = [];

        while (($r = fgetcsv($fh)) !== false) {
            $sku = trim((string) ($r[$iS] ?? ''));
            if ($sku === '') { continue; }
            $ps = Product::where('sku', $sku)->with('images')->get();
            if ($ps->count() === 0) { $n['missing']++; $lines[] = "MISSING {$sku}"; continue; }
            if ($ps->count() > 1) {
                $n['failed_checks']++;
                $reason = "duplicate SKU ({$ps->count()} products)";
                $failedReasons[$sku] = [$reason];
                $lines[] = "FAIL {$sku}: {$reason}";
                continue;
            }
            $p = $ps->first();
            if ($p->is_active) { $n['already_published']++; $lines[] = "ALREADY {$sku} (id {$p->id})"; continue; }

            $reasons = $this->failReasons($p);
            if ($reasons) {
                $n['failed_checks']++;
                $failedReasons[$sku] = $reasons;
                $lines[] = "FAIL {$sku} (id {$p->id}): ".implode('; ', $reasons);
                continue;
            }

            if (! $dry) {
                $bak = ['id' => $p->id, 'sku' => $sku, 'is_active' => (bool) $p->is_active];
                file_put_contents($backup, json_encode($bak, JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);
                $p->is_active = true;
                $p->save();
            }
            $n['published']++;
            $lines[] = ($dry ? 'WOULD PUBLISH ' : 'PUBLISHED ')."{$sku} (id {$p->id}) {$p->name}";
        }

        $payload = $n + ['failed_reasons' => $failedReasons];
        $head = '['.now()->toDateTimeString().'] '.($dry ? 'DRY RUN ' : '').json_encode($payload, JSON_UNESCAPED_UNICODE).($dry || $n['published'] === 0 ? '' : " backup: {$backup}");
        file_put_contents(storage_path('app/publish_report.txt'), $head."\n".implode("\n", $lines)."\n\n", FILE_APPEND);
        $this->info($head);

        return 0;
    }

    /** @return list<string> */
    private function failReasons(Product $p): array
    {
        $reasons = [];
        $name = trim((string) $p->name);
        if ($name === '') {
            $reasons[] = 'empty name';
        }
        $desc = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $p->description)) ?? '');
        $len = mb_strlen($desc);
        if ($desc === '') {
            $reasons[] = 'empty description';
        } elseif ($len < 200) {
            $reasons[] = "description too short ({$len} chars)";
        }
        if ((float) $p->price <= 0) {
            $reasons[] = 'price is not greater than 0';
        }
        if ($this->option('no-loss')) {
            $cost = (float) ($p->cost_price ?? 0);
            if ($cost <= 0) {
                $reasons[] = 'no cost to confirm no-loss price';
            } else {
                $floor = self::noLossPrice($cost);
                if ((float) $p->price + 1e-6 < $floor) {
                    $reasons[] = sprintf('price is below no-loss floor (floor R%d)', $floor);
                }
            }
        }
        $real = 0;
        $total = 0;
        foreach ($p->images ?? [] as $img) {
            $total++;
            $path = is_object($img) ? (string) ($img->path ?? '') : (string) $img;
            if ($path === '') { continue; }
            if (function_exists('is_catalog_placeholder_path') && is_catalog_placeholder_path($path)) { continue; }
            $real++;
        }
        if ($real === 0) {
            $reasons[] = $total === 0 ? 'no image' : 'no real image (placeholder only)';
        }

        return $reasons;
    }
    /** ~8% no-loss incl-VAT floor, R201 shipping above R1,500, rounded up to the next R10. */
    public static function noLossPrice(float $cost): int
    {
        if ($cost <= 0) {
            return 0;
        }
        $k = 1 / 1.15 - 0.039;
        $n = ($cost * 1.08 + 1) / $k;
        if ($n > 1500) {
            $n = ($cost * 1.08 + 201) / $k;
        }
        if ($n < 10) {
            return (int) ceil($n - 1e-9);
        }

        return (int) (ceil($n / 10 - 1e-9) * 10);
    }
}
