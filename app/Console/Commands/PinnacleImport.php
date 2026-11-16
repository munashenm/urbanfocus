<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Safe import of NEW Pinnacle products from a prepared, priced CSV.
 *
 *   php artisan pinnacle:import storage/app/pinnacle/import_1114.csv --dry-run
 *   php artisan pinnacle:import storage/app/pinnacle/import_1114.csv
 *
 * Guarantees:
 *  - New products are created UNPUBLISHED (is_active = 0), whatever the CSV says.
 *  - Never creates a duplicate: every row is checked against ALL products (incl. trashed)
 *    by normalised SKU, normalised model number (MPN) and barcode (EAN/UPC). Any match => the
 *    row is skipped and reported; the existing product is NOT touched (no name/description blanking).
 *  - Duplicate rows inside the CSV are skipped too.
 *  - Never passes a supplier product URL through (keeps supplier names off the storefront).
 *  - After a real run, appends "SKU<TAB>StockCode" for every product it created to
 *    storage/app/pinnacle/skus.txt (backup taken first) so the stock-only sync covers them.
 *  - --dry-run changes nothing (no DB writes, no skus.txt change, no image downloads).
 *
 * Expected CSV columns (header row): SKU, Name, Brand, Categories, Cost Price, Selling Price,
 * Stock, Images, Short Description, Description, Barcode, MPN, Supplier SKU [, Warranty Months]
 * Selling Price is incl. VAT and is used exactly as given.
 *
 * IMPORTANT: ImageService downloads images with file_get_contents(), which needs allow_url_fopen.
 * The cPanel PHP 8.3 CLI has it OFF, so always run:  php -d allow_url_fopen=1 artisan pinnacle:import ...
 * A real run refuses to start without it (v1114b), instead of failing every image.
 */
class PinnacleImport extends Command
{
    protected $signature = 'pinnacle:import
        {file=storage/app/pinnacle/import_1114.csv : Prepared CSV (path relative to the app root or absolute)}
        {--dry-run : Report only; change nothing}
        {--limit=0 : Only process the first N eligible rows (0 = all)}
        {--check-images=0 : Test-download the first N eligible images exactly like the importer does (no DB writes)}';

    protected $description = 'Create NEW Pinnacle products (unpublished, de-duplicated) from a prepared CSV and add them to the stock sync list';

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

