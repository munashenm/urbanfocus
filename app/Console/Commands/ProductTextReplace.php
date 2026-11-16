<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * products:text-replace {csv} [--dry-run]
 * CSV: sku,product_id,field,find,replace  (literal, case-sensitive string replace; product_id optional but must match if given)
 * Allowed fields: name, description, short_description, meta_title, meta_description.
 * Backup: storage/app/text_replace_backup_<ts>.jsonl. Report: storage/app/text_replace_report.txt
 */
class ProductTextReplace extends Command
{
    protected $signature = 'products:text-replace {csv} {--dry-run : Report only}';

    protected $description = 'Literal find/replace in product text fields by SKU';

    public function handle(): int
    {
        $path = $this->argument('csv');
        $path = is_file($path) ? $path : base_path($path);
        if (! is_file($path)) { $this->error('CSV not found'); return 1; }
        $allowed = ['name', 'description', 'short_description', 'meta_title', 'meta_description'];
        $fh = fopen($path, 'r');
        $h = array_map(fn ($x) => strtolower(trim((string) $x, " \t\n\r\0\x0B\xEF\xBB\xBF")), fgetcsv($fh) ?: []);
        foreach (['sku', 'field', 'find', 'replace'] as $c) { if (! in_array($c, $h, true)) { $this->error("CSV needs {$c}"); return 1; } }
        $ix = array_flip($h);
        $dry = (bool) $this->option('dry-run');
        $backup = storage_path('app/text_replace_backup_'.now()->format('Ymd_His').'.jsonl');
        $n = ['changed' => 0, 'no_match' => 0, 'missing' => 0, 'ambiguous' => 0, 'bad_row' => 0]; $lines = [];
        while (($r = fgetcsv($fh)) !== false) {
            $sku = trim((string) ($r[$ix['sku']] ?? '')); if ($sku === '') { continue; }
            $field = trim((string) ($r[$ix['field']] ?? '')); $find = (string) ($r[$ix['find']] ?? ''); $rep = (string) ($r[$ix['replace']] ?? '');
            if ($find === '' || ! in_array($field, $allowed, true) || ! Schema::hasColumn('products', $field)) { $n['bad_row']++; $lines[] = "BAD ROW {$sku} {$field}"; continue; }
            $q = Product::where('sku', $sku);
            if (isset($ix['product_id']) && trim((string) ($r[$ix['product_id']] ?? '')) !== '') { $q->where('id', (int) $r[$ix['product_id']]); }
            $ps = $q->get();
            if ($ps->count() === 0) { $n['missing']++; $lines[] = "MISSING {$sku}"; continue; }
            if ($ps->count() > 1) { $n['ambiguous']++; $lines[] = "SKIP {$sku}: duplicate SKU"; continue; }
            $p = $ps->first(); $old = (string) ($p->getAttributes()[$field] ?? '');
            $cnt = substr_count($old, $find);
            if ($cnt === 0) { $n['no_match']++; $lines[] = "NO MATCH {$sku} {$field}"; continue; }
            if (! $dry) {
                file_put_contents($backup, json_encode(['id' => $p->id, 'sku' => $sku, 'field' => $field, 'old' => $old])."\n", FILE_APPEND);
                $p->{$field} = str_replace($find, $rep, $old); $p->save();
            }
            $n['changed']++; $lines[] = ($dry ? 'WOULD ' : '')."REPLACE {$sku} (id {$p->id}) {$field}: {$cnt} occurrence(s)";
        }
        $head = '['.now()->toDateTimeString().'] '.($dry ? 'DRY RUN ' : '').json_encode($n).($dry ? '' : " backup: {$backup}");
        file_put_contents(storage_path('app/text_replace_report.txt'), $head."\n".implode("\n", $lines)."\n\n", FILE_APPEND);
        $this->info($head);
        return 0;
    }
}
