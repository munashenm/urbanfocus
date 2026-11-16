<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ImageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * products:replace-images {csv} [--dry-run] [--min=500] [--max-side=1600]
 * CSV header: sku,image_urls,mode   (image_urls separated by |, first one becomes the main image)
 *   mode=replace : new images replace the product's current image rows (old rows backed up, files kept on disk)
 *   mode=add     : new images are added; the first new image becomes the main image
 * Every URL is downloaded and must be a real image of at least --min x --min pixels, otherwise it is skipped.
 * Images larger than --max-side are scaled down before saving (then stored as WebP by ImageService).
 * A product is only changed when at least one new image passes. Exact SKU match; missing/duplicate SKUs skipped.
 * Never touches price, stock, text, category or is_active.
 * Backup: storage/app/replace_images_backup_<ts>.jsonl   Report: storage/app/replace_images_report.txt
 * Needs: php -d allow_url_fopen=1
 */
class ProductReplaceImages extends Command
{
    protected $signature = 'products:replace-images {csv} {--dry-run : Download and check only} {--min=500 : Minimum width and height} {--max-side=1600 : Scale down larger images}';

    protected $description = 'Replace or add product images by SKU from verified image URLs (with backup)';

    public function handle(ImageService $images): int
    {
        $path = $this->argument('csv');
        $path = is_file($path) ? $path : base_path($path);
        if (! is_file($path)) { $this->error('CSV not found'); return 1; }
        if (! ini_get('allow_url_fopen')) { $this->error('Run with php -d allow_url_fopen=1'); return 1; }
        $dry = (bool) $this->option('dry-run');
        $min = max(1, (int) $this->option('min'));
        $maxSide = max($min, (int) $this->option('max-side'));
        $fh = fopen($path, 'r');
        $h = array_map(fn ($x) => strtolower(trim((string) $x, " \t\n\r\0\x0B\xEF\xBB\xBF")), fgetcsv($fh) ?: []);
        foreach (['sku', 'image_urls'] as $c) { if (! in_array($c, $h, true)) { $this->error("CSV needs column {$c}"); return 1; } }
        $ix = array_flip($h);
        $backup = storage_path('app/replace_images_backup_'.now()->format('Ymd_His').'.jsonl');
        $n = ['changed' => 0, 'missing' => 0, 'duplicate' => 0, 'no_valid_image' => 0, 'images_saved' => 0, 'images_rejected' => 0];
        $lines = [];

        while (($r = fgetcsv($fh)) !== false) {
            $sku = trim((string) ($r[$ix['sku']] ?? ''));
            if ($sku === '') { continue; }
            $mode = strtolower(trim((string) ($r[$ix['mode'] ?? -1] ?? 'replace'))) ?: 'replace';
            $urls = array_values(array_filter(array_map('trim', explode('|', (string) ($r[$ix['image_urls']] ?? '')))));
            $ps = Product::where('sku', $sku)->get();
            if ($ps->count() === 0) { $n['missing']++; $lines[] = "MISSING {$sku}"; continue; }
            if ($ps->count() > 1) { $n['duplicate']++; $lines[] = "SKIP {$sku}: duplicate SKU"; continue; }
            $p = $ps->first();

            $good = [];
            foreach ($urls as $url) {
                $bytes = $this->download($url);
                $info = $bytes !== null ? @getimagesizefromstring($bytes) : false;
                if (! $info) { $n['images_rejected']++; $lines[] = "  REJECT {$sku} not an image / download failed: {$url}"; continue; }
                [$w, $hgt] = $info;
                if ($w < $min || $hgt < $min) { $n['images_rejected']++; $lines[] = "  REJECT {$sku} {$w}x{$hgt} below {$min}px: {$url}"; continue; }
                $good[] = ['url' => $url, 'bytes' => $bytes, 'w' => $w, 'h' => $hgt];
                $lines[] = "  OK {$sku} {$w}x{$hgt}: {$url}";
            }
            if ($good === []) { $n['no_valid_image']++; $lines[] = "NO VALID IMAGE {$sku} (unchanged)"; continue; }

            if ($dry) {
                $n['changed']++;
                $lines[] = "WOULD ".strtoupper($mode)." {$sku} (id {$p->id}) with ".count($good).' image(s); current images: '.$p->images()->count();
                continue;
            }

            $saved = [];
            foreach ($good as $g) {
                $bytes = $this->scaleDown($g['bytes'], $g['w'], $g['h'], $maxSide);
                $stored = $images->storeProductImageFromBinary($bytes, $p->id, 'jpg');
                if ($stored) { $saved[] = $stored; } else { $lines[] = "  SAVE FAILED {$sku}: {$g['url']}"; }
            }
            if ($saved === []) { $n['no_valid_image']++; $lines[] = "SAVE FAILED {$sku} (unchanged)"; continue; }

            DB::transaction(function () use ($p, $saved, $mode, $backup, $sku) {
                $old = ProductImage::where('product_id', $p->id)->orderBy('sort_order')->get();
                file_put_contents($backup, json_encode(['id' => $p->id, 'sku' => $sku, 'mode' => $mode, 'old_images' => $old->map(fn ($i) => $i->only(['id', 'path', 'alt_text', 'sort_order', 'is_primary']))->all(), 'new_paths' => $saved], JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);
                if ($mode === 'replace') {
                    ProductImage::where('product_id', $p->id)->delete(); // rows only; files stay on disk for rollback
                    $sort = 0;
                } else {
                    ProductImage::where('product_id', $p->id)->update(['is_primary' => false]);
                    ProductImage::where('product_id', $p->id)->increment('sort_order', count($saved));
                    $sort = 0;
                }
                foreach ($saved as $i => $stored) {
                    ProductImage::create(['product_id' => $p->id, 'path' => $stored, 'alt_text' => $p->name, 'sort_order' => $sort + $i, 'is_primary' => $i === 0]);
                }
            });
            $n['changed']++; $n['images_saved'] += count($saved);
            $lines[] = strtoupper($mode)." {$sku} (id {$p->id}) saved ".count($saved).' image(s)';
        }

        $head = '['.now()->toDateTimeString().'] '.($dry ? 'DRY RUN ' : '').json_encode($n).($dry ? '' : " backup: {$backup}");
        file_put_contents(storage_path('app/replace_images_report.txt'), $head."\n".implode("\n", $lines)."\n\n", FILE_APPEND);
        $this->info($head);
        foreach ($lines as $l) { $this->line($l); }

        return 0;
    }

    private function download(string $url): ?string
    {
        if (! preg_match('#^https?://#i', $url)) { return null; }
        $ctx = stream_context_create(['http' => ['timeout' => 45, 'user_agent' => 'Mozilla/5.0 (UrbanFocus image check)', 'follow_location' => 1], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $data = @file_get_contents($url, false, $ctx);

        return is_string($data) && $data !== '' ? $data : null;
    }

    private function scaleDown(string $bytes, int $w, int $h, int $maxSide): string
    {
        if (max($w, $h) <= $maxSide || ! function_exists('imagecreatefromstring')) { return $bytes; }
        $src = @imagecreatefromstring($bytes);
        if ($src === false) { return $bytes; }
        $scale = $maxSide / max($w, $h);
        $nw = max(1, (int) round($w * $scale)); $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        ob_start(); imagejpeg($dst, null, 90); $out = ob_get_clean();
        imagedestroy($src); imagedestroy($dst);

        return is_string($out) && $out !== '' ? $out : $bytes;
    }
}
