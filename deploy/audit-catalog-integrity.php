<?php

/**
 * Catalogue integrity audit (report first). Optional high-confidence taxonomy apply.
 *
 * 1. Git pull latest code into ~/urbanfocus
 * 2. Copy this file to public_html/audit-catalog-integrity.php
 * 3. Set AUDIT_KEY below (16+ letters/numbers)
 * 4. Visit the URL, enter the password, review the report
 * 5. Only then click Apply high-confidence taxonomy
 * 6. DELETE public_html/audit-catalog-integrity.php
 *
 * Does not rename products or change SKUs. Taxonomy apply only moves
 * laptop chargers/bags out of Laptops and interactive boards out of Monitors.
 */

declare(strict_types=1);

use App\Services\CatalogIntegrityService;
use Illuminate\Contracts\Console\Kernel;

const AUDIT_KEY = 'CHANGE-ME-catalog-integrity-secret';
const COOKIE_NAME = 'uf_catalog_audit_auth';

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
    html_page('Set a password in the file first', '<p>Open <code>public_html/audit-catalog-integrity.php</code> and change:</p>'
        .'<pre>const AUDIT_KEY = \'CHANGE-ME-catalog-integrity-secret\';</pre>'
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
        .'<form method="post" action="audit-catalog-integrity.php">'
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

$integrity = $app->make(CatalogIntegrityService::class);
$apply = (($_POST['apply'] ?? '') === '1');
$applied = null;

if ($apply) {
    $applied = $integrity->applyHighConfidenceTaxonomyFixes();
}

$report = $integrity->audit();
$dir = $laravelRoot.'/storage/app';
if (! is_dir($dir)) {
    @mkdir($dir, 0755, true);
}
$jsonPath = $dir.'/catalog-integrity-audit.json';
file_put_contents($jsonPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$counts = $report['counts'];
$totals = $report['totals'];
$usw = $report['usw_16p']['rows'] ?? [];

$body = '<p>Read-only audit of the live catalogue. Uncertain SKU/name data was <strong>not</strong> changed.</p>';
$body .= '<p>Products: '.e((string) $totals['all'])
    .' (active '.e((string) $totals['active'])
    .', inactive '.e((string) $totals['inactive']).')</p>';

if (is_array($applied)) {
    $body .= '<p><strong>Applied '.$applied['fixed'].' high-confidence category move(s).</strong></p>';
    if (($applied['samples'] ?? []) !== []) {
        $body .= '<pre>'.e(implode("\n", $applied['samples'])).'</pre>';
    }
}

$body .= '<h2>USW-16P</h2>';
if ($usw === []) {
    $body .= '<p>No product rows matched SKU USW-16P.</p>';
} else {
    $body .= '<pre>'.e(json_encode($usw, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)).'</pre>';
}

$body .= '<h2>Counts</h2><pre>'.e(json_encode($counts, JSON_PRETTY_PRINT)).'</pre>';
$body .= section_table('Duplicate SKUs', $report['duplicate_skus'], ['sku', 'count', 'names']);
$body .= section_table('SKU / name conflicts (do not auto-rename)', $report['sku_name_conflicts'], ['sku', 'count', 'names']);
$body .= section_table('High-confidence taxonomy (chargers, bags, interactive boards)', $report['taxonomy_high_confidence'], ['sku', 'name', 'from', 'to']);
$body .= section_table('Taxonomy review only', $report['taxonomy_review'], ['sku', 'name', 'from', 'to']);
$body .= section_table('Missing SKUs', $report['missing_skus'], ['id', 'sku', 'name']);
$body .= section_table('Missing prices', $report['missing_prices'], ['id', 'sku', 'name']);
$body .= section_table('Missing images', $report['missing_images'], ['id', 'sku', 'name']);
$body .= section_table('Missing descriptions', $report['missing_descriptions'], ['id', 'sku', 'name']);
$body .= section_table('Wrong brand / SKU', $report['wrong_brand_sku'], ['sku', 'name', 'brand', 'expected_brand']);
$body .= section_table('Malformed names', $report['malformed_names'], ['sku', 'name']);
$body .= section_table('Duplicate names', $report['duplicate_names'], ['name', 'count', 'skus']);
$body .= section_table('SEO title collisions', $report['seo_title_collisions'], ['title', 'count', 'skus']);

$body .= '<p>JSON written to <code>'.e($jsonPath).'</code></p>';

if (! $apply) {
    $body .= '<form method="post" onsubmit="return confirm(\'Move only high-confidence chargers, laptop bags and interactive boards? SKUs and names will not change.\')">'
        .'<input type="hidden" name="token" value="'.e($expectedKey).'">'
        .'<input type="hidden" name="apply" value="1">'
        .'<p><button type="submit" style="padding:10px 18px">Apply high-confidence taxonomy</button></p>'
        .'</form>';
}

$body .= '<p><strong>Delete public_html/audit-catalog-integrity.php when finished.</strong></p>';

html_page($apply ? 'Catalogue audit + taxonomy apply' : 'Catalogue integrity audit', $body);

function normalize_secret(string $value): string
{
    $value = trim($value);
    $value = trim($value, "\"'");
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;

    return trim($value);
}

function auth_cookie_value(string $secret): string
{
    return hash_hmac('sha256', 'catalog-integrity-audit', $secret);
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

function section_table(string $title, mixed $rows, array $keys): string
{
    if (! is_array($rows) || $rows === []) {
        return '<h2>'.e($title).'</h2><p>None in this sample.</p>';
    }

    $html = '<h2>'.e($title).' ('.count($rows).')</h2><table><thead><tr>';
    foreach ($keys as $key) {
        $html .= '<th>'.e($key).'</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        if (! is_array($row)) {
            continue;
        }
        $html .= '<tr>';
        foreach ($keys as $key) {
            $value = $row[$key] ?? '';
            if (is_array($value)) {
                $value = implode(' | ', array_map('strval', $value));
            }
            $html .= '<td>'.e((string) $value).'</td>';
        }
        $html .= '</tr>';
    }

    return $html.'</tbody></table>';
}
