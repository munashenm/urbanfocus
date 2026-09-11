<?php

/**
 * Remove internal pricing language from customer-facing product copy.
 *
 * 1. Upload THIS file to public_html/scrub-internal-copy.php
 *    (not urbanfocus/deploy/ — it must sit next to index.php)
 * 2. Edit ONLY this line and use letters/numbers, no spaces or quotes:
 *      const SCRUB_KEY = 'UrbanFocusScrub2026ok';
 * 3. Open: https://www.urbanfocus.co.za/scrub-internal-copy.php
 *    Type the same password in the box. Do not put it in the URL.
 * 4. Preview, then Apply. Delete this file when finished.
 */

declare(strict_types=1);

use App\Models\Product;
use App\Services\InternalPricingCopySanitizer;
use App\Services\SeoService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;

const SCRUB_KEY = 'CHANGE-ME-scrub-internal-copy-secret';
const BATCH_SIZE = 40;
const COOKIE_NAME = 'uf_scrub_auth';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0, must-revalidate');
header('Pragma: no-cache');

$expectedKey = normalize_secret(SCRUB_KEY);
$providedKey = normalize_secret((string) (
    $_POST['token']
    ?? $_GET['token']
    ?? $_GET['key']
    ?? $_GET['k']
    ?? ''
));

if (str_contains($expectedKey, 'CHANGE-ME') || strlen($expectedKey) < 16) {
    html_page('Set a password in the file first', '<p>Open <code>public_html/scrub-internal-copy.php</code> in File Manager and change:</p>'
        .'<pre>const SCRUB_KEY = \'CHANGE-ME-scrub-internal-copy-secret\';</pre>'
        .'<p>to a password of 16+ letters and numbers, for example:</p>'
        .'<pre>const SCRUB_KEY = \'UrbanFocusScrub2026ok\';</pre>'
        .'<p>Save the file, then reload this page and type that password in the box. Do not put it in the URL.</p>');
}

$cookieOk = isset($_COOKIE[COOKIE_NAME]) && hash_equals(auth_cookie_value($expectedKey), (string) $_COOKIE[COOKIE_NAME]);
$keyOk = $providedKey !== '' && hash_equals($expectedKey, $providedKey);
$authed = $cookieOk || $keyOk;

if (! $authed) {
    $hint = '';
    if ($providedKey !== '') {
        $hint = '<p><strong>Password did not match.</strong> Typed '.strlen($providedKey)
            .' characters; file has '.strlen($expectedKey)
            .' characters. Copy the text between the quotes only — no spaces, no extra quotes.</p>';
    }
    html_page('Enter password', $hint
        .'<form method="post" action="scrub-internal-copy.php">'
        .'<p><input type="password" name="token" autocomplete="current-password" style="width:100%;max-width:28rem;padding:10px;font-size:16px" required></p>'
        .'<p><button type="submit" style="padding:10px 18px">Continue</button></p>'
        .'</form>'
        .'<p>Edit the password in <code>public_html/scrub-internal-copy.php</code> on the <code>const SCRUB_KEY</code> line.</p>');
}

setcookie(COOKIE_NAME, auth_cookie_value($expectedKey), [
    'expires' => time() + 3600,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);

@set_time_limit(120);
@ini_set('memory_limit', '512M');

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
    html_page('Laravel root not found', '<p>Expected <code>urbanfocus/</code> next to <code>public_html</code>.</p>', 500);
}

require $laravelRoot.'/vendor/autoload.php';
$app = require_once $laravelRoot.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$sanitizer = $app->make(InternalPricingCopySanitizer::class);
$apply = (($_POST['apply'] ?? $_GET['apply'] ?? '') === '1');
$preview = (($_POST['preview'] ?? $_GET['preview'] ?? '') === '1');
$afterId = max(0, (int) ($_POST['after'] ?? $_GET['after'] ?? 0));
$totalsProcessed = max(0, (int) ($_POST['processed'] ?? $_GET['processed'] ?? 0));
$totalsUpdated = max(0, (int) ($_POST['updated'] ?? $_GET['updated'] ?? 0));
$backupName = preg_replace('/[^a-zA-Z0-9._-]/', '', (string) ($_POST['backup'] ?? $_GET['backup'] ?? '')) ?: '';

if (! $apply && ! $preview) {
    $u7 = $sanitizer->inspectSku('U7-ENTERPRISE');
    $u7Block = $u7 === null
        ? '<p>SKU U7-ENTERPRISE not found.</p>'
        : '<p><strong>'.e($u7['name']).'</strong><br>Needs stored-copy scrub: '
            .($u7['needs_scrub'] ? '<span style="color:#b00">YES</span>' : 'no')
            .'<br>Phrases: '.e($u7['phrases'] === [] ? '(none)' : implode(' | ', $u7['phrases']))
            .'<br>Excerpt: '.e($u7['excerpt']).'</p>';

    html_page('Scrub internal pricing copy', '<p>Logged in. The catalogue has not been changed yet.</p>'
        .'<h2>U7-ENTERPRISE</h2>'.$u7Block
        .'<form method="post" style="display:inline-block;margin-right:12px">'
        .'<input type="hidden" name="token" value="'.e($expectedKey).'">'
        .'<input type="hidden" name="preview" value="1">'
        .'<button type="submit" style="padding:10px 18px">Preview</button>'
        .'</form>'
        .'<form method="post" style="display:inline-block" onsubmit="return confirm(\'Backup affected rows, then clean stored product copy?\')">'
        .'<input type="hidden" name="token" value="'.e($expectedKey).'">'
        .'<input type="hidden" name="apply" value="1">'
        .'<button type="submit" style="padding:10px 18px">Apply cleanup</button>'
        .'</form>'
        .'<p>Delete <code>public_html/scrub-internal-copy.php</code> when finished.</p>');
}

