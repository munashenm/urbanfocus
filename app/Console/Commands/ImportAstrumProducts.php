<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\AstrumApi;
use App\Services\ProductImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Safe import of NEW Astrum products from a prepared, priced CSV.
 *
 *   php -d allow_url_fopen=1 artisan astrum:import storage/app/astrum/import_batch1_1115.csv --dry-run
 *   php -d allow_url_fopen=1 artisan astrum:import storage/app/astrum/import_batch1_1115.csv
 *
 * New products are always unpublished (is_active = 0). Duplicates (SKU, MPN, barcode,
 * including trashed) are skipped. Selling prices are recalculated from cost with the agreed tiered margin (20% / 15% / 10% by resulting price, no FirstShop cap) and refused if they fall below the no-loss floor.
 * Created SKUs are appended to storage/app/astrum/skus.txt for astrum:sync.
 */
class ImportAstrumProducts extends Command
{
    protected $signature = 'astrum:import
        {file=storage/app/astrum/import_batch1_1115.csv : Prepared CSV}
        {--dry-run : Report only; change nothing}
        {--limit=0 : Only process the first N eligible rows (0 = all)}
        {--check-images=0 : HEAD-check the first N image URLs and include the result in the summary}';

    protected $description = 'Create NEW Astrum products (unpublished, de-duplicated) from a prepared CSV and add them to the Astrum sync list';

    public bool $skipHashGate = false;

