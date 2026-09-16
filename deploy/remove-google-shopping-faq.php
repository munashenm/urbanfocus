<?php

/**
 * Remove the customer-visible "Is this listing ready for Google Shopping?" FAQ
 * and related SEO commentary from stored product copy.
 *
 * This edits the DATABASE, so the live product page updates even if the
 * Laravel app code has not been pulled yet.
 *
 * 1. Upload THIS file to public_html/remove-google-shopping-faq.php
 *    (next to index.php — not into urbanfocus/deploy/)
 * 2. Edit ONLY this line:
 *      const SCRUB_KEY = 'UrbanFocusSeoFaq2026ok';
 * 3. Open: https://www.urbanfocus.co.za/remove-google-shopping-faq.php
 *    Type the same password in the box.
 * 4. Preview, then Apply. Hard-refresh the ZimaBoard product page.
 * 5. DELETE this file from public_html when finished.
 */

declare(strict_types=1);

use App\Models\Product;
use App\Services\SeoService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

const SCRUB_KEY = 'CHANGE-ME-remove-google-shopping-faq-secret';
const BATCH_SIZE = 50;
const COOKIE_NAME = 'uf_seo_faq_scrub_auth';

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
    html_page('Set a password in the file first', '<p>Open <code>public_html/remove-google-shopping-faq.php</code> in File Manager and change:</p>'
        .'<pre>const SCRUB_KEY = \'CHANGE-ME-remove-google-shopping-faq-secret\';</pre>'
        .'<p>to a password of 16+ letters and numbers, for example:</p>'
        .'<pre>const SCRUB_KEY = \'UrbanFocusSeoFaq2026ok\';</pre>'
        .'<p>Save, reload this page, and type that password in the box.</p>');
}

$cookieOk = isset($_COOKIE[COOKIE_NAME]) && hash_equals(auth_cookie_value($expectedKey), (string) $_COOKIE[COOKIE_NAME]);
$keyOk = $providedKey !== '' && hash_equals($expectedKey, $providedKey);
$authed = $cookieOk || $keyOk;