$backupPath = null;
if ($apply) {
    $dir = $laravelRoot.'/storage/app/backups';
    if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
        html_page('Backup failed', '<p>Unable to create '.$dir.'</p>', 500);
    }
    if ($backupName === '' || ! str_ends_with($backupName, '.jsonl')) {
        $backupName = 'product-copy-'.date('Y-m-d-His').'.jsonl';
    }
    $backupPath = $dir.DIRECTORY_SEPARATOR.$backupName;
}

$chunk = scrub_chunk($sanitizer, $apply, $afterId, BATCH_SIZE, $backupPath);
$totalsProcessed += $chunk['processed'];
$totalsUpdated += $chunk['updated'];

if (! $chunk['done']) {
    $fields = [
        'token' => $expectedKey,
        $apply ? 'apply' : 'preview' => '1',
        'after' => (string) $chunk['last_id'],
        'processed' => (string) $totalsProcessed,
        'updated' => (string) $totalsUpdated,
        'backup' => $backupName,
    ];
    $hidden = '';
    foreach ($fields as $name => $value) {
        $hidden .= '<input type="hidden" name="'.e($name).'" value="'.e($value).'">';
    }
    html_page(
        $apply ? 'Applying…' : 'Previewing…',
        '<p>'.($apply ? 'APPLYING' : 'PREVIEW').' batch ending at product ID '.$chunk['last_id'].'</p>'
        .'<p>This batch scanned '.$chunk['processed'].', '
        .($apply ? 'updated' : 'would update').' '.$chunk['updated'].'.</p>'
        .'<p>Running totals: scanned '.$totalsProcessed.', '
        .($apply ? 'updated' : 'would update').' '.$totalsUpdated.'.</p>'
        .($chunk['samples'] === [] ? '' : '<pre>'.e(implode("\n", $chunk['samples'])).'</pre>')
        .'<form id="next" method="post">'.$hidden.'<button type="submit">Continue</button></form>'
        .'<script>setTimeout(function(){document.getElementById("next").submit();},800);</script>'
    );
}

$cacheNote = '';
if ($apply && $totalsUpdated > 0) {
    try {
        $app->make(SeoService::class)->clearCache();
        Cache::forget('home.product_rows_v1');
        Cache::forget('home.product_rows_v2');
        $cacheNote = '<p>Storefront and feed caches cleared.</p>';
    } catch (Throwable $e) {
        $cacheNote = '<p>Cache clear warning: '.e($e->getMessage()).'</p>';
    }
}

$u7 = $sanitizer->inspectSku('U7-ENTERPRISE');
$remaining = $sanitizer->auditCatalog();
html_page('Finished', '<p>Scanned '.$totalsProcessed.' products. '
    .($apply ? 'Updated' : 'Would update').' '.$totalsUpdated.'.</p>'
    .$cacheNote
    .'<p>U7-ENTERPRISE needs scrub: '.(($u7['needs_scrub'] ?? false) ? 'YES' : 'no').'<br>'
    .'U7 phrases: '.e((($u7['phrases'] ?? []) === []) ? '(none)' : implode(' | ', $u7['phrases'])).'</p>'
    .'<p>Remaining catalogue leaks: '.count($remaining).'</p>'
    .'<p><strong>Delete public_html/scrub-internal-copy.php now.</strong></p>');

function normalize_secret(string $value): string
{
    $value = trim($value);
    $value = trim($value, "\"'");
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;

    return trim($value);
}

function auth_cookie_value(string $secret): string
{
    return hash_hmac('sha256', 'scrub-internal-copy', $secret);
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function html_page(string $title, string $body, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex">'
        .'<title>'.e($title).'</title>'
        .'<style>body{font-family:Arial,sans-serif;max-width:720px;margin:40px auto;padding:0 16px;line-height:1.45}'
        .'code,pre{background:#f4f4f4;padding:2px 6px}pre{padding:12px;overflow:auto}</style></head><body>'
        .'<h1>'.e($title).'</h1>'.$body.'</body></html>';
    exit;
}

/**
 * @return array{processed:int,updated:int,last_id:int,done:bool,samples:list<string>}
 */
function scrub_chunk(InternalPricingCopySanitizer $sanitizer, bool $apply, int $afterId, int $limit, ?string $backupPath): array
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