    public static function norm(?string $v): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string) $v)));
    }

    public static function normBarcode(?string $v): string
    {
        $d = ltrim(preg_replace('/\D/', '', (string) $v), '0');

        return strlen($d) >= 8 ? $d : '';
    }

    public function handle(ProductImportService $import): int
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(0);

        if ($gate = $this->hashGate()) {
            $this->error($gate);

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));
        $file = (string) $this->argument('file');
        $path = str_starts_with($file, '/') ? $file : base_path($file);
        $dir = storage_path('app/astrum');
        @mkdir($dir, 0775, true);
        $stamp = date('Ymd_His');

        if (! is_readable($path)) {
            $this->error('CSV not found: '.$file);

            return self::FAILURE;
        }

        $lock = fopen($dir.'/import.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            $this->warn('Another astrum:import is running, exiting.');

            return self::SUCCESS;
        }

        $fh = fopen($path, 'r');
        $header = fgetcsv($fh);
        if (! $header) {
            $this->error('CSV is empty.');

            return self::FAILURE;
        }
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
        foreach (['sku', 'name', 'brand', 'categories', 'cost price', 'selling price', 'images'] as $req) {
            if (! in_array($req, $header, true)) {
                $this->error("CSV is missing required column: {$req}");

                return self::FAILURE;
            }
        }
        $rows = [];
        while (($r = fgetcsv($fh)) !== false) {
            if (count(array_filter($r, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $r = array_pad(array_slice($r, 0, count($header)), count($header), '');
            $rows[] = array_combine($header, $r);
        }
        fclose($fh);

        $bySku = $byMpn = $byBarcode = [];
        Product::withTrashed()->select(['id', 'sku', 'model_number', 'barcode', 'supplier_sku'])
            ->orderBy('id')->chunk(2000, function ($chunk) use (&$bySku, &$byMpn, &$byBarcode) {
                foreach ($chunk as $p) {
                    foreach ([$p->sku, $p->supplier_sku] as $s) {
                        if (($k = self::norm($s)) !== '') {
                            $bySku[$k] ??= $p->sku ?: ('#'.$p->id);
                        }
                    }
                    if (strlen($k = self::norm($p->model_number)) >= 4) {
                        $byMpn[$k] ??= $p->sku ?: ('#'.$p->id);
                    }
                    if (($b = self::normBarcode($p->barcode)) !== '') {
                        $byBarcode[$b] ??= $p->sku ?: ('#'.$p->id);
                    }
                }
            });

        $eligible = [];
        $report = [];
        $seen = [];
        $counts = ['rows' => count($rows), 'eligible' => 0, 'exists' => 0, 'dup_in_file' => 0, 'invalid' => 0];
        foreach ($rows as $r) {
            $sku = trim($r['sku']);
            $mpn = trim($r['mpn'] ?? '');
            $bc = self::normBarcode($r['barcode'] ?? '');
            $cost = (float) str_replace([',', 'R', ' '], '', $r['cost price']);
            // Selling price is always the agreed tiered margin (no FirstShop cap), never below no-loss.
            $tiered = $cost > 0 ? AstrumApi::tieredPrice($cost) : ['price' => 0, 'basis' => 'no cost'];
            $price = (int) ($tiered['price'] ?? 0);
            $r['selling price'] = (string) $price;

            $brandName = trim($r['brand']);
            $blob = $brandName.' '.$r['name'].' '.($r['short description'] ?? '').' '.($r['description'] ?? '');
            if ($sku === '' || trim($r['name']) === '' || $brandName === '' || trim($r['images']) === ''
                || $cost <= 0 || $price <= 0 || preg_match('/\s/', $sku)
                || preg_match('/\b(pinn(acle|pos|sec)|firstshop)\b/i', $blob)) {
                $counts['invalid']++;
                $report[] = "INVALID\t{$sku}\tmissing sku/name/brand/image/cost, whitespace in SKU, or forbidden supplier wording";
                continue;
            }
            if (! AstrumApi::breakEvenOk($price, $cost)) {
                $counts['invalid']++;
                $report[] = sprintf("INVALID\t%s\ttiered price R%.2f is below break-even for cost R%.2f", $sku, $price, $cost);
                continue;
            }

            $keys = array_filter(['sku' => self::norm($sku), 'mpn' => self::norm($mpn)]);
            $hit = null;
            foreach ($keys as $k) {
                if (isset($bySku[$k])) {
                    $hit = 'SKU/MPN '.$k.' = existing '.$bySku[$k];
                    break;
                }
                if (strlen($k) >= 4 && isset($byMpn[$k])) {
                    $hit = 'SKU/MPN '.$k.' = existing model '.$byMpn[$k];
                    break;
                }
            }
            if (! $hit && $bc !== '' && isset($byBarcode[$bc])) {
                $hit = 'barcode '.$bc.' = existing '.$byBarcode[$bc];
            }
            if ($hit) {
                $counts['exists']++;
                $report[] = "EXISTS\t{$sku}\t{$hit} (left untouched)";
                continue;
            }
            $fileKeys = array_merge(array_values($keys), $bc !== '' ? ['B'.$bc] : []);
            foreach ($fileKeys as $k) {
                if (isset($seen[$k])) {
                    $hit = $seen[$k];
                    break;
                }
            }
            if ($hit) {
                $counts['dup_in_file']++;
                $report[] = "DUP_IN_FILE\t{$sku}\tsame as row {$hit}";
                continue;
            }
            foreach ($fileKeys as $k) {
                $seen[$k] = $sku;
            }
            $eligible[] = $r;
            if ($limit && count($eligible) >= $limit) {
                break;
            }
        }
        $counts['eligible'] = count($eligible);

        $checkN = max(0, (int) $this->option('check-images'));
        $imageCheck = null;
        if ($checkN && $eligible) {
            $ok = 0;
            $n = 0;
            foreach ($eligible as $r) {
                if ($n >= $checkN) {
                    break;
                }
                $url = trim(preg_split('/\s*[|,]\s*/', (string) $r['images'])[0] ?? '');
                $n++;
                if ($url === '' || ! preg_match('#^https?://#i', $url)) {
                    continue;
                }
                $hdr = @get_headers($url, true);
                $code = is_array($hdr) ? (int) preg_replace('/\D/', '', explode(' ', (string) $hdr[0], 3)[1] ?? '0') : 0;
                if ($code >= 200 && $code < 400) {
                    $ok++;
                }
            }
            $imageCheck = "{$ok}/{$n} ok";
        }

        $runCsv = $dir."/import_run_{$stamp}.csv";
        $out = fopen($runCsv, 'w');
        $cols = ['SKU', 'Name', 'Brand', 'Categories', 'Cost Price', 'Selling Price', 'Published', 'In stock?', 'Stock',
            'Images', 'Short Description', 'Description', 'Barcode', 'MPN', 'Supplier Name', 'Supplier SKU'];
        fputcsv($out, $cols);
        foreach ($eligible as $r) {
            $stock = (int) preg_replace('/\D/', '', (string) ($r['stock'] ?? '0'));
            fputcsv($out, [
                trim($r['sku']), trim($r['name']), trim($r['brand']), trim($r['categories']),
                trim($r['cost price']), trim($r['selling price']),
                '0',
                $stock > 0 ? '1' : '0', (string) $stock,
                trim($r['images']), trim($r['short description'] ?? ''), trim($r['description'] ?? ''),
                trim($r['barcode'] ?? ''), trim($r['mpn'] ?? ''),
                'Astrum', trim($r['supplier sku'] ?? '') ?: trim($r['sku']),
            ]);
        }
        fclose($out);
        @chmod($runCsv, 0600);

        $created = [];
        $skuFile = $dir.'/skus.txt';
        $existingSyncLines = [];
        if (is_readable($skuFile)) {
            foreach (file($skuFile, FILE_IGNORE_NEW_LINES) as $line) {
                $p = explode("\t", trim($line));
                if ($p[0] !== '' && ! str_starts_with($p[0], '#')) {
                    $existingSyncLines[strtoupper($p[0])] = true;
                }
            }
        }

        if ($dry) {
            $result = $counts['eligible'] ? $import->previewFromPath($runCsv, 20) : [];
            $toAdd = array_values(array_filter(array_map(fn ($r) => trim($r['sku']), $eligible), fn ($s) => ! isset($existingSyncLines[strtoupper($s)])));
            $summary = ['mode' => 'DRY-RUN'] + $counts + [
                'would_create' => $result['would_create'] ?? 0,
                'would_update' => $result['would_update'] ?? 0,
                'importer_skips' => array_intersect_key($result, array_flip(['skippedNonIt', 'skippedNoImage', 'skippedNoPrice', 'skippedNoSku', 'skippedNoBrand', 'skippedNoTitle'])),
                'errors' => $result['errors'] ?? [],
                'skus_txt_would_add' => count($toAdd),
            ];
            if ($imageCheck !== null) {
                $summary['image_check'] = $imageCheck;
            }
            @unlink($runCsv);
        } else {
            $t0 = now()->subSecond();
            $result = $counts['eligible'] ? $import->importFromPath($runCsv) : [];
            $skus = array_map(fn ($r) => trim($r['sku']), $eligible);
            $stockcode = [];
            foreach ($eligible as $r) {
                $stockcode[trim($r['sku'])] = trim($r['supplier sku'] ?? '') ?: trim($r['sku']);
            }
            foreach (array_chunk($skus, 500) as $part) {
                Product::query()->whereIn('sku', $part)->where('created_at', '>=', $t0)
                    ->get(['id', 'sku', 'is_active'])->each(function ($p) use (&$created) {
                        $created[$p->id] = $p->sku;
                    });
            }
            if ($created) {
                Product::query()->whereIn('id', array_keys($created))->update(['is_active' => false, 'import_source' => 'astrum']);
            }
            $add = [];
            foreach ($created as $sku) {
                if (! isset($existingSyncLines[strtoupper($sku)])) {
                    $add[] = $sku."\t".($stockcode[$sku] ?? $sku);
                }
            }
            if ($add) {
                if (is_file($skuFile)) {
                    copy($skuFile, $dir."/skus.txt.bak_{$stamp}");
                }
                $txt = (is_file($skuFile) && filesize($skuFile) > 0 && ! str_ends_with((string) file_get_contents($skuFile), "\n")) ? "\n" : '';
                $txt .= '# astrum:import '.date('Y-m-d H:i').' — '.count($add)." new products (created unpublished)\n".implode("\n", $add)."\n";
                file_put_contents($skuFile, $txt, FILE_APPEND | LOCK_EX);
            }
            $summary = ['mode' => 'IMPORT'] + $counts + [
                'created' => count($created),
                'importer' => array_intersect_key($result, array_flip(['imported', 'updated', 'skipped', 'skippedNonIt', 'skippedNoImage', 'skippedImageFailed', 'skippedNoPrice'])),
                'errors' => $result['errors'] ?? [],
                'skus_txt_added' => count($add),
            ];
            if ($imageCheck !== null) {
                $summary['image_check'] = $imageCheck;
            }
            foreach ($created as $id => $sku) {
                $report[] = "CREATED\t{$sku}\tproduct #{$id} (unpublished)";
            }
        }

        $reportFile = $dir.'/import_report_'.($dry ? 'dry_' : '')."{$stamp}.txt";
        file_put_contents($reportFile, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n\n".implode("\n", $report)."\n");
        @chmod($reportFile, 0600);
        Log::info('astrum:import '.json_encode(array_diff_key($summary, ['errors' => 1])));
        $this->line(json_encode($summary, JSON_UNESCAPED_SLASHES));
        $this->line('Report: '.str_replace(base_path().'/', '', $reportFile));

        return self::SUCCESS;
    }

    private function hashGate(): ?string
    {
        if ($this->skipHashGate) {
            return null;
        }
        $rel = 'app/Services/ProductImportService.php';
        $path = base_path($rel);
        if (! is_file($path)) {
            return "md5 gate: {$rel} is missing. Refusing to run.";
        }
        $hash = md5_file($path);
        $allowed = config('astrum.import_new_service_hashes') ?: [];
        if (! in_array($hash, $allowed, true)) {
            return "md5 gate: {$rel} is {$hash}, which is not a known tested version. Refusing to run. Do not overwrite it.";
        }

        return null;
    }
}
