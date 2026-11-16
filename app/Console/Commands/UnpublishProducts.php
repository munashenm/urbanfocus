<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * Unpublish products by SKU from a CSV (column SKU, optional product_id: when given, id AND SKU must both match). Sets is_active = false only.
 * Never deletes or creates products. Soft-deleted products are skipped (default Eloquent scope).
 * Report: storage/app/unpublish_report.txt
 */
class UnpublishProducts extends Command
{
    protected $signature = 'products:unpublish {csv : CSV with a SKU column} {--dry-run : Report only}';

    protected $description = 'Unpublish (is_active=false) products listed by SKU';

    public function handle(): int
    {
        $path = $this->argument('csv');
        $path = is_file($path) ? $path : base_path($path);
        if (! is_file($path)) { $this->error('CSV not found'); return 1; }
        $fh = fopen($path, 'r');
        $h = array_map(fn ($x) => strtolower(trim((string) $x, " \t\n\r\0\x0B\xEF\xBB\xBF")), fgetcsv($fh) ?: []);
        $iS = array_search('sku', $h, true);
        $iId = array_search('product_id', $h, true);
        if ($iS === false) { $this->error('CSV needs SKU column'); return 1; }
        $dry = (bool) $this->option('dry-run');
        $lines = []; $n = ['done' => 0, 'already' => 0, 'missing' => 0, 'ambiguous' => 0];
        while (($r = fgetcsv($fh)) !== false) {
            $sku = trim((string) ($r[$iS] ?? ''));
            if ($sku === '') { continue; }
            $q = Product::where('sku', $sku);
            if ($iId !== false && trim((string) ($r[$iId] ?? '')) !== '') {
                $q->where('id', (int) $r[$iId]); // exact product: id AND sku must both match
            }
            $ps = $q->get(['id', 'sku', 'name', 'is_active']);
            if ($ps->count() === 0) { $n['missing']++; $lines[] = "MISSING {$sku}"; continue; }
            if ($ps->count() > 1) { $n['ambiguous']++; $lines[] = "SKIP {$sku}: {$ps->count()} products share this SKU"; continue; }
            $p = $ps->first();
            if (! $p->is_active) { $n['already']++; $lines[] = "ALREADY {$sku}"; continue; }
            if (! $dry) { $p->is_active = false; $p->save(); }
            $n['done']++; $lines[] = ($dry ? 'WOULD UNPUBLISH ' : 'UNPUBLISHED ')."{$sku} (id {$p->id}) {$p->name}";
        }
        $head = '['.now()->toDateTimeString().'] '.($dry ? 'DRY RUN ' : '').json_encode($n);
        file_put_contents(storage_path('app/unpublish_report.txt'), $head."\n".implode("\n", $lines)."\n\n", FILE_APPEND);
        $this->info($head);
        return 0;
    }
}
