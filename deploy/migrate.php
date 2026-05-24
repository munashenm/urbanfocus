<?php

/**
 * Run pending Laravel migrations on cPanel (no Terminal)
 *
 * 1. Git pull latest code to urbanfocus first
 * 2. Upload this file to public_html/migrate.php
 * 3. Visit: https://www.urbanfocus.co.za/migrate.php?key=YOUR_SECRET
 * 4. DELETE this file immediately after success
 */

declare(strict_types=1);

const MIGRATE_KEY = 'CHANGE-ME-migrate-secret';

if (($_GET['key'] ?? '') !== MIGRATE_KEY) {
    http_response_code(403);
    exit('Forbidden. Add ?key=YOUR_SECRET to the URL.');
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

echo "Running pending migrations...\n\n";

try {
    $exitCode = $kernel->call('migrate', ['--force' => true]);
    echo $kernel->output();
    if ($exitCode === 0) {
        echo "\n✓ Migrations complete.\n";
    } else {
        echo "\n✗ Migration exit code: {$exitCode}\n";
        echo "If you see 'already exists', pull the latest code (idempotent migration fix) and run this script again.\n";
    }
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n\n";
    if (str_contains($e->getMessage(), 'already exists')) {
        echo "Fix: Git pull latest urbanfocus, re-copy this migrate.php to public_html, run again.\n";
        echo "The updated migration skips tables/columns that already exist.\n";
    }
    exit;
}

$cacheFiles = glob($laravelRoot.'/bootstrap/cache/*.php') ?: [];
foreach ($cacheFiles as $file) {
    if (basename($file) !== '.gitignore' && @unlink($file)) {
        echo "Deleted cache: {$file}\n";
    }
}

echo "\nDone. Test /admin/brands and /b2b/quote\n";
echo "DELETE public_html/migrate.php now.\n</pre>";