        $dry = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));
        $file = (string) $this->argument('file');
        $path = str_starts_with($file, '/') ? $file : base_path($file);
        $dir = storage_path('app/pinnacle');
        @mkdir($dir, 0775, true);
        $stamp = date('Ymd_His');

        if (! is_readable($path)) {
            $this->error('CSV not found: '.$file);

            return self::FAILURE;
        }

        $lock = fopen($dir.'/import.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            $this->warn('Another pinnacle:import is running, exiting.');

            return self::SUCCESS;
        }

        $urlFopen = filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN);
        if (! $dry && ! $urlFopen) {
            $this->error('allow_url_fopen is OFF: every image download would fail. Re-run with: php -d allow_url_fopen=1 artisan pinnacle:import ...');

            return self::FAILURE;
        }

        // ---- read CSV
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

        // ---- index existing catalogue (incl. trashed) by normalised SKU / MPN / barcode
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

        // ---- filter
        $eligible = [];
        $report = [];
        $seen = [];
        $counts = ['rows' => count($rows), 'eligible' => 0, 'exists' => 0, 'dup_in_file' => 0, 'invalid' => 0];
        foreach ($rows as $i => $r) {
            $sku = trim($r['sku']);
            $mpn = trim($r['mpn'] ?? '');
            $bc = self::normBarcode($r['barcode'] ?? '');
            $price = (float) str_replace([',', 'R', ' '], '', $r['selling price']);
            $cost = (float) str_replace([',', 'R', ' '], '', $r['cost price']);

            if ($sku === '' || trim($r['name']) === '' || trim($r['brand']) === '' || trim($r['images']) === ''
                || $price <= 0 || $cost <= 0 || preg_match('/\s/', $sku) || preg_match('/\bpinn(pos|sec)\b/i', $r['brand'].' '.$r['name'])) {
                $counts['invalid']++;
                $report[] = "INVALID\t{$sku}\tmissing sku/name/brand/image/price, whitespace in SKU, or PinnPOS/PinnSec branding";

                continue;
            }
            // Refuse to sell below a no-loss price: price must cover cost (incl VAT) + card fee
            $net = $price / 1.15 - 0.039 * $price - 1 - ($price > 1500 ? 200 : 0);
            if ($net < $cost) {
                $counts['invalid']++;
                $report[] = sprintf("INVALID\t%s\tselling price R%.2f is below break-even for cost R%.2f", $sku, $price, $cost);

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
        $counts['allow_url_fopen'] = $urlFopen ? 'on' : 'off';

        // ---- optional image check: same call the importer's ImageService makes
        $checkN = max(0, (int) $this->option('check-images'));
        if ($checkN > 0) {
            $ok = 0;
            foreach (array_slice($eligible, 0, $checkN) as $r) {
                $url = trim(preg_split('/\s*[|,]\s*/', trim($r['images']))[0] ?? '');
                $ctx = stream_context_create(['http' => ['timeout' => 20, 'user_agent' => 'UrbanFocus-ProductImport/1.0'],
                    'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
                $t = microtime(true);
                $bytes = @file_get_contents($url, false, $ctx);
                $isImg = is_string($bytes) && $bytes !== '' && @getimagesizefromstring($bytes) !== false;
                $isImg && $ok++;
                $err = $isImg ? 'OK' : ('FAIL '.(error_get_last()['message'] ?? 'not an image'));
                $line = sprintf("IMAGE_CHECK\t%s\t%s\t%d bytes\t%.1fs", trim($r['sku']), $err, is_string($bytes) ? strlen($bytes) : 0, microtime(true) - $t);
                $report[] = $line;
                $this->line($line);
            }
            $counts['image_check'] = $ok.'/'.min($checkN, count($eligible)).' ok';
        }

        // ---- write the filtered CSV in the format ProductImportService understands
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
                '0',                                   // ALWAYS unpublished
                $stock > 0 ? '1' : '0', (string) $stock,
                trim($r['images']), trim($r['short description'] ?? ''), trim($r['description'] ?? ''),
                trim($r['barcode'] ?? ''), trim($r['mpn'] ?? ''),
                'Pinnacle', trim($r['supplier sku'] ?? '') ?: trim($r['sku']),
            ]);
        }
        fclose($out);
        @chmod($runCsv, 0600);

        $created = [];
        $result = [];
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
            foreach (($result['samples']['skipped'] ?? []) as $s) {
                $report[] = 'IMPORTER_SKIP_SAMPLE	'.json_encode($s);
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
            // Belt and braces: everything created by this run stays a draft.
            if ($created) {
                Product::query()->whereIn('id', array_keys($created))->update(['is_active' => false, 'import_source' => 'pinnacle']);
            }
            // Add to the stock-only sync list
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
                $txt = (is_file($skuFile) && filesize($skuFile) > 0 && ! str_ends_with(file_get_contents($skuFile), "\n")) ? "\n" : '';
                $txt .= '# pinnacle:import '.date('Y-m-d H:i').' — '.count($add)." new products (created unpublished)\n".implode("\n", $add)."\n";
                file_put_contents($skuFile, $txt, FILE_APPEND | LOCK_EX);
            }
            $summary = ['mode' => 'IMPORT'] + $counts + [
                'created' => count($created),
                'importer' => array_intersect_key($result, array_flip(['imported', 'updated', 'skipped', 'skippedNonIt', 'skippedNoImage', 'skippedImageFailed', 'skippedNoPrice'])),
                'errors' => $result['errors'] ?? [],
                'skus_txt_added' => count($add),
            ];
            foreach ($created as $id => $sku) {
                $report[] = "CREATED\t{$sku}\tproduct #{$id} (unpublished)";
            }
        }

        $reportFile = $dir.'/import_report_'.($dry ? 'dry_' : '')."{$stamp}.txt";
        file_put_contents($reportFile, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n\n".implode("\n", $report)."\n");
        @chmod($reportFile, 0600);
        Log::info('pinnacle:import '.json_encode(array_diff_key($summary, ['errors' => 1])));
        $this->line(json_encode($summary, JSON_UNESCAPED_SLASHES));
        $this->line('Report: '.str_replace(base_path().'/', '', $reportFile));

        return self::SUCCESS;
    }
}
