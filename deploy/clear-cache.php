<?php

/**
 * Clear Laravel caches on cPanel (no Terminal) — does NOT rebuild caches.
 *
 * 1. Copy urbanfocus/deploy/clear-cache.php → public_html/clear-cache.php
 * 2. Visit: https://www.urbanfocus.co.za/clear-cache.php?key=YOUR_SECRET
 * 3. Optional migrations: add &migrate=1 to the URL
 * 4. Optional role seed: add &seed=1 (after migrate)
 * 5. DELETE this file immediately after use
 *
 * Standalone migrate script: urbanfocus/deploy/migrate.php (copy to public_html/migrate.php)
 * Blog diagnostic: urbanfocus/deploy/diagnose-blog.php
 */

declare(strict_types=1);

const CLEAR_KEY = 'CHANGE-ME-clear-cache-secret';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');

if (str_contains(CLEAR_KEY, 'CHANGE-ME') || strlen(CLEAR_KEY) < 16) {
    http_response_code(403);
    exit('Refusing to run: edit this file and set a strong, unique secret key (16+ chars, no "CHANGE-ME") before use.');
}

if (! hash_equals(CLEAR_KEY, (string) ($_GET['key'] ?? ''))) {
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

echo "\n=== Delete generated sitemap files ===\n";
$sitemapFiles = glob($laravelRoot.'/storage/app/sitemaps/*.xml') ?: [];
foreach ($sitemapFiles as $file) {
    if (@unlink($file)) {
        echo "Deleted: ".basename($file)."\n";
    }
}
if ($sitemapFiles === []) {
    echo "No sitemap files.\n";
}

echo "\n=== Delete generated feed files ===\n";
$feedFiles = glob($laravelRoot.'/storage/app/feeds/*.xml') ?: [];
foreach ($feedFiles as $file) {
    if (@unlink($file)) {
        echo "Deleted: ".basename($file)."\n";
    }
}
if ($feedFiles === []) {
    echo "No feed files.\n";
}

require $laravelRoot.'/deploy/cpanel-asset-sync.php';
cpanel_sync_public_assets($laravelRoot, dirname(__DIR__).'/public_html');

if (file_exists($laravelRoot.'/vendor/autoload.php')) {
    require $laravelRoot.'/vendor/autoload.php';
    $app = require_once $laravelRoot.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

    if (isset($_GET['migrate'])) {
        echo "\n=== Running migrations ===\n";
        try {
            $exitCode = $kernel->call('migrate', ['--force' => true]);
            echo $kernel->output();
            echo $exitCode === 0 ? "\n✓ Migrations complete.\n" : "\n✗ Migration exit code: {$exitCode}\n";
        } catch (Throwable $e) {
            echo 'Migration ERROR: '.$e->getMessage()."\n";
        }
    }

    if (isset($_GET['seed'])) {
        echo "\n=== Seeding roles & permissions ===\n";
        try {
            $exitCode = $kernel->call('db:seed', [
                '--class' => 'Database\\Seeders\\RolePermissionSeeder',
                '--force' => true,
            ]);
            echo $kernel->output();
            echo $exitCode === 0 ? "\n✓ Roles seeded.\n" : "\n✗ Seed exit code: {$exitCode}\n";
        } catch (Throwable $e) {
            echo 'Seed ERROR: '.$e->getMessage()."\n";
        }
    }

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
