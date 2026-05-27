<?php

/**
 * Discover blog topics + optional GSC sync (cPanel, no Terminal)
 *
 * 1. Copy urbanfocus/deploy/sync-blog-topics.php → public_html/sync-blog-topics.php
 * 2. Edit SYNC_KEY below
 * 3. Visit: https://www.urbanfocus.co.za/sync-blog-topics.php?key=YOUR_SECRET
 * 4. DELETE this file immediately after use
 */

declare(strict_types=1);

const SYNC_KEY = 'CHANGE-ME-sync-blog';

if (($_GET['key'] ?? '') !== SYNC_KEY) {
    http_response_code(403);
    exit('Forbidden');
}

$laravelRoot = dirname(__DIR__).'/urbanfocus';

header('Content-Type: text/html; charset=utf-8');
echo '<pre>';

require $laravelRoot.'/vendor/autoload.php';
$app = require_once $laravelRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Blog topic discovery...\n\n";

try {
    $result = $app->make(App\Services\Blog\BlogTopicDiscoveryService::class)->discover();
    Illuminate\Support\Facades\Cache::forget('sitemap.xml');
    echo "Discovered: {$result['discovered']}\n";
    echo "Skipped: {$result['skipped']}\n";
    if ($result['errors']) {
        echo "\nWarnings:\n".implode("\n", $result['errors'])."\n";
    }
    echo "\n✓ Review topics in Admin → Content Strategy\n";
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
}

echo "\nDELETE public_html/sync-blog-topics.php now.\n</pre>";
