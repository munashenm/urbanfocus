<?php

/**
 * Clear Laravel caches on cPanel (no Terminal) — does NOT rebuild caches.
 *
 * 1. Copy to public_html/clear-cache.php
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

echo "=== Delete bootstrap cache files ===\n";
$cacheFiles = glob($laravelRoot.'/bootstrap/cache/*.php') ?: [];
$deleted = 0;
foreach ($cacheFiles as $file) {
    if (basename($file) !== '.gitignore' && @unlink($file)) {
        echo "Deleted: ".basename($file)."\n";
        $deleted++;
    }
}
if ($deleted === 0) {
    echo "Already clear.\n";
}

if (file_exists($laravelRoot.'/vendor/autoload.php')) {
    require $laravelRoot.'/vendor/autoload.php';
    $app = require_once $laravelRoot.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    foreach (['config:clear', 'route:clear', 'view:clear', 'cache:clear'] as $cmd) {
        try {
            $kernel->call($cmd);
            echo trim($kernel->output())."\n";
        } catch (Throwable $e) {
            echo "{$cmd} failed: ".$e->getMessage()."\n";
        }
    }
    echo "\n✓ Caches cleared (not rebuilt).\n";
} else {
    echo "\nvendor/ not found — bootstrap cache delete may be enough.\n";
}

echo "\nTry https://www.urbanfocus.co.za/ now.\n";
echo "DELETE public_html/clear-cache.php now.\n</pre>";
