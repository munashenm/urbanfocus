<?php

/**
 * ONE-TIME cache clear for cPanel (no Terminal)
 *
 * 1. Upload to public_html/clear-cache.php
 * 2. Visit: https://www.urbanfocus.co.za/clear-cache.php?key=YOUR_SECRET
 * 3. DELETE this file immediately after use
 */

declare(strict_types=1);

const CLEAR_KEY = 'CHANGE-ME-clear-cache-secret';

if (($_GET['key'] ?? '') !== CLEAR_KEY) {
    http_response_code(403);
    exit('Forbidden');
}

$laravelRoot = dirname(__DIR__).'/urbanfocus';

header('Content-Type: text/html; charset=utf-8');
echo '<pre>';

$cacheFiles = glob($laravelRoot.'/bootstrap/cache/*.php') ?: [];
$deleted = 0;

foreach ($cacheFiles as $file) {
    if (basename($file) !== '.gitignore' && @unlink($file)) {
        echo "Deleted: {$file}\n";
        $deleted++;
    }
}

if ($deleted === 0) {
    echo "No cache files found (already clear).\n";
}

if (file_exists($laravelRoot.'/vendor/autoload.php')) {
    require $laravelRoot.'/vendor/autoload.php';
    $app = require_once $laravelRoot.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->call('route:cache');
    echo $kernel->output();
    $kernel->call('view:cache');
    echo $kernel->output();
    $kernel->call('config:cache');
    echo $kernel->output();
    echo "\nCaches rebuilt.\n";
} else {
    echo "\nRebuild skipped — vendor/ not found.\n";
}

echo "\nDone. DELETE public_html/clear-cache.php now.\n</pre>";
