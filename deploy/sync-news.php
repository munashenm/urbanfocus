<?php

/**
 * Sync IT news from RSS feeds into blog drafts (cPanel, no Terminal)
 *
 * 1. Copy urbanfocus/deploy/sync-news.php → public_html/sync-news.php
 * 2. Edit SYNC_KEY below
 * 3. Visit: https://www.urbanfocus.co.za/sync-news.php?key=YOUR_SECRET
 * 4. DELETE this file immediately after use
 */

declare(strict_types=1);

const SYNC_KEY = 'CHANGE-ME-sync-news';

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

$sync = $app->make(App\Services\NewsSyncService::class);

echo "Syncing IT news from RSS feeds...\n\n";

try {
    $result = $sync->sync();
    Illuminate\Support\Facades\Cache::forget('sitemap.xml');
    echo "Imported: {$result['imported']}\n";
    echo "Skipped (duplicates): {$result['skipped']}\n";
    if ($result['errors']) {
        echo "\nErrors:\n".implode("\n", $result['errors'])."\n";
    }
    echo "\n✓ Review drafts in Admin → Blog\n";
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
}

echo "\nDELETE public_html/sync-news.php now.\n</pre>";