if (! $authed) {
    $hint = '';
    if ($providedKey !== '') {
        $hint = '<p><strong>Password did not match.</strong> Typed '.strlen($providedKey)
            .' characters; file has '.strlen($expectedKey).' characters.</p>';
    }
    html_page('Enter password', $hint
        .'<form method="post" action="remove-google-shopping-faq.php">'
        .'<p><input type="password" name="token" autocomplete="current-password" style="width:100%;max-width:28rem;padding:10px;font-size:16px" required></p>'
        .'<p><button type="submit" style="padding:10px 18px">Continue</button></p>'
        .'</form>');
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

if (property_exists(Product::class, 'sanitizeCustomerCopyOnSave')) {
    Product::$sanitizeCustomerCopyOnSave = false;
}

$apply = (($_POST['apply'] ?? $_GET['apply'] ?? '') === '1');
$preview = (($_POST['preview'] ?? $_GET['preview'] ?? '') === '1');
$afterId = max(0, (int) ($_POST['after'] ?? $_GET['after'] ?? 0));
$totalsProcessed = max(0, (int) ($_POST['processed'] ?? $_GET['processed'] ?? 0));
$totalsUpdated = max(0, (int) ($_POST['updated'] ?? $_GET['updated'] ?? 0));

if (! $apply && ! $preview) {
    $sample = Product::query()
        ->where(function ($q) {
            seo_copy_query($q);
        })
        ->orderBy('id')
        ->first();

    $sampleBlock = $sample === null
        ? '<p>No stored Google Shopping / structured-data commentary found. If the live page still shows it, hard-refresh after uploading the latest app code.</p>'
        : '<p><strong>'.e((string) $sample->name).'</strong><br>SKU '.e((string) ($sample->sku ?: '—'))
            .'<br>'.e(route('products.show', $sample)).'</p>';

    html_page('Remove Google Shopping FAQ copy', '<p>Logged in. Catalogue copy has not been changed yet.</p>'
        .'<p>This removes the <em>Is this listing ready for Google Shopping?</em> FAQ and similar SEO notes from stored product descriptions. Customer FAQs about buying in South Africa and delivery stay.</p>'
        .'<h2>First matching listing</h2>'.$sampleBlock
        .'<form method="post" style="display:inline-block;margin-right:12px">'
        .'<input type="hidden" name="token" value="'.e($expectedKey).'">'
        .'<input type="hidden" name="preview" value="1">'
        .'<button type="submit" style="padding:10px 18px">Preview</button>'
        .'</form>'
        .'<form method="post" style="display:inline-block" onsubmit="return confirm(\'Remove Google Shopping FAQ copy from stored product listings?\')">'
        .'<input type="hidden" name="token" value="'.e($expectedKey).'">'
        .'<input type="hidden" name="apply" value="1">'
        .'<button type="submit" style="padding:10px 18px">Apply cleanup</button>'
        .'</form>'
        .'<p>Delete <code>public_html/remove-google-shopping-faq.php</code> when finished.</p>');
}

$chunk = scrub_chunk($apply, $afterId, BATCH_SIZE);
$totalsProcessed += $chunk['processed'];
$totalsUpdated += $chunk['updated'];

if (! $chunk['done']) {
    $fields = [
        'token' => $expectedKey,
        $apply ? 'apply' : 'preview' => '1',
        'after' => (string) $chunk['last_id'],
        'processed' => (string) $totalsProcessed,
        'updated' => (string) $totalsUpdated,
    ];
    $hidden = '';
    foreach ($fields as $name => $value) {
        $hidden .= '<input type="hidden" name="'.e($name).'" value="'.e($value).'">';
    }
    html_page(
        $apply ? 'Applying…' : 'Previewing…',
        '<p>Batch ending at product ID '.$chunk['last_id'].'</p>'
        .'<p>This batch scanned '.$chunk['processed'].', '
        .($apply ? 'updated' : 'would update').' '.$chunk['updated'].'.</p>'
        .'<p>Running totals: scanned '.$totalsProcessed.', '
        .($apply ? 'updated' : 'would update').' '.$totalsUpdated.'.</p>'
        .($chunk['samples'] === [] ? '' : '<pre>'.e(implode("\n", $chunk['samples'])).'</pre>')
        .'<form id="next" method="post">'.$hidden.'<button type="submit">Continue</button></form>'
        .'<script>setTimeout(function(){document.getElementById("next").submit();},600);</script>'
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

$remaining = Product::query()->where(function ($q) {
    seo_copy_query($q);
})->count();

html_page('Finished', '<p>Scanned '.$totalsProcessed.' matching products. '
    .($apply ? 'Updated' : 'Would update').' '.$totalsUpdated.'.</p>'
    .$cacheNote
    .'<p>Remaining listings with Google Shopping FAQ copy: '.$remaining.'</p>'
    .'<p>Hard-refresh <a href="https://www.urbanfocus.co.za/product/urban-focus-zimaboard-cctv-recording-storage-server-ufzbcctv">the ZimaBoard product page</a>.</p>'
    .'<p><strong>Delete public_html/remove-google-shopping-faq.php now.</strong></p>');

function seo_copy_query($query): void
{
    $query->where('description', 'like', '%Google Shopping%')
        ->orWhere('description', 'like', '%Google Merchant Center%')
        ->orWhere('description', 'like', '%structured data%')
        ->orWhere('description', 'like', '%AI search overview%')
        ->orWhere('short_description', 'like', '%Google Shopping%')
        ->orWhere('specifications', 'like', '%Google Shopping%')
        ->orWhere('specifications', 'like', '%Google Merchant Center%')
        ->orWhere('specifications', 'like', '%structured data%');
}

function is_seo_commentary(string $text): bool
{
    return preg_match(
        '/google shopping|google merchant|structured data|image alt text|ai search overview|listings? are prepared for google/iu',
        $text
    ) === 1;
}

function strip_seo_html(string $html): string
{
    if ($html === '') {
        return '';
    }

    $html = preg_replace(
        '/<(h[1-6])\b[^>]*>\s*(?:<[^>]+>\s*)*Is this listing ready for Google Shopping\?[\s\S]*?<\/\1>\s*(?:<p\b[^>]*>[\s\S]*?<\/p>\s*)?/iu',
        '',
        $html
    ) ?? $html;

    $html = preg_replace(
        '/<(h[1-6])\b[^>]*>[\s\S]*?Google Shopping[\s\S]*?<\/\1>\s*(?:<p\b[^>]*>[\s\S]*?<\/p>\s*)?/iu',
        '',
        $html
    ) ?? $html;

    $html = preg_replace(
        '/<(p|li|div)\b[^>]*>[\s\S]*?(?:Google Merchant Center|image alt text|structured data so Google|AI search overviews)[\s\S]*?<\/\1>\s*/iu',
        '',
        $html
    ) ?? $html;

    $html = preg_replace(
        '/\s*Listings are prepared for Google Shopping[^.]*\./iu',
        '',
        $html
    ) ?? $html;

    return trim($html);
}

function strip_seo_plain(string $text): string
{
    if ($text === '' || ! is_seo_commentary($text)) {
        return $text;
    }

    $parts = preg_split('/(?<=[.!?])\s+|\n+/u', $text) ?: [$text];
    $kept = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '' && ! is_seo_commentary($part)) {
            $kept[] = $part;
        }
    }

    return trim(implode(' ', $kept));
}

/**
 * @param  array<string, mixed>  $specs
 * @return array<string, mixed>
 */
function strip_seo_specs(array $specs): array
{
    $clean = [];
    foreach ($specs as $key => $value) {
        if (is_string($value) && is_seo_commentary($value)) {
            continue;
        }
        $clean[$key] = $value;
    }

    for ($i = 1; $i <= 6; $i++) {
        $question = trim((string) ($clean["FAQ {$i} question"] ?? ''));
        $answer = trim((string) ($clean["FAQ {$i} answer"] ?? ''));
        if ($question === '' || $answer === '' || is_seo_commentary($question) || is_seo_commentary($answer)) {
            unset($clean["FAQ {$i} question"], $clean["FAQ {$i} answer"]);
        }
    }

    return $clean;
}

/**
 * @return array{processed:int,updated:int,last_id:int,done:bool,samples:list<string>}
 */
function scrub_chunk(bool $apply, int $afterId, int $limit): array
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
        ->where(function ($q) {
            seo_copy_query($q);
        })
        ->orderBy('id')
        ->limit($limit)
        ->get();

    $stats['done'] = $products->count() < $limit;

    foreach ($products as $product) {
        $stats['processed']++;
        $stats['last_id'] = (int) $product->id;

        $updates = [];

        $description = strip_seo_html((string) $product->description);
        if ($description !== (string) $product->description) {
            $updates['description'] = $description;
        }

        $short = strip_seo_plain((string) $product->short_description);
        if ($short !== (string) $product->short_description) {
            $updates['short_description'] = $short;
        }

        $specs = is_array($product->specifications) ? $product->specifications : [];
        $cleanSpecs = strip_seo_specs($specs);
        if ($cleanSpecs !== $specs) {
            $updates['specifications'] = $cleanSpecs;
        }

        if ($updates === []) {
            continue;
        }

        $stats['updated']++;
        if (count($stats['samples']) < 25) {
            $stats['samples'][] = trim(($product->sku ? $product->sku.' ' : '').$product->name);
        }

        if ($apply) {
            if (isset($updates['specifications']) && is_array($updates['specifications'])) {
                $updates['specifications'] = json_encode(
                    $updates['specifications'],
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
            }
            $updates['updated_at'] = now();
            DB::table('products')->where('id', $product->id)->update($updates);
        }
    }

    return $stats;
}

function normalize_secret(string $value): string
{
    $value = trim($value);
    $value = trim($value, "\"'");
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;

    return trim($value);
}

function auth_cookie_value(string $secret): string
{
    return hash_hmac('sha256', 'remove-google-shopping-faq', $secret);
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
