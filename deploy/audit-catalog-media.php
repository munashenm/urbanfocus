<?php

/**
 * Catalogue media & data audit (report first). Optional high-confidence image recovery.
 *
 * 1. Git pull latest code into ~/urbanfocus
 * 2. Copy this file to public_html/audit-catalog-media.php
 * 3. Set AUDIT_KEY below (16+ letters/numbers)
 * 4. Visit the URL, enter the password, review the report
 * 5. Only then tick Recover if you want exact Brand+SKU image recovery
 * 6. DELETE public_html/audit-catalog-media.php
 *
 * Audit does not invent images, scrape manufacturer sites, or guess missing titles.
 */

declare(strict_types=1);

use App\Services\CatalogMediaHealthService;
use Illuminate\Contracts\Console\Kernel;
use Throwable;

const AUDIT_KEY = 'CHANGE-ME-catalog-media-secret';
const COOKIE_NAME = 'uf_catalog_media_audit_auth';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0, must-revalidate');
header('Pragma: no-cache');

$expectedKey = normalize_secret(AUDIT_KEY);
$providedKey = normalize_secret((string) (
    $_POST['token']
    ?? $_GET['token']
    ?? $_GET['key']
    ?? ''
));

if (str_contains($expectedKey, 'CHANGE-ME') || strlen($expectedKey) < 16) {
    html_page('Set a password in the file first', '<p>Open <code>public_html/audit-catalog-media.php</code> and change:</p>'
        .'<pre>const AUDIT_KEY = \'CHANGE-ME-catalog-media-secret\';</pre>'
        .'<p>to 16+ letters and numbers, save, reload, then type that password in the box.</p>');
}

$cookieOk = isset($_COOKIE[COOKIE_NAME]) && hash_equals(auth_cookie_value($expectedKey), (string) $_COOKIE[COOKIE_NAME]);
$keyOk = $providedKey !== '' && hash_equals($expectedKey, $providedKey);
$authed = $cookieOk || $keyOk;

