<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ImageService;
use Illuminate\Console\Command;

/**
 * Replace product images from a CSV: SKU,product_id,image_urls (pipe-separated).
 * Downloads via ImageService (webp + thumbnail, same as the importer), makes the first new image
 * primary, and by default only DEMOTES old images (sort to end, not primary). --delete-old removes them.
 * Skips a row if no new image downloads. --dry-run reports only.
 */
class ReplaceProductImages extends Command
{
    protected $signature = 'images:replace {csv} {--dry-run} {--delete-old} {--limit=20 : rows per run (host limits)} {--offset=0}';

    protected $description = 'Replace product images from approved image URLs';

    public function handle(ImageService $images): int
    {
        $path = $this->argument('csv');
        $path = is_file($path) ? $path : base_path($path);
        $fh = is_file($path) ? fopen($path, 'r') : false;
        if (! $fh) { $this->error('CSV not found'); return 1; }
        $h = array_map(fn ($x) => strtolower(trim($x, " \t\n\r\0\x0B\xEF\xBB\xBF")), fgetcsv($fh));
        $iS = array_search('sku', $h); $iP = array_search('product_id', $h); $iU = array_search('image_urls', $h);
        $rows = [];
        while (($r = fgetcsv($fh)) !== false) { $rows[] = $r; }
        $rows = array_slice($rows, (int) $this->option('offset'), (int) $this->option('limit'));
        $log = [];
        foreach ($rows as $r) {
            $p = Product::find((int) $r[$iP]);
            if (! $p || trim((string) $p->sku) !== trim($r[$iS])) { $log[] = "SKIP {$r[$iS]}: product_id/SKU mismatch"; continue; }
            $urls = array_values(array_filter(array_map('trim', explode('|', $r[$iU]))));
            if (! $urls) { $log[] = "SKIP {$p->sku}: no URLs"; continue; }
            if ($this->option('dry-run')) { $log[] = "DRY {$p->sku} (id {$p->id}): would add ".count($urls).' image(s), demote '.$p->images()->count().' old'; continue; }
            $old = $p->images()->get();
            $new = [];
            foreach ($urls as $u) {
                if ($path = $images->storeProductImageFromUrl($u, $p->id)) { $new[] = $path; }
            }
            if (! $new) { $log[] = "FAIL {$p->sku}: no image downloaded, left unchanged"; continue; }
            foreach ($old as $o) { $o->update(['is_primary' => false, 'sort_order' => $o->sort_order + 1000]); }
            foreach ($new as $i => $path) {
                ProductImage::create(['product_id' => $p->id, 'path' => $path, 'alt_text' => $p->name, 'sort_order' => $i + 1, 'is_primary' => $i === 0]);
            }
            if ($this->option('delete-old')) {
                foreach ($old as $o) { $images->delete($o->path); $o->delete(); }
            }
            $p->touch();
            $log[] = "OK {$p->sku}: ".count($new).' new, '.count($old).' old '.($this->option('delete-old') ? 'deleted' : 'demoted');
            usleep(500000);
        }
        file_put_contents(storage_path('app/images_replace_report.txt'), '['.now()->toDateTimeString().'] '.($this->option('dry-run') ? 'DRY RUN ' : '').'offset '.$this->option('offset')."\n".implode("\n", $log)."\n\n", FILE_APPEND);
        $this->info(implode("\n", $log));
        return 0;
    }
}
