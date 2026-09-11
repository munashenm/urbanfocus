<?php

/**
 * Remove internal pricing / margin language from customer-facing product copy.
 *
 * 1. Copy this file to public_html/scrub-internal-copy.php
 * 2. Change SCRUB_KEY below to a secret (16+ characters, no CHANGE-ME)
 * 3. Open:
 *    https://www.urbanfocus.co.za/scrub-internal-copy.php?key=THE_SAME_SECRET_YOU_TYPED
 *    Do NOT use the word YOUR_SECRET in the URL.
 * 4. Use Preview, then Apply. The script cleans the catalogue in small batches.
 * 5. DELETE public_html/scrub-internal-copy.php when finished.
 */

declare(strict_types=1);

use App\Models\Product;
use App\Services\InternalPricingCopySanitizer;
use App\Services\SeoService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;

const SCRUB_KEY = 'CHANGE-ME-scrub-internal-copy-secret';
const BATCH_SIZE = 40;

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');
header('Content-Type: text/plain; charset=utf-8');

$providedKey = (string) ($_GET['key'] ?? '');

if (str_contains(SCRUB_KEY, 'CHANGE-ME') || strlen(SCRUB_KEY) < 16) {
    http_response_code(403);
    exit(
        "This file is not armed yet.\n\n"
        ."cPanel → File Manager → public_html → scrub-internal-copy.php → Edit\n"
        ."Change this line:\n"
        ."  const SCRUB_KEY = 'CHANGE-ME-scrub-internal-copy-secret';\n"
        ."to a secret of 16+ characters that does NOT contain CHANGE-ME.\n\n"
        ."Then open:\n"
        ."  https://www.urbanfocus.co.za/scrub-internal-copy.php?key=THE_SECRET_YOU_TYPED\n"
        ."Do not use YOUR_SECRET — that is only an example.\n"
    );
}

$placeholderKeys = ['', 'YOUR_SECRET', 'YOUR-SECRET', 'the_same_secret_you_typed', 'CHANGE-ME-scrub-internal-copy-secret'];
if (in_array(strtoupper($providedKey), array_map('strtoupper', $placeholderKeys), true) || ! hash_equals(SCRUB_KEY, $providedKey)) {
    http_response_code(403);
    exit(
        "Forbidden — the key in the URL does not match SCRUB_KEY in this file.\n\n"
        ."YOUR_SECRET is only an example. Copy the exact text between the quotes on this line:\n"
        ."  const SCRUB_KEY = '........';\n"
        ."from public_html/scrub-internal-copy.php, then visit:\n"
        ."  https://www.urbanfocus.co.za/scrub-internal-copy.php?key=PASTE_THAT_TEXT\n"
    );
}

@set_time_limit(120);
@ini_set('memory_limit', '512M');
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

$candidates = [
    dirname(__DIR__).'/urbanfocus',
    dirname(__DIR__),
    __DIR__,
];

$laravelRoot = null;
foreach ($candidates as $candidate) {
    if (is_file($candidate.'/bootstrap/app.php') && is_file($candidate.'/vendor/autoload.php')) {
        $laravelRoot = $candidate;
        break;
    }
}

if ($laravelRoot === null) {
    exit("Laravel root not found. Expected urbanfocus/ next to public_html.\n");
}

require $laravelRoot.'/vendor/autoload.php';
$app = require_once $laravelRoot.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$sanitizer = $app->make(InternalPricingCopySanitizer::class);
$apply = isset($_GET['apply']) && (string) $_GET['apply'] === '1';
$preview = isset($_GET['preview']) && (string) $_GET['preview'] === '1';
$afterId = max(0, (int) ($_GET['after'] ?? 0));
$totalsProcessed = max(0, (int) ($_GET['processed'] ?? 0));
$totalsUpdated = max(0, (int) ($_GET['updated'] ?? 0));
$backupName = preg_replace('/[^a-zA-Z0-9._-]/', '', (string) ($_GET['backup'] ?? '')) ?: '';

echo "Urban Focus — scrub internal pricing copy\n";
echo 'Laravel: '.$laravelRoot."\n";

if (! $apply && ! $preview) {
    $u7 = $sanitizer->inspectSku('U7-ENTERPRISE');
    echo "Authenticated. Catalogue has not been changed.\n\n";
    echo "=== U7-ENTERPRISE ===\n";
    if ($u7 === null) {
        echo "SKU not found.\n";
    } else {
        echo 'Name: '.$u7['name']."\n";
        echo 'Needs stored-copy scrub: '.($u7['needs_scrub'] ? 'YES' : 'no')."\n";
        echo 'Phrases: '.($u7['phrases'] === [] ? '(none)' : implode(' | ', $u7['phrases']))."\n";
        echo 'Excerpt: '.$u7['excerpt']."\n";
    }
    echo "\nNext steps (same key):\n";
    echo 'Preview: '.selfUrl($providedKey, ['preview' => '1'])."\n";
    echo 'Apply:   '.selfUrl($providedKey, ['apply' => '1'])."\n";
    echo "\nApply writes a JSONL backup under urbanfocus/storage/app/backups/ and cleans in batches of ".BATCH_SIZE.".\n";
    echo "DELETE public_html/scrub-internal-copy.php when finished.\n";
    exit;
}

