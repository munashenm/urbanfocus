<?php

/**
 * One-shot 500 error fix for cPanel — NO git pull needed.
 *
 * HOW TO USE (File Manager only):
 * 1. Go to public_html → + File → name it: fix-500.php
 * 2. Edit → paste this ENTIRE file → Save
 * 3. Change FIX_KEY on line 18 to any password you choose
 * 4. Visit: https://www.urbanfocus.co.za/fix-500.php?key=YOUR_PASSWORD
 * 5. Read the output — it shows the exact error if still broken
 * 6. DELETE fix-500.php from public_html when done
 */

declare(strict_types=1);

const FIX_KEY = 'urbanfocus-fix-2026';

if (($_GET['key'] ?? '') !== FIX_KEY) {
    http_response_code(403);
    exit('Forbidden — add ?key=YOUR_PASSWORD to the URL (must match FIX_KEY in this file).');
}

$laravelRoot = dirname(__DIR__).'/urbanfocus';

header('Content-Type: text/html; charset=utf-8');
echo '<pre style="font:14px/1.5 monospace;white-space:pre-wrap">';

echo "=== Urban Focus 500 Fix ===\n";
echo 'Time: '.date('Y-m-d H:i:s')."\n";
echo "Laravel root: {$laravelRoot}\n";
echo 'Exists: '.(is_dir($laravelRoot) ? 'yes' : 'NO — wrong path!')."\n\n";

if (! is_dir($laravelRoot)) {
    exit("STOP: urbanfocus folder not found next to public_html.\n");
}

// --- 1. Check critical files (incomplete git pull is a common cause) ---
echo "=== Critical files ===\n";
$mustExist = [
    'vendor/autoload.php',
    'bootstrap/app.php',
    'app/helpers.php',
    'app/Http/Controllers/HomeController.php',
    'config/homepage.php',
    'config/trust.php',
    'config/mega-menu.php',
    'config/partners.php',
    'resources/views/home.blade.php',
    'resources/views/partials/trust-badges.blade.php',
    'resources/views/partials/testimonials.blade.php',
    'resources/views/partials/section-header.blade.php',
    '.env',
];
$missing = [];
foreach ($mustExist as $file) {
    $ok = file_exists($laravelRoot.'/'.$file);
    echo ($ok ? 'OK   ' : 'MISSING   ').$file."\n";
    if (! $ok) {
        $missing[] = $file;
    }
}

if ($missing) {
    echo "\n⚠ MISSING FILES — git pull did not complete.\n";
    echo "Fix: cPanel → File Manager → delete these if they block pull:\n";
    echo "  urbanfocus/deploy/emergency-fix.php\n";
    echo "  urbanfocus/deploy/migrate.php\n";
    echo "Then Git Version Control → Pull again.\n\n";
}

// --- 2. Clear ALL caches (bootstrap + compiled views) ---
echo "\n=== Clearing caches ===\n";

$deleted = 0;
foreach (glob($laravelRoot.'/bootstrap/cache/*.php') ?: [] as $file) {
    if (basename($file) !== '.gitignore' && @unlink($file)) {
        echo 'Deleted bootstrap cache: '.basename($file)."\n";
        $deleted++;
    }
}

$viewsDir = $laravelRoot.'/storage/framework/views';
if (is_dir($viewsDir)) {
    foreach (glob($viewsDir.'/*.php') ?: [] as $file) {
        if (@unlink($file)) {
            $deleted++;
        }
    }
    echo "Cleared compiled views in storage/framework/views/\n";
}

if ($deleted === 0) {
    echo "Cache folders already empty.\n";
}

// --- 3. Laravel artisan cache clear ---
echo "\n=== Laravel cache commands ===\n";
if (! file_exists($laravelRoot.'/vendor/autoload.php')) {
    echo "SKIP — vendor/ missing.\n";
} else {
    try {
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
        echo "\n✓ Laravel booted OK.\n";

        // --- 4. Homepage test ---
        echo "\n=== Homepage test ===\n";
        try {
            $host = $_SERVER['HTTP_HOST'] ?? 'www.urbanfocus.co.za';
            $http = $app->make(Illuminate\Contracts\Http\Kernel::class);
            $request = Illuminate\Http\Request::create("https://{$host}/", 'GET', [], [], [], [
                'HTTP_HOST' => $host,
                'HTTPS' => 'on',
                'SERVER_NAME' => $host,
            ]);
            $response = $http->handle($request);
            $status = $response->getStatusCode();
            echo "HTTP status: {$status}\n";
            if ($status < 400) {
                echo "✓ SUCCESS — homepage works! Open https://{$host}/\n";
            } else {
                echo "Still failing. Response preview:\n";
                echo htmlspecialchars(substr($response->getContent(), 0, 600))."\n";
            }
            $http->terminate($request, $response);
        } catch (Throwable $e) {
            echo "HOMEPAGE ERROR:\n";
            echo $e->getMessage()."\n";
            echo $e->getFile().':'.$e->getLine()."\n";
        }
    } catch (Throwable $e) {
        echo "BOOT FAILED: ".$e->getMessage()."\n";
        echo $e->getFile().':'.$e->getLine()."\n";
    }
}

// --- 5. Last error from log ---
echo "\n=== Last error in laravel.log ===\n";
$logFile = $laravelRoot.'/storage/logs/laravel.log';
if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES) ?: [];
    for ($i = count($lines) - 1; $i >= 0; $i--) {
        if (str_contains($lines[$i], '.ERROR:') || str_contains($lines[$i], 'local.ERROR')) {
            echo implode("\n", array_slice($lines, $i, min(15, count($lines) - $i)))."\n";
            break;
        }
    }
} else {
    echo "No log file.\n";
}

echo "\n=== Permissions ===\n";
foreach (['storage', 'storage/logs', 'storage/framework/views', 'bootstrap/cache'] as $dir) {
    $path = $laravelRoot.'/'.$dir;
    echo $dir.': '.(is_dir($path) ? (is_writable($path) ? 'writable' : 'NOT WRITABLE') : 'MISSING')."\n";
}

echo "\n=== Next steps ===\n";
if ($missing) {
    echo "1. Fix git pull (delete blocking deploy/*.php files, pull again)\n";
    echo "2. Re-run this script\n";
} else {
    echo "1. Open https://www.urbanfocus.co.za/\n";
    echo "2. If still 500, copy the 'Last error' section above and send it for help\n";
}
echo "\nDELETE public_html/fix-500.php NOW.\n</pre>";
