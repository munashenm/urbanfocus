<?php

/**
 * Diagnose homepage 500 errors on cPanel (no Terminal)
 *
 * 1. Copy urbanfocus/deploy/diagnose-home.php → public_html/diagnose-home.php
 * 2. Edit DIAGNOSE_KEY below
 * 3. Visit: https://www.urbanfocus.co.za/diagnose-home.php?key=YOUR_SECRET
 * 4. DELETE this file immediately after use
 */

declare(strict_types=1);

const DIAGNOSE_KEY = 'CHANGE-ME-diagnose-home';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');

if (str_contains(DIAGNOSE_KEY, 'CHANGE-ME') || strlen(DIAGNOSE_KEY) < 16) {
    http_response_code(403);
    exit('Refusing to run: edit this file and set a strong, unique secret key (16+ chars, no "CHANGE-ME") before use.');
}

if (! hash_equals(DIAGNOSE_KEY, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden');
}

$laravelRoot = dirname(__DIR__).'/urbanfocus';

header('Content-Type: text/html; charset=utf-8');
echo '<pre>';

echo "=== Paths ===\n";
echo "Laravel root: {$laravelRoot}\n\n";

$configFiles = [
    'config/homepage.php',
    'config/trust.php',
    'config/mega-menu.php',
    'config/partners.php',
    'config/social.php',
];

echo "=== Config files ===\n";
foreach ($configFiles as $file) {
    $path = $laravelRoot.'/'.$file;
    echo (file_exists($path) ? 'OK   ' : 'MISSING   ').$file."\n";
}

echo "\n=== Clear all caches ===\n";
foreach (glob($laravelRoot.'/bootstrap/cache/*.php') ?: [] as $file) {
    if (basename($file) !== '.gitignore' && @unlink($file)) {
        echo 'Deleted: '.basename($file)."\n";
    }
}

if (! file_exists($laravelRoot.'/vendor/autoload.php')) {
    exit("\nError: vendor/ missing.\n");
}

require $laravelRoot.'/vendor/autoload.php';
$app = require_once $laravelRoot.'/bootstrap/app.php';
$console = $app->make(Illuminate\Contracts\Console\Kernel::class);

foreach (['config:clear', 'route:clear', 'view:clear', 'cache:clear'] as $cmd) {
    try {
        $console->call($cmd);
        echo trim($console->output())."\n";
    } catch (Throwable $e) {
        echo "{$cmd} failed: ".$e->getMessage()."\n";
    }
}

echo "\n=== Homepage HTTP test ===\n";
try {
    $host = $_SERVER['HTTP_HOST'] ?? 'www.urbanfocus.co.za';
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $request = Illuminate\Http\Request::create("https://{$host}/", 'GET', [], [], [], [
        'HTTP_HOST' => $host,
        'HTTPS' => 'on',
        'SERVER_NAME' => $host,
    ]);
    $response = $kernel->handle($request);
    $status = $response->getStatusCode();
    echo "HTTP status: {$status}\n";

    if ($status >= 500) {
        echo "\nResponse preview:\n";
        echo substr($response->getContent(), 0, 800)."\n";
    } elseif ($status >= 400) {
        echo "Unexpected client error — check routes.\n";
    } else {
        echo "✓ Homepage rendered successfully.\n";
        echo "Try: https://{$host}/\n";
    }

    $kernel->terminate($request, $response);
} catch (Throwable $e) {
    echo "HOMEPAGE FAILED:\n";
    echo $e->getMessage()."\n\n";
    echo $e->getFile().':'.$e->getLine()."\n\n";
    echo substr($e->getTraceAsString(), 0, 2000)."\n";
}

echo "\n=== Laravel log (last error) ===\n";
$logFile = $laravelRoot.'/storage/logs/laravel.log';
if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES) ?: [];
    for ($i = count($lines) - 1; $i >= 0; $i--) {
        if (str_contains($lines[$i], '.ERROR:') || str_contains($lines[$i], 'local.ERROR')) {
            echo implode("\n", array_slice($lines, $i, min(10, count($lines) - $i)))."\n";
            break;
        }
    }
}

echo "\nDELETE public_html/diagnose-home.php now.\n</pre>";
