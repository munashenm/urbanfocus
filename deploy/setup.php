<?php

/**
 * ONE-TIME cPanel setup script (no Terminal required)
 *
 * 1. Complete File Manager folder restructure first (see deploy/NO_TERMINAL_GUIDE.md)
 * 2. Upload this file to public_html/setup.php
 * 3. Edit SETUP_KEY below to a random secret string
 * 4. Visit: https://www.urbanfocus.co.za/setup.php?key=YOUR_SECRET
 * 5. DELETE this file immediately after success
 */

declare(strict_types=1);

const SETUP_KEY = 'CHANGE-ME-to-a-random-string-12345';

if (($_GET['key'] ?? '') !== SETUP_KEY) {
    http_response_code(403);
    exit('Forbidden. Add ?key=YOUR_SECRET to the URL.');
}

$laravelRoot = dirname(__DIR__).'/urbanfocus';

if (! is_dir($laravelRoot)) {
    exit('Error: urbanfocus folder not found at '.$laravelRoot);
}

if (! file_exists($laravelRoot.'/vendor/autoload.php')) {
    exit('Error: vendor/ folder missing. Upload vendor or run Composer first.');
}

require $laravelRoot.'/vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require_once $laravelRoot.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><title>Urban Focus Setup</title></head><body style="font-family:sans-serif;max-width:800px;margin:40px auto;padding:20px">';
echo '<h1>Urban Focus Setup</h1>';

$steps = [
    'key:generate --force' => 'Generate APP_KEY',
    'migrate --force' => 'Create database tables',
    'db:seed --force' => 'Seed admin user and sample data',
    'storage:link' => 'Link storage for uploads',
    'config:cache' => 'Cache configuration',
    'route:cache' => 'Cache routes',
    'view:cache' => 'Cache views',
];

foreach ($steps as $command => $label) {
    echo '<h3>'.htmlspecialchars($label).'</h3><pre>';
    try {
        $parts = preg_split('/\s+/', $command);
        $exitCode = $kernel->call($parts[0], array_slice($parts, 1));
        echo htmlspecialchars($kernel->output());
        echo $exitCode === 0 ? "\n✓ Done" : "\n✗ Exit code: {$exitCode}";
    } catch (Throwable $e) {
        echo 'ERROR: '.htmlspecialchars($e->getMessage());
    }
    echo '</pre>';
}

echo '<p style="background:#d4edda;padding:15px;border-radius:8px"><strong>Setup finished.</strong><br>';
echo 'Test: <a href="/">Homepage</a> | <a href="/login">Login</a> | <a href="/admin">Admin</a><br><br>';
echo '<strong style="color:red">DELETE public_html/setup.php NOW via File Manager.</strong></p>';
echo '</body></html>';
