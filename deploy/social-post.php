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

if (($_GET['key'] ?? '') !== SOCIAL_KEY) {
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
