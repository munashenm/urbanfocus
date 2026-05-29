<?php

/**
 * Emergency fix for 500 errors after deploy (no Terminal)
 *
 * 1. Copy urbanfocus/deploy/emergency-fix.php → public_html/emergency-fix.php
 * 2. Edit FIX_KEY below
 * 3. Visit: https://www.urbanfocus.co.za/emergency-fix.php?key=YOUR_SECRET
 * 4. DELETE this file immediately after use
 */

declare(strict_types=1);

const FIX_KEY = 'CHANGE-ME-emergency-fix';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');

if (str_contains(FIX_KEY, 'CHANGE-ME') || strlen(FIX_KEY) < 16) {
    http_response_code(403);
    exit('Refusing to run: edit this file and set a strong, unique secret key (16+ chars, no "CHANGE-ME") before use.');
}

if (! hash_equals(FIX_KEY, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden');
}

$laravelRoot = dirname(__DIR__).'/urbanfocus';
header('Content-Type: text/html; charset=utf-8');
echo '<pre>';

echo "=== Paths ===\n";
echo "Laravel root: {$laravelRoot}\n";
echo "Exists: ".(is_dir($laravelRoot) ? 'yes' : 'NO')."\n\n";

$required = [
    'vendor/autoload.php',
    'bootstrap/app.php',
    'app/helpers.php',
    'routes/web.php',
    '.env',
];

echo "=== Required files ===\n";
$missing = [];
foreach ($required as $file) {
    $path = $laravelRoot.'/'.$file;
    if (file_exists($path)) {
        echo "OK   {$file}\n";
    } else {
        echo "MISSING   {$file}\n";
        $missing[] = $file;
    }
}

echo "\n=== Clear bootstrap cache (fixes most 500s) ===\n";
$cacheFiles = glob($laravelRoot.'/bootstrap/cache/*.php') ?: [];
$cleared = 0;
foreach ($cacheFiles as $file) {
    if (basename($file) !== '.gitignore' && @unlink($file)) {
        echo "Deleted: ".basename($file)."\n";
        $cleared++;
    }
}
if ($cleared === 0) {
    echo "No cache files (already clear).\n";
}

echo "\n=== Laravel log (last error) ===\n";
$logFile = $laravelRoot.'/storage/logs/laravel.log';
if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES) ?: [];
    $errorStart = null;
    for ($i = count($lines) - 1; $i >= 0; $i--) {
        if (str_contains($lines[$i], '.ERROR:') || str_contains($lines[$i], 'local.ERROR')) {
            $errorStart = $i;
            break;
        }
    }
    if ($errorStart !== null) {
        echo implode("\n", array_slice($lines, $errorStart, min(8, count($lines) - $errorStart)))."\n";
        echo "\n(... stack trace continues — see storage/logs/laravel.log)\n";
    } else {
        echo implode("\n", array_slice($lines, -10))."\n";
    }
} else {
    echo "No log file yet.\n";
}

echo "\n=== Boot test ===\n";
if ($missing) {
    echo "Cannot boot — missing files above. Run git pull in urbanfocus.\n";
} elseif (! file_exists($laravelRoot.'/vendor/autoload.php')) {
    echo "Cannot boot — vendor/ missing. Upload vendor or run composer install.\n";
} else {
    try {
        require $laravelRoot.'/vendor/autoload.php';
        $app = require_once $laravelRoot.'/bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->call('config:clear');
        echo trim($kernel->output())."\n";
        $kernel->call('route:clear');
        echo trim($kernel->output())."\n";
        $kernel->call('view:clear');
        echo trim($kernel->output())."\n";
        $kernel->call('cache:clear');
        echo trim($kernel->output())."\n";
        echo "\n✓ Laravel booted OK. Caches cleared.\n";

        echo "\n=== Homepage test ===\n";
        try {
            $host = $_SERVER['HTTP_HOST'] ?? 'www.urbanfocus.co.za';
            $http = $app->make(Illuminate\Contracts\Http\Kernel::class);
            $request = Illuminate\Http\Request::create("https://{$host}/", 'GET', [], [], [], [
                'HTTP_HOST' => $host,
                'HTTPS' => 'on',
            ]);
            $response = $http->handle($request);
            echo 'HTTP status: '.$response->getStatusCode()."\n";
            if ($response->getStatusCode() < 400) {
                echo "✓ Homepage OK — try https://{$host}/\n";
            } else {
                echo substr($response->getContent(), 0, 400)."\n";
            }
            $http->terminate($request, $response);
        } catch (Throwable $e) {
            echo "HOMEPAGE ERROR: ".$e->getMessage()."\n";
            echo $e->getFile().':'.$e->getLine()."\n";
        }
    } catch (Throwable $e) {
        echo "BOOT FAILED:\n";
        echo $e->getMessage()."\n\n";
        echo $e->getFile().':'.$e->getLine()."\n";
    }
}

echo "\n=== Permissions check ===\n";
foreach (['storage', 'storage/logs', 'storage/framework', 'bootstrap/cache'] as $dir) {
    $path = $laravelRoot.'/'.$dir;
    echo $dir.': '.(is_writable($path) ? 'writable' : 'NOT WRITABLE')."\n";
}

echo "\nDELETE public_html/emergency-fix.php now.\n</pre>";
