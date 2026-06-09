<?php

/**
 * Seed admin roles & permissions on cPanel (no Terminal)
 *
 * Run AFTER migrations (clear-cache.php?migrate=1 or migrate.php).
 *
 * 1. Git pull latest code to urbanfocus first
 * 2. Copy urbanfocus/deploy/seed-roles.php → public_html/seed-roles.php
 * 3. Edit SEED_KEY below to a random secret
 * 4. Visit: https://www.urbanfocus.co.za/seed-roles.php?key=YOUR_SECRET
 * 5. DELETE this file immediately after success
 */

declare(strict_types=1);

const SEED_KEY = 'CHANGE-ME-seed-roles-secret';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');

if (str_contains(SEED_KEY, 'CHANGE-ME') || strlen(SEED_KEY) < 16) {
    http_response_code(403);
    exit('Refusing to run: edit this file and set a strong, unique secret key (16+ chars, no "CHANGE-ME") before use.');
}

if (! hash_equals(SEED_KEY, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden');
}

$laravelRoot = dirname(__DIR__).'/urbanfocus';

header('Content-Type: text/html; charset=utf-8');
echo '<pre>';

if (! is_dir($laravelRoot)) {
    exit("Error: urbanfocus folder not found at {$laravelRoot}\n");
}

if (! file_exists($laravelRoot.'/vendor/autoload.php')) {
    exit("Error: vendor/ missing in urbanfocus/\n");
}

require $laravelRoot.'/vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require_once $laravelRoot.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

echo "Seeding roles & permissions...\n\n";

try {
    $exitCode = $kernel->call('db:seed', [
        '--class' => 'Database\\Seeders\\RolePermissionSeeder',
        '--force' => true,
    ]);
    echo $kernel->output();
    if ($exitCode === 0) {
        echo "\n✓ Roles and permissions seeded.\n";
        echo "Super Admin role assigned to admin@urbanfocus.co.za (if that user exists).\n";
        echo "Log in and open /admin to test.\n";
    } else {
        echo "\n✗ Exit code: {$exitCode}\n";
    }
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
    if (str_contains($e->getMessage(), "doesn't exist") || str_contains($e->getMessage(), 'Base table or view not found')) {
        echo "\nRun migrations first: clear-cache.php?key=YOUR_SECRET&migrate=1\n";
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

$cacheFiles = glob($laravelRoot.'/bootstrap/cache/*.php') ?: [];
foreach ($cacheFiles as $file) {
    if (basename($file) !== '.gitignore' && @unlink($file)) {
        echo 'Deleted cache: '.basename($file)."\n";
    }
}

echo "\nDELETE public_html/seed-roles.php now.\n</pre>";
