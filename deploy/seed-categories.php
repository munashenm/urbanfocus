<?php

/**
 * Seed IT category hierarchy on cPanel (no Terminal)
 *
 * 1. Git pull latest code to urbanfocus first
 * 2. Copy this file to public_html/seed-categories.php
 * 3. Edit SEED_KEY below to a random secret
 * 4. Visit: https://www.urbanfocus.co.za/seed-categories.php?key=YOUR_SECRET
 * 5. DELETE this file immediately after success
 */

declare(strict_types=1);

const SEED_KEY = 'CHANGE-ME-seed-categories-secret';

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

echo "Seeding categories...\n\n";

try {
    $exitCode = $kernel->call('db:seed', [
        '--class' => 'Database\\Seeders\\CategorySeeder',
        '--force' => true,
    ]);
    echo $kernel->output();
    if ($exitCode === 0) {
        echo "\n✓ Categories seeded.\n";
        echo "Check homepage mega menu and Shop by Category.\n";
    } else {
        echo "\n✗ Exit code: {$exitCode}\n";
    }
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
}

$cacheFiles = glob($laravelRoot.'/bootstrap/cache/*.php') ?: [];
foreach ($cacheFiles as $file) {
    if (basename($file) !== '.gitignore' && @unlink($file)) {
        echo "Deleted cache: {$file}\n";
    }
}

echo "\nDELETE public_html/seed-categories.php now.\n</pre>";
