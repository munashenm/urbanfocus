<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * products:set-text {csv} [--dry-run] [--only-drafts] [--sku-file=]
 * CSV header: sku,name,brand,short_description,meta_title,meta_description,warranty_months
 * Any column may be omitted; a blank cell leaves that field unchanged.
 * - Matches products by exact SKU; skips missing and duplicate SKUs.
 * - --only-drafts: skips any product that is currently published (is_active = 1).
 * - --sku-file=: only update SKUs listed in this file (one SKU per line; # comments
 *   and blank lines ignored). Use this to target a known published set without
 *   relying on --only-drafts. CSV rows whose SKU is not in the file are skipped.
 * - brand: reuses an existing brand spelling (brands table / products.brand, case-insensitive),
 *   otherwise creates an active brands row (same behaviour as brands:apply).
 * - Never touches price, cost, stock, slug, images, category or is_active.
 * Backup: storage/app/set_text_backup_<ts>.jsonl. Report: storage/app/set_text_report.txt
 */
class ProductSetText extends Command
{
    protected $signature = 'products:set-text {csv} {--dry-run : Report only} {--only-drafts : Skip published products} {--sku-file= : Only update SKUs listed in this file (one SKU per line)}';

    protected $description = 'Set name/brand/short description/meta/warranty per SKU from a CSV (with backup)';

    private const FIELDS = ['name', 'brand', 'short_description', 'meta_title', 'meta_description', 'warranty_months'];

    public function handle(): int
    {
        $path = $this->argument('csv');
        $path = is_file($path) ? $path : base_path($path);
        if (! is_file($path)) { $this->error('CSV not found'); return 1; }

        $allow = $this->loadSkuAllowlist();
        if ($allow === false) { return 1; }

        $dry = (bool) $this->option('dry-run');
        $onlyDrafts = (bool) $this->option('only-drafts');
        $fh = fopen($path, 'r');
        $h = array_map(fn ($x) => strtolower(trim((string) $x, " \t\n\r\0\x0B\xEF\xBB\xBF")), fgetcsv($fh) ?: []);
        if (! in_array('sku', $h, true)) { $this->error('CSV needs a sku column'); return 1; }
        $ix = array_flip($h);
        $fields = array_values(array_filter(self::FIELDS, fn ($f) => isset($ix[$f]) && Schema::hasColumn('products', $f)));
        $missingCols = array_values(array_filter(self::FIELDS, fn ($f) => isset($ix[$f]) && ! Schema::hasColumn('products', $f)));
        $hasBrands = Schema::hasTable('brands') && class_exists(\App\Models\Brand::class);
        $backup = storage_path('app/set_text_backup_'.now()->format('Ymd_His').'.jsonl');
        $n = ['changed' => 0, 'unchanged' => 0, 'missing' => 0, 'duplicate' => 0, 'skipped_published' => 0, 'skipped_not_in_sku_file' => 0, 'brand_created' => 0];
        $lines = [];
        if ($missingCols) { $lines[] = 'NOTE columns not in products table, ignored: '.implode(',', $missingCols); }
        $resolved = [];
        while (($r = fgetcsv($fh)) !== false) {
            $sku = trim((string) ($r[$ix['sku']] ?? ''));
            if ($sku === '') { continue; }
            if (is_array($allow) && ! isset($allow[$sku])) { $n['skipped_not_in_sku_file']++; continue; }
            $ps = Product::where('sku', $sku)->get();
            if ($ps->count() === 0) { $n['missing']++; $lines[] = "MISSING {$sku}"; continue; }
            if ($ps->count() > 1) { $n['duplicate']++; $lines[] = "SKIP {$sku}: duplicate SKU"; continue; }
            $p = $ps->first();
            if ($onlyDrafts && $p->is_active) { $n['skipped_published']++; $lines[] = "SKIP {$sku}: published"; continue; }
            $changes = [];
            foreach ($fields as $f) {
                $v = trim((string) ($r[$ix[$f]] ?? ''));
                if ($v === '') { continue; }
                if ($f === 'warranty_months') { $v = (int) $v; if ($v < 1 || $v > 120) { continue; } }
                if ($f === 'meta_title') { $v = Str::limit($v, 255, ''); }
                if ($f === 'brand') {
                    $key = mb_strtolower($v);
                    if (! isset($resolved[$key])) {
                        $name = $hasBrands ? \App\Models\Brand::whereRaw('LOWER(name) = ?', [$key])->value('name') : null;
                        $name ??= Product::whereRaw('LOWER(brand) = ?', [$key])->value('brand');
                        if ($name === null && $hasBrands) {
                            $n['brand_created']++; $lines[] = ($dry ? 'WOULD CREATE' : 'CREATE')." brand '{$v}'";
                            if (! $dry) {
                                $slug = Str::slug($v); $i = 2; while (\App\Models\Brand::where('slug', $slug)->exists()) { $slug = Str::slug($v).'-'.$i++; }
                                \App\Models\Brand::create(['name' => $v, 'slug' => $slug, 'is_active' => true]);
                            }
                        }
                        $resolved[$key] = $name ?? $v;
                    }
                    $v = $resolved[$key];
                }
                $old = $p->getAttributes()[$f] ?? null;
                if ((string) $old !== (string) $v) { $changes[$f] = $v; }
            }
            if (! $changes) { $n['unchanged']++; continue; }
            $bak = ['id' => $p->id, 'sku' => $sku];
            foreach ($changes as $f => $v) { $bak[$f] = $p->getAttributes()[$f] ?? null; }
            if (! $dry) {
                file_put_contents($backup, json_encode($bak, JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);
                foreach ($changes as $f => $v) { $p->{$f} = $v; }
                $p->save();
            }
            $n['changed']++;
            $desc = [];
            foreach ($changes as $f => $v) { $desc[] = $f === 'name' || $f === 'brand' ? "{$f}: '".Str::limit((string) ($bak[$f] ?? ''), 60)."' -> '".Str::limit((string) $v, 60)."'" : $f; }
            $lines[] = ($dry ? 'WOULD ' : '')."SET {$sku} (id {$p->id}) ".implode('; ', $desc);
        }
        $tag = ($dry ? 'DRY RUN ' : '').($onlyDrafts ? '(only drafts) ' : '').(is_array($allow) ? '(sku-file '.count($allow).') ' : '');
        $head = '['.now()->toDateTimeString().'] '.$tag.json_encode($n).($dry ? '' : " backup: {$backup}");
        file_put_contents(storage_path('app/set_text_report.txt'), $head."\n".implode("\n", $lines)."\n\n", FILE_APPEND);
        $this->info($head);

        return 0;
    }

    /** @return array<string,true>|null|false  null = no filter; false = error already reported */
    private function loadSkuAllowlist()
    {
        $skuFile = $this->option('sku-file');
        if ($skuFile === null || $skuFile === false || $skuFile === '') {
            return null;
        }
        $sf = is_file($skuFile) ? $skuFile : base_path((string) $skuFile);
        if (! is_file($sf)) { $this->error('SKU file not found'); return false; }
        $allow = [];
        foreach (file($sf, FILE_IGNORE_NEW_LINES) as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#')) { continue; }
            $sku = trim(strtok($line, "\t"));
            if ($sku !== '') { $allow[$sku] = true; }
        }
        if (! $allow) { $this->error('SKU file is empty'); return false; }

        return $allow;
    }
}