$backupPath = null;
if ($apply) {
    $dir = $laravelRoot.'/storage/app/backups';
    if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
        exit("Unable to create backup directory: {$dir}\n");
    }
    if ($backupName === '' || ! str_ends_with($backupName, '.jsonl')) {
        $backupName = 'product-copy-'.date('Y-m-d-His').'.jsonl';
    }
    $backupPath = $dir.DIRECTORY_SEPARATOR.$backupName;
}

$chunk = scrubChunk($sanitizer, $apply, $afterId, BATCH_SIZE, $backupPath);
$totalsProcessed += $chunk['processed'];
$totalsUpdated += $chunk['updated'];

echo $apply ? "APPLYING batch\n" : "PREVIEW batch (no database writes)\n";
if ($backupPath) {
    echo 'Backup: '.$backupPath."\n";
}
echo 'After ID: '.$chunk['last_id']."\n";
echo "This batch scanned: {$chunk['processed']}\n";
echo ($apply ? 'This batch updated: ' : 'This batch would update: ').$chunk['updated']."\n";
foreach ($chunk['samples'] as $sample) {
    echo ($apply ? 'UPDATED   ' : 'WOULD     ').$sample."\n";
}
echo "Running totals — scanned {$totalsProcessed}, ".($apply ? 'updated' : 'would update')." {$totalsUpdated}\n";

if (! $chunk['done']) {
    $next = selfUrl($providedKey, [
        $apply ? 'apply' : 'preview' => '1',
        'after' => (string) $chunk['last_id'],
        'processed' => (string) $totalsProcessed,
        'updated' => (string) $totalsUpdated,
        'backup' => $backupName,
    ]);
    echo "\nContinuing automatically in 1 second…\n";
    echo $next."\n";
    echo '<meta http-equiv="refresh" content="1;url='.htmlspecialchars($next, ENT_QUOTES, 'UTF-8').'">';
    exit;
}

if ($apply && $totalsUpdated > 0) {
    try {
        $app->make(SeoService::class)->clearCache();
        Cache::forget('home.product_rows_v1');
        Cache::forget('home.product_rows_v2');
        echo "Storefront and feed caches cleared.\n";
    } catch (Throwable $e) {
        echo 'Cache clear warning: '.$e->getMessage()."\n";
    }
}

$u7 = $sanitizer->inspectSku('U7-ENTERPRISE');
$remaining = $sanitizer->auditCatalog();
echo "\n=== Finished ===\n";
echo 'U7-ENTERPRISE needs scrub: '.(($u7['needs_scrub'] ?? false) ? 'YES' : 'no')."\n";
echo 'U7 phrases: '.(($u7['phrases'] ?? []) === [] ? '(none)' : implode(' | ', $u7['phrases']))."\n";
echo 'Remaining catalogue leaks: '.count($remaining)."\n";
echo "\nDELETE public_html/scrub-internal-copy.php now.\n";

/**
 * @return array{processed:int,updated:int,last_id:int,done:bool,samples:list<string>}
 */
function scrubChunk(InternalPricingCopySanitizer $sanitizer, bool $apply, int $afterId, int $limit, ?string $backupPath): array
{
    $stats = [
        'processed' => 0,
        'updated' => 0,
        'last_id' => $afterId,
        'done' => true,
        'samples' => [],
    ];

    $products = Product::query()
        ->where('id', '>', $afterId)
        ->orderBy('id')
        ->limit($limit)
        ->get();

    $stats['done'] = $products->count() < $limit;

    foreach ($products as $product) {
        $stats['processed']++;
        $stats['last_id'] = (int) $product->id;

        if (! $sanitizer->needsScrub($product)) {
            continue;
        }

        if ($apply && $backupPath) {
            $row = json_encode([
                'id' => $product->id,
                'sku' => $product->sku,
                'slug' => $product->slug,
                'name' => $product->name,
                'short_description' => $product->short_description,
                'description' => $product->description,
                'meta_title' => $product->getAttributes()['meta_title'] ?? null,
                'meta_description' => $product->getAttributes()['meta_description'] ?? null,
                'meta_keywords' => $product->getAttributes()['meta_keywords'] ?? null,
                'specifications' => $product->specifications,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (is_string($row)) {
                file_put_contents($backupPath, $row."\n", FILE_APPEND | LOCK_EX);
            }
        }

        $updates = $sanitizer->scrubProduct($product, ! $apply);
        if ($updates === []) {
            continue;
        }

        $stats['updated']++;
        if (count($stats['samples']) < 20) {
            $stats['samples'][] = trim(($product->sku ? $product->sku.' ' : '').$product->name);
        }
    }

    return $stats;
}

function selfUrl(string $key, array $query): string
{
    $query['key'] = $key;
    $scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'www.urbanfocus.co.za';
    $path = strtok((string) ($_SERVER['REQUEST_URI'] ?? '/scrub-internal-copy.php'), '?') ?: '/scrub-internal-copy.php';

    return $scheme.'://'.$host.$path.'?'.http_build_query($query);
}
