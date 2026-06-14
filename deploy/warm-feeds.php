<?php

/**
 * Pre-build product XML feeds on cPanel (avoids Google Merchant timeout on first fetch).
 *
 * 1. Git pull latest code into ~/urbanfocus
 * 2. Copy urbanfocus/deploy/warm-feeds.php → public_html/warm-feeds.php
 * 3. Set WARM_KEY below to a long random secret
 * 4. Visit: https://www.urbanfocus.co.za/warm-feeds.php?key=YOUR_SECRET
 * 5. DELETE public_html/warm-feeds.php when done
 */

declare(strict_types=1);

const WARM_KEY = 'CHANGE-ME-warm-feeds-secret';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');

if (str_contains(WARM_KEY, 'CHANGE-ME') || strlen(WARM_KEY) < 16) {
    http_response_code(403);
    exit('Refusing to run: edit this file and set a strong, unique secret key (16+ chars, no "CHANGE-ME") before use.');
}

if (! hash_equals(WARM_KEY, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden');
}

$laravelRoot = dirname(__DIR__).'/urbanfocus';

header('Content-Type: text/html; charset=utf-8');
echo '<pre style="font:14px/1.5 monospace;white-space:pre-wrap">';

echo "=== Warm product feeds ===\n";
echo 'Time: '.date('Y-m-d H:i:s')."\n";
echo "Laravel root: {$laravelRoot}\n\n";

if (! is_dir($laravelRoot.'/vendor')) {
    exit("STOP: vendor/ missing. Run setup or composer install first.\n");
}

require $laravelRoot.'/vendor/autoload.php';
$app = require_once $laravelRoot.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

/** @var \App\Services\FeedService $feeds */
$feeds = $app->make(\App\Services\FeedService::class);

set_time_limit(300);
@ini_set('memory_limit', '512M');

$targets = [
    'Google Merchant XML' => static fn () => $feeds->googleMerchantXml(),
    'PriceCheck XML' => static fn () => $feeds->priceCheckXml(),
];

foreach ($targets as $label => $builder) {
    echo "Building {$label}...\n";
    $started = microtime(true);

    try {
        $xml = $builder();
        $seconds = number_format(microtime(true) - $started, 2);
        $bytes = strlen($xml);
        $items = substr_count($xml, '<item>');

        echo "  OK in {$seconds}s — {$bytes} bytes, ~{$items} products\n";
        echo "  Preview: ".substr(ltrim($xml), 0, 80)."...\n\n";
    } catch (Throwable $e) {
        echo '  FAILED: '.$e->getMessage()."\n";
        echo '  File: '.$e->getFile().':'.$e->getLine()."\n\n";
    }
}

$feedDir = $laravelRoot.'/storage/app/feeds';
echo "Feed files in storage/app/feeds:\n";
foreach (glob($feedDir.'/*.xml') ?: [] as $file) {
    echo '  '.basename($file).' — '.number_format(filesize($file)).' bytes'."\n";
}

echo "\nTest URLs:\n";
echo "  https://www.urbanfocus.co.za/feeds/google-merchant.xml\n";
echo "  https://www.urbanfocus.co.za/feeds/pricecheck.xml\n";
echo "\nDELETE public_html/warm-feeds.php now.\n</pre>";
