<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * Apply description fixes from CSV: SKU,mode,find,replace_html
 *   mode=remove  : remove the word in `find` (e.g. "Scoop ") from products.description, whole-word, case-sensitive.
 *                  Only description is touched (never name).
 *   mode=replace : replace products.description with replace_html.
 * Backs up every old description (JSON lines) to storage/app/descriptions_backup_<date>.jsonl before writing.
 * Report: storage/app/descriptions_apply_report.txt. Soft-deleted skipped; no product creation.
 */
class ApplyDescriptions extends Command
{
    protected $signature = 'descriptions:apply {csv} {--dry-run : Report only}';

    protected $description = 'Apply approved description removals/replacements by SKU';

    public function handle(): int
    {
        $path = $this->argument('csv');
        $path = is_file($path) ? $path : base_path($path);
        if (! is_file($path)) { $this->error('CSV not found'); return 1; }
        $fh = fopen($path, 'r');
        $h = array_map(fn ($x) => strtolower(trim((string) $x, " \t\n\r\0\x0B\xEF\xBB\xBF")), fgetcsv($fh) ?: []);
        foreach (['sku', 'mode', 'find', 'replace_html'] as $c) {
            if (! in_array($c, $h, true)) { $this->error("CSV needs column {$c}"); return 1; }
        }
        $ix = array_flip($h);
        $dry = (bool) $this->option('dry-run');
        $backup = storage_path('app/descriptions_backup_'.now()->format('Ymd_His').'.jsonl');
        $lines = []; $n = ['changed' => 0, 'no_match' => 0, 'missing' => 0, 'ambiguous' => 0, 'bad_row' => 0];
        while (($r = fgetcsv($fh)) !== false) {
            $sku = trim((string) ($r[$ix['sku']] ?? ''));
            $mode = trim((string) ($r[$ix['mode']] ?? ''));
            if ($sku === '') { continue; }
            $ps = Product::where('sku', $sku)->get(['id', 'sku', 'description']);
            if ($ps->count() === 0) { $n['missing']++; $lines[] = "MISSING {$sku}"; continue; }
            if ($ps->count() > 1) { $n['ambiguous']++; $lines[] = "SKIP {$sku}: duplicate SKU"; continue; }
            $p = $ps->first();
            $old = (string) $p->description;
            if ($mode === 'remove') {
                $word = trim((string) $r[$ix['find']]);
                if ($word === '') { $n['bad_row']++; continue; }
                $new = preg_replace('/\b'.preg_quote($word, '/').'(?:\s|&nbsp;)+/u', '', $old);
            } elseif ($mode === 'replace') {
                $new = trim((string) $r[$ix['replace_html']]);
                if (mb_strlen(strip_tags($new)) < 200) { $n['bad_row']++; $lines[] = "SKIP {$sku}: replacement too short"; continue; }
            } else { $n['bad_row']++; $lines[] = "SKIP {$sku}: unknown mode {$mode}"; continue; }
            if ($new === null || $new === $old) { $n['no_match']++; $lines[] = "NO CHANGE {$sku} ({$mode})"; continue; }
            if (! $dry) {
                file_put_contents($backup, json_encode(['id' => $p->id, 'sku' => $sku, 'description' => $old])."\n", FILE_APPEND);
                $p->description = $new;
                $p->save();
            }
            $n['changed']++;
            $lines[] = ($dry ? 'WOULD ' : '').strtoupper($mode)." {$sku} (id {$p->id}) ".mb_strlen(strip_tags($old)).' -> '.mb_strlen(strip_tags($new)).' chars';
        }
        $head = '['.now()->toDateTimeString().'] '.($dry ? 'DRY RUN ' : '').json_encode($n).($dry ? '' : " backup: {$backup}");
        file_put_contents(storage_path('app/descriptions_apply_report.txt'), $head."\n".implode("\n", $lines)."\n\n", FILE_APPEND);
        $this->info($head);
        return 0;
    }
}
