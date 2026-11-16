<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * brands:apply {csv} [--dry-run] [--old="MiRO (unbranded)"]
 * CSV: product_id,sku,new_brand. Row applies only when id AND sku match AND current brand == --old.
 * - Sets products.brand (string column; Brand model links products by brand name).
 * - Reuses an existing brand spelling (brands table or products.brand, case-insensitive), else
 *   creates a brands row (Generic is created inactive).
 * - Strips the old brand text from name, meta_title, meta_description, short_description, description.
 * - After all rows, deactivates the old brand record if no products still use it.
 * Backup: storage/app/brands_backup_<ts>.jsonl. Report: storage/app/brands_apply_report.txt
 */
class ApplyBrands extends Command
{
    protected $signature = 'brands:apply {csv} {--dry-run : Report only} {--old=MiRO (unbranded) : Brand text to replace}';

    protected $description = 'Replace a supplier-placeholder brand with the real maker per product';

    public function handle(): int
    {
        $path = $this->argument('csv');
        $path = is_file($path) ? $path : base_path($path);
        if (! is_file($path)) { $this->error('CSV not found'); return 1; }
        if (! Schema::hasColumn('products', 'brand')) { $this->error('products.brand column not found - schema differs; columns: '.implode(',', Schema::getColumnListing('products'))); return 1; }
        $old = (string) $this->option('old');
        $dry = (bool) $this->option('dry-run');
        $hasBrands = Schema::hasTable('brands');
        $textCols = array_values(array_filter(['name', 'meta_title', 'meta_description', 'short_description', 'description'], fn ($c) => Schema::hasColumn('products', $c)));
        $oldRx = '/\s*\b'.preg_quote($old, '/').'(?:\s*[-:|,])?\s*/iu';
        $fh = fopen($path, 'r');
        $h = array_map(fn ($x) => strtolower(trim((string) $x, " \t\n\r\0\x0B\xEF\xBB\xBF")), fgetcsv($fh) ?: []);
        foreach (['product_id', 'sku', 'new_brand'] as $c) { if (! in_array($c, $h, true)) { $this->error("CSV needs {$c}"); return 1; } }
        $ix = array_flip($h);
        $backup = storage_path('app/brands_backup_'.now()->format('Ymd_His').'.jsonl');
        $n = ['changed' => 0, 'missing' => 0, 'skipped_brand_differs' => 0, 'brand_created' => 0];
        $lines = []; $resolved = [];
        while (($r = fgetcsv($fh)) !== false) {
            $id = (int) ($r[$ix['product_id']] ?? 0); $sku = trim((string) ($r[$ix['sku']] ?? '')); $want = trim((string) ($r[$ix['new_brand']] ?? ''));
            if ($id === 0 || $sku === '' || $want === '') { continue; }
            $p = Product::where('id', $id)->where('sku', $sku)->first();
            if (! $p) { $n['missing']++; $lines[] = "MISSING {$id} {$sku}"; continue; }
            if (mb_strtolower(trim((string) $p->brand)) !== mb_strtolower($old)) { $n['skipped_brand_differs']++; $lines[] = "SKIP {$sku}: brand is '{$p->brand}'"; continue; }
            if (! isset($resolved[mb_strtolower($want)])) {
                $name = null;
                if ($hasBrands) { $name = \App\Models\Brand::whereRaw('LOWER(name) = ?', [mb_strtolower($want)])->value('name'); }
                $name ??= Product::whereRaw('LOWER(brand) = ?', [mb_strtolower($want)])->value('brand');
                if ($name === null && $hasBrands) {
                    $n['brand_created']++; $lines[] = ($dry ? 'WOULD CREATE' : 'CREATE')." brand '{$want}'";
                    if (! $dry) {
                        $slug = Str::slug($want); $i = 2; while (\App\Models\Brand::where('slug', $slug)->exists()) { $slug = Str::slug($want).'-'.$i++; }
                        \App\Models\Brand::create(['name' => $want, 'slug' => $slug, 'is_active' => strtolower($want) !== 'generic']);
                    }
                }
                $resolved[mb_strtolower($want)] = $name ?? $want;
            }
            $brand = $resolved[mb_strtolower($want)];
            $bak = ['id' => $p->id, 'sku' => $sku, 'brand' => $p->brand];
            $changes = ['brand' => $brand];
            foreach ($textCols as $c) {
                $v = (string) ($p->getAttributes()[$c] ?? '');
                if ($v !== '' && stripos($v, $old) !== false) {
                    $v2 = preg_replace('/\bGenuine\s+'.preg_quote($old, '/').'/iu', 'Genuine '.(strtolower($brand) === 'generic' ? '' : $brand), $v);
                    $nv = trim(preg_replace('/\s{2,}/u', ' ', preg_replace($oldRx, ' ', $v2)));
                    $bak[$c] = $v; $changes[$c] = $nv;
                }
            }
            if (! $dry) {
                file_put_contents($backup, json_encode($bak)."\n", FILE_APPEND);
                foreach ($changes as $c => $v) { $p->{$c} = $v; }
                $p->save();
            }
            $n['changed']++;
            $lines[] = ($dry ? 'WOULD ' : '')."SET {$sku} (id {$p->id}) brand '{$p->getOriginal('brand')}' -> '{$brand}'; text fields cleaned: ".implode(',', array_diff(array_keys($changes), ['brand']));
        }
        $left = Product::whereRaw('LOWER(brand) = ?', [mb_strtolower($old)])->count();
        $lines[] = "Products still with brand '{$old}' after run: ".($dry ? '(dry run) ' : '').$left;
        if ($hasBrands && ! $dry && $left === 0) {
            $b = \App\Models\Brand::whereRaw('LOWER(name) = ?', [mb_strtolower($old)])->first();
            if ($b && $b->is_active) { file_put_contents($backup, json_encode(['brand_record' => $b->id, 'is_active' => true])."\n", FILE_APPEND); $b->is_active = false; $b->save(); $lines[] = "Deactivated brand record {$b->id} '{$b->name}'"; }
        }
        $head = '['.now()->toDateTimeString().'] '.($dry ? 'DRY RUN ' : '').json_encode($n).($dry ? '' : " backup: {$backup}");
        file_put_contents(storage_path('app/brands_apply_report.txt'), $head."\n".implode("\n", $lines)."\n\n", FILE_APPEND);
        $this->info($head);
        return 0;
    }
}
