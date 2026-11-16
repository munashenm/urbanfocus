<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * descriptions:append-block {csv} [--dry-run]
 * CSV header: sku,block_id,html
 * Adds an HTML block to the end of products.description, wrapped in <!--uf:{block_id}--> ... <!--/uf:{block_id}-->.
 * Running it again replaces the same block instead of adding a second copy. Everything else in the description is kept.
 * An empty html cell removes the block. Exact SKU match; missing/duplicate SKUs skipped.
 * Backup: storage/app/descriptions_block_backup_<ts>.jsonl   Report: storage/app/descriptions_block_report.txt
 */
class DescriptionAppendBlock extends Command
{
    protected $signature = 'descriptions:append-block {csv} {--dry-run : Report only}';

    protected $description = 'Append (or refresh) a marked HTML block at the end of product descriptions by SKU';

    public function handle(): int
    {
        $path = $this->argument('csv');
        $path = is_file($path) ? $path : base_path($path);
        if (! is_file($path)) { $this->error('CSV not found'); return 1; }
        $dry = (bool) $this->option('dry-run');
        $fh = fopen($path, 'r');
        $h = array_map(fn ($x) => strtolower(trim((string) $x, " \t\n\r\0\x0B\xEF\xBB\xBF")), fgetcsv($fh) ?: []);
        foreach (['sku', 'block_id', 'html'] as $c) { if (! in_array($c, $h, true)) { $this->error("CSV needs column {$c}"); return 1; } }
        $ix = array_flip($h);
        $backup = storage_path('app/descriptions_block_backup_'.now()->format('Ymd_His').'.jsonl');
        $n = ['added' => 0, 'replaced' => 0, 'removed' => 0, 'unchanged' => 0, 'missing' => 0, 'duplicate' => 0, 'bad_row' => 0];
        $lines = [];
        while (($r = fgetcsv($fh)) !== false) {
            $sku = trim((string) ($r[$ix['sku']] ?? ''));
            if ($sku === '') { continue; }
            $id = trim((string) ($r[$ix['block_id']] ?? ''));
            if (! preg_match('/^[a-z0-9_-]{2,40}$/i', $id)) { $n['bad_row']++; $lines[] = "SKIP {$sku}: bad block_id"; continue; }
            $html = trim((string) ($r[$ix['html']] ?? ''));
            $ps = Product::where('sku', $sku)->get(['id', 'sku', 'description']);
            if ($ps->count() === 0) { $n['missing']++; $lines[] = "MISSING {$sku}"; continue; }
            if ($ps->count() > 1) { $n['duplicate']++; $lines[] = "SKIP {$sku}: duplicate SKU"; continue; }
            $p = $ps->first();
            $old = (string) $p->description;
            $re = '/\s*<!--uf:'.preg_quote($id, '/').'-->.*?<!--\/uf:'.preg_quote($id, '/').'-->/s';
            $has = preg_match($re, $old) === 1;
            $base = $has ? (preg_replace($re, '', $old) ?? $old) : $old;
            $wrapped = "<!--uf:{$id}-->".$html."<!--/uf:{$id}-->";
            $new = $html === '' ? $base : (trim($base) === '' ? $wrapped : rtrim($base)."\n".$wrapped);
            if ($new === $old) { $n['unchanged']++; continue; }
            $kind = $html === '' ? 'removed' : ($has ? 'replaced' : 'added');
            if (! $dry) {
                file_put_contents($backup, json_encode(['id' => $p->id, 'sku' => $sku, 'description' => $old], JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);
                $p->description = $new;
                $p->save();
            }
            $n[$kind]++;
            $lines[] = ($dry ? 'WOULD ' : '').strtoupper($kind)." {$sku} (id {$p->id}) block {$id}: ".mb_strlen(strip_tags($old)).' -> '.mb_strlen(strip_tags($new)).' chars';
        }
        $head = '['.now()->toDateTimeString().'] '.($dry ? 'DRY RUN ' : '').json_encode($n).($dry ? '' : " backup: {$backup}");
        file_put_contents(storage_path('app/descriptions_block_report.txt'), $head."\n".implode("\n", $lines)."\n\n", FILE_APPEND);
        $this->info($head);

        return 0;
    }
}
