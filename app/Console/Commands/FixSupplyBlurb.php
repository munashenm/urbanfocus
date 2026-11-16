<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * seo:fix-supply-blurb [--dry-run]
 * 1) Code: app/helpers.php seo_meta_description() builds "Genuine {$brand} supply from Urban Focus ...".
 *    Replaces that exact literal with "Genuine {$brand} product from Urban Focus ..." (only if found exactly once),
 *    after copying the file to app/helpers.php.bak_<ts>. Restore = copy the .bak back.
 * 2) Stored: products.meta_description containing " supply from Urban Focus" after "Genuine " -> " product from Urban Focus".
 *    Backup storage/app/meta_blurb_backup_<ts>.jsonl. Report: storage/app/meta_blurb_report.txt
 */
class FixSupplyBlurb extends Command
{
    protected $signature = 'seo:fix-supply-blurb {--dry-run : Report only}';

    protected $description = 'Change "Genuine {brand} supply from Urban Focus" to "... product from Urban Focus"';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $ts = now()->format('Ymd_His');
        $lines = [];
        $file = base_path('app/helpers.php');
        $from = 'Genuine {$brand} supply from Urban Focus';
        $to = 'Genuine {$brand} product from Urban Focus';
        $src = is_file($file) ? file_get_contents($file) : '';
        $c = substr_count($src, $from);
        if ($c === 1) {
            $lines[] = ($dry ? 'WOULD PATCH' : 'PATCHED')." app/helpers.php (1 occurrence)";
            if (! $dry) {
                copy($file, $file.'.bak_'.$ts);
                file_put_contents($file, str_replace($from, $to, $src));
                if (function_exists('opcache_invalidate')) { @opcache_invalidate($file, true); }
            }
        } else {
            $lines[] = "CODE NOT PATCHED: found {$c} occurrence(s) of the literal in app/helpers.php (expected 1). Search the codebase for 'supply from Urban Focus'.";
        }
        $n = 0;
        if (Schema::hasColumn('products', 'meta_description')) {
            $backup = storage_path('app/meta_blurb_backup_'.$ts.'.jsonl');
            Product::where('meta_description', 'like', '%Genuine % supply from Urban Focus%')->select(['id', 'sku', 'meta_description'])
                ->chunkById(500, function ($ps) use ($dry, $backup, &$n, &$lines) {
                    foreach ($ps as $p) {
                        $old = (string) $p->meta_description;
                        $new = preg_replace('/(Genuine .{1,80}?) supply from Urban Focus/u', '$1 product from Urban Focus', $old);
                        if ($new === null || $new === $old) { continue; }
                        if (! $dry) { file_put_contents($backup, json_encode(['id' => $p->id, 'sku' => $p->sku, 'meta_description' => $old])."\n", FILE_APPEND); $p->meta_description = $new; $p->save(); }
                        $n++;
                        if ($n <= 20) { $lines[] = ($dry ? 'WOULD ' : '')."UPDATE meta {$p->sku} (id {$p->id})"; }
                    }
                });
        }
        $head = '['.now()->toDateTimeString().'] '.($dry ? 'DRY RUN ' : '')."stored meta rows ".($dry ? 'to change' : 'changed').": {$n}";
        file_put_contents(storage_path('app/meta_blurb_report.txt'), $head."\n".implode("\n", $lines)."\n\n", FILE_APPEND);
        $this->info($head.' | '.$lines[0]);
        return 0;
    }
}
