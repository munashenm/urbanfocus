<?php

/**
 * Emergency fix for 500 errors after deploy (no Terminal)
 * Upload to public_html/emergency-fix.php, visit with key, then DELETE.
 */

declare(strict_types=1);

const FIX_KEY = 'CHANGE-ME-emergency-fix';

if (($_GET['key'] ?? '') !== FIX_KEY) {
    http_response_code(403);
    exit('Forbidden');
}

$laravelRoot = dirname(__DIR__).'/urbanfocus';
header('Content-Type: text/html; charset=utf-8');
echo '<pre>';

$required = [
    'routes/web.php',
    'routes/api.php',
    'bootstrap/app.php',
    'app/Http/Controllers/Admin/CatalogController.php',
    'app/Http/Controllers/Admin/DashboardController.php',
    'resources/views/layouts/admin.blade.php',
];

echo "=== File check ===\n";
$missing = [];
foreach ($required as $file) {
    $path = $laravelRoot.'/'.$file;
    if (file_exists($path)) {
        echo "OK  {$file}\n";
    } else {
        echo "MISSING  {$file}\n";
        $missing[] = $file;
    }
}

echo "\n=== Clear cache ===\n";
$cacheFiles = glob($laravelRoot.'/bootstrap/cache/*.php') ?: [];
foreach ($cacheFiles as $file) {
    if (basename($file) !== '.gitignore' && @unlink($file)) {
        echo "Deleted: {$file}\n";
    }
}

if (file_exists($laravelRoot.'/vendor/autoload.php')) {
    require $laravelRoot.'/vendor/autoload.php';
    $app = require_once $laravelRoot.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    try {
        $kernel->call('config:clear');
        echo $kernel->output();
        $kernel->call('route:clear');
        echo $kernel->output();
        $kernel->call('view:clear');
        echo $kernel->output();
        echo "\nArtisan caches cleared.\n";
    } catch (Throwable $e) {
        echo "Artisan error: ".$e->getMessage()."\n";
    }
}

echo "\n=== Result ===\n";
if ($missing) {
    echo "Upload missing files via Git pull, then run this script again.\n";
} else {
    echo "Try https://www.urbanfocus.co.za/admin now.\n";
}
echo "\nDELETE public_html/emergency-fix.php now.\n</pre>";
