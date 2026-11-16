<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bulk-apply target prices (incl VAT) from a CSV with columns SKU,target_price_incl.
 * Updates ONLY products.price; clears sale_price when it is >= the new price.
 * Nothing else (name, images, categories, descriptions, published status, cost) is touched.
 */
class ApplyPrices extends Command
{
    protected $signature = 'prices:apply {csv : Path to CSV (SKU,target_price_incl)} {--dry-run : Report only, write nothing}';

    protected $description = 'Apply target selling prices by SKU (price column only)';

    public function handle(): int
    {
        $path = $this->argument('csv');
        if (! is_file($path)) {
            $path = base_path($path);
        }
        if (! is_file($path) || ($fh = fopen($path, 'r')) === false) {
            $this->error('CSV not found: '.$this->argument('csv'));

            return self::FAILURE;
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h, " \t\n\r\0\x0B\xEF\xBB\xBF")), fgetcsv($fh) ?: []);
        $iSku = array_search('sku', $header, true);
        $iPrice = array_search('target_price_incl', $header, true);
        if ($iSku === false || $iPrice === false) {
            $this->error('CSV needs columns SKU and target_price_incl');

            return self::FAILURE;
        }

        $targets = [];
        $bad = [];
        while (($row = fgetcsv($fh)) !== false) {
            $sku = trim((string) ($row[$iSku] ?? ''));
            $price = (float) str_replace([',', 'R', ' '], '', (string) ($row[$iPrice] ?? ''));
            if ($sku === '' || $price <= 0) {
                $bad[] = $sku ?: '(blank)';
                continue;
            }
            $targets[$sku] = round($price, 2);
        }
        fclose($fh);

        $dry = (bool) $this->option('dry-run');
        $soft = Schema::hasColumn('products', 'deleted_at');
        $s = ['rows' => count($targets), 'invalid_rows' => count($bad), 'found' => 0, 'not_found' => 0,
            'changed' => 0, 'unchanged' => 0, 'sale_cleared' => 0];
        $missing = [];
        $lines = [];

        foreach (array_chunk($targets, 100, true) as $chunk) {
            $q = DB::table('products')->whereIn('sku', array_keys($chunk));
            if ($soft) {
                $q->whereNull('deleted_at');
            }
            $found = $q->get(['id', 'sku', 'price', 'sale_price'])->keyBy('sku');

            foreach ($chunk as $sku => $new) {
                $p = $found->get($sku);
                if (! $p) {
                    $s['not_found']++;
                    $missing[] = $sku;
                    continue;
                }
                $s['found']++;
                $old = (float) $p->price;
                $clearSale = $p->sale_price !== null && (float) $p->sale_price > 0 && (float) $p->sale_price >= $new;
                if (abs($old - $new) < 0.01 && ! $clearSale) {
                    $s['unchanged']++;
                    continue;
                }
                $upd = ['price' => $new];
                if ($clearSale) {
                    $upd['sale_price'] = null;
                    $s['sale_cleared']++;
                }
                if (! $dry) {
                    DB::table('products')->where('id', $p->id)->update($upd);
                }
                $s['changed']++;
                $lines[] = sprintf('%s R%s -> R%s%s', $sku, $old, $new, $clearSale ? ' (sale cleared)' : '');
            }
            usleep(200000); // be gentle on shared hosting
        }

        $summary = ($dry ? '[DRY RUN] ' : '').now()->toDateTimeString().' '.json_encode($s);
        $report = $summary."\n\nChanged:\n".implode("\n", $lines)
            ."\n\nNot found:\n".implode("\n", $missing)
            ."\n\nInvalid rows:\n".implode("\n", $bad)."\n";
        file_put_contents(storage_path('app/prices_apply_report.txt'), $report);
        $this->info($summary);

        return self::SUCCESS;
    }
}