if (! $authed) {
    $hint = $providedKey !== ''
        ? '<p><strong>Password did not match.</strong></p>'
        : '';
    html_page('Enter password', $hint
        .'<form method="post" action="audit-catalog-media.php">'
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

@set_time_limit(0);
@ini_set('memory_limit', '1024M');
@ini_set('display_errors', '0');
error_reporting(E_ALL);

register_shutdown_function(function (): void {
    $error = error_get_last();
    if (! is_array($error) || ! in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    if (headers_sent()) {
        echo "\n<pre>Fatal: ".htmlspecialchars((string) $error['message'])." in ".$error['file'].':'.$error['line']."</pre>";
        return;
    }
    html_page(
        'Audit crashed',
        '<p>PHP stopped before the report finished. Common cause: memory or a missing class after an incomplete git pull.</p>'
        .'<pre>'.e((string) $error['message'])."\n".$error['file'].':'.$error['line'].'</pre>'
        .'<p>Git pull <code>urbanfocus</code>, then copy this file from <code>urbanfocus/deploy/audit-catalog-media.php</code> over <code>public_html/audit-catalog-media.php</code> again, keeping your AUDIT_KEY.</p>',
        500
    );
});

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

set_exception_handler(function (Throwable $e): void {
    html_page(
        'Audit failed',
        '<p>Laravel booted, then threw:</p><pre>'.e($e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n\n".$e->getTraceAsString()).'</pre>',
        500
    );
});

if (! class_exists(CatalogMediaHealthService::class)) {
    html_page(
        'Code not deployed',
        '<p><code>CatalogMediaHealthService</code> is missing. Git pull <code>~/urbanfocus</code> first, then reload.</p>',
        500
    );
}

$recover = (($_POST['recover'] ?? '') === '1');
$checkRemote = (($_POST['check_remote'] ?? '1') === '1');
$download = (string) ($_GET['download'] ?? '');

$health = $app->make(CatalogMediaHealthService::class);

if ($download !== '') {
    $path = $health->reportAbsolutePath($download);
    if ($path === null) {
        html_page('Report not found', '<p>Run the audit first, then download the CSV.</p>', 404);
    }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="'.basename($path).'"');
    readfile($path);
    exit;
}

try {
    $report = $health->run(checkRemote: $checkRemote, recover: $recover);
} catch (Throwable $e) {
    html_page(
        'Audit failed',
        '<pre>'.e($e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n\n".$e->getTraceAsString()).'</pre>',
        500
    );
}

$counts = $report['counts'];
$huawei = $report['huawei_02356uum'] ?? [];

$body = $recover
    ? '<p>Audit + high-confidence SKU/brand image recovery. Uncertain products were left without invented photos.</p>'
    : '<p>Read-only audit of every active product. Images were <strong>not</strong> changed. Use the form below only after you have reviewed the counts.</p>';

$body .= '<h2>Final counts</h2><table><tbody>';
$labels = [
    'total_scanned' => 'Total products scanned',
    'valid_images' => 'Valid product images',
    'missing_images' => 'Missing images',
    'broken_images' => 'Broken images',
    'placeholder_images' => 'Placeholder images',
    'recovered_images' => 'Recovered images',
    'still_requiring_manual_images' => 'Still requiring manual images',
    'missing_titles' => 'Missing titles',
    'missing_skus' => 'Missing SKUs',
    'missing_prices' => 'Missing prices',
    'duplicate_skus' => 'Duplicate SKUs',
    'missing_brands' => 'Missing brands',
    'missing_descriptions' => 'Missing descriptions',
];
foreach ($labels as $key => $label) {
    $body .= '<tr><th>'.e($label).'</th><td>'.e((string) ($counts[$key] ?? '0')).'</td></tr>';
}
$body .= '</tbody></table>';

$body .= '<p>Downloads: '
    .'<a href="audit-catalog-media.php?download=missing-product-images.csv">missing-product-images.csv</a> · '
    .'<a href="audit-catalog-media.php?download=catalogue-data-problems.csv">catalogue-data-problems.csv</a> · '
    .'<a href="audit-catalog-media.php?download=manufacturer-image-candidates.csv">manufacturer-image-candidates.csv</a> · '
    .'<a href="audit-catalog-media.php?download=catalogue-media-audit.csv">full audit CSV</a></p>';

$body .= '<h2>Huawei SKU 02356UUM</h2>';
if ($huawei === []) {
    $body .= '<p>No product row matched this SKU, model, name or slug. It is not in the live product table under that identifier.</p>';
} else {
    $body .= '<table><thead><tr><th>ID</th><th>SKU</th><th>Name</th><th>Meta title</th><th>Active</th><th>Image</th><th>URL</th><th>Problems</th></tr></thead><tbody>';
    foreach ($huawei as $row) {
        $body .= '<tr>'
            .'<td>'.e((string) $row['id']).'</td>'
            .'<td>'.e((string) $row['sku']).'</td>'
            .'<td>'.e((string) $row['name']).'</td>'
            .'<td>'.e((string) ($row['meta_title'] ?: '—')).'</td>'
            .'<td>'.($row['is_active'] ? 'yes' : 'no').($row['trashed'] ? ' (archived)' : '').'</td>'
            .'<td>'.e((string) $row['image_status']).'</td>'
            .'<td>'.e((string) ($row['product_url'] ?: '—')).'</td>'
            .'<td>'.e($row['problems'] === [] ? 'none' : implode('; ', $row['problems'])).'</td>'
            .'</tr>';
    }
    $body .= '</tbody></table>';
}

if (! $recover) {
    $body .= '<h2>Step 2 — recover exact SKU images</h2>'
        .'<form method="post" action="audit-catalog-media.php">'
        .'<input type="hidden" name="token" value="'.e($providedKey !== '' ? $providedKey : (string) ($_COOKIE[COOKIE_NAME] ?? '')).'">'
        .'<p><label><input type="checkbox" name="check_remote" value="1" checked> HTTP-check remote image URLs</label></p>'
        .'<p><label><input type="checkbox" name="recover" value="1"> Recover high-confidence Brand + SKU images from local/catalog sources</label></p>'
        .'<p><button type="submit" style="padding:10px 18px">Run again</button></p>'
        .'</form>';
}

$body .= '<p><strong>Delete public_html/audit-catalog-media.php when finished.</strong></p>';

html_page($recover ? 'Catalogue media audit + recovery' : 'Catalogue media audit', $body);

function normalize_secret(string $value): string
{
    $value = trim($value);
    $value = trim($value, "\"'");
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;

    return trim($value);
}

function auth_cookie_value(string $secret): string
{
    return hash_hmac('sha256', 'catalog-media-audit', $secret);
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
        .'<style>body{font-family:Arial,sans-serif;max-width:960px;margin:40px auto;padding:0 16px;line-height:1.45}'
        .'table{border-collapse:collapse;width:100%;font-size:13px;margin:12px 0 24px}'
        .'th,td{border:1px solid #ddd;padding:6px 8px;text-align:left;vertical-align:top}'
        .'th{background:#f4f4f4}code,pre{background:#f4f4f4;padding:2px 6px}pre{padding:12px;overflow:auto}</style></head><body>'
        .'<h1>'.e($title).'</h1>'.$body.'</body></html>';
    exit;
}
