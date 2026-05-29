<?php

/**
 * Publish pending social media posts (cPanel cron — every 15–30 min)
 *
 * 1. Copy to public_html/social-post.php
 * 2. Set SOCIAL_POSTING_ENABLED=true and API keys in .env
 * 3. Visit: https://www.urbanfocus.co.za/social-post.php?key=YOUR_SECRET
 * 4. Add as cPanel Cron Job: curl/wget this URL
 * 5. DELETE from public_html if using cron CLI only — or keep with strong key
 */

declare(strict_types=1);

const SOCIAL_KEY = 'CHANGE-ME-social-post';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');

if (str_contains(SOCIAL_KEY, 'CHANGE-ME') || strlen(SOCIAL_KEY) < 16) {
    http_response_code(403);
    exit('Refusing to run: edit this file and set a strong, unique secret key (16+ chars, no "CHANGE-ME") before use.');
}

if (! hash_equals(SOCIAL_KEY, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden');
}

$laravelRoot = dirname(__DIR__).'/urbanfocus';

header('Content-Type: text/plain; charset=utf-8');

require $laravelRoot.'/vendor/autoload.php';
$app = require_once $laravelRoot.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$social = $app->make(App\Services\Social\SocialPostingService::class);

if (! $social->isEnabled()) {
    exit("Social posting disabled. Set SOCIAL_POSTING_ENABLED=true\n");
}

$result = $social->publishPending();
echo "Posted: {$result['posted']}\nFailed: {$result['failed']}\nSkipped: {$result['skipped']}\n";

$recentFailed = App\Models\SocialPost::where('status', 'failed')->latest()->take(5)->get(['platform', 'error_message']);
if ($recentFailed->isNotEmpty()) {
    echo "\nRecent errors:\n";
    foreach ($recentFailed as $row) {
        echo "- {$row->platform}: {$row->error_message}\n";
    }
}
