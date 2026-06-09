<?php

/**
 * Diagnose and fix /admin 500 errors on cPanel (no Terminal)
 *
 * 1. Copy urbanfocus/deploy/fix-admin.php → public_html/fix-admin.php
 * 2. Set FIX_KEY below
 * 3. Visit: https://urbanfocus.co.za/fix-admin.php?key=YOUR_SECRET
 * 4. Optional full setup: add &migrate=1&seed=1
 * 5. DELETE this file after use
 */

declare(strict_types=1);

const FIX_KEY = 'CHANGE-ME-fix-admin-secret';

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
echo '<pre style="font:14px/1.5 monospace;white-space:pre-wrap">';

echo "=== Urban Focus admin fix ===\n";
echo 'Time: '.date('Y-m-d H:i:s')."\n";
echo "Laravel root: {$laravelRoot}\n\n";

if (! is_dir($laravelRoot)) {
    exit("STOP: urbanfocus folder not found next to public_html.\n");
}

$mustExist = [
    'vendor/autoload.php',
    'bootstrap/app.php',
    'app/Support/AdminRbac.php',
    'app/Concerns/HasAdminRoles.php',
    'config/permissions.php',
    'database/migrations/2026_06_09_000001_create_admin_rbac_tables.php',
    'resources/views/layouts/admin.blade.php',
    'resources/views/partials/admin-sidebar.blade.php',
    'resources/views/admin/partials/rbac-setup-alert.blade.php',
    '.env',
];

echo "=== Admin upgrade files ===\n";
$missing = [];
foreach ($mustExist as $file) {
    $ok = file_exists($laravelRoot.'/'.$file);
    echo ($ok ? 'OK   ' : 'MISSING   ').$file."\n";
    if (! $ok) {
        $missing[] = $file;
    }
}

if ($missing) {
    echo "\n⚠ Git pull did not complete. cPanel → Git Version Control → Pull on urbanfocus.\n";
    echo "If pull is blocked, delete urbanfocus/deploy/fix-admin.php then pull again.\n\n";
}

echo "\n=== Clearing caches ===\n";
foreach (glob($laravelRoot.'/bootstrap/cache/*.php') ?: [] as $file) {
    if (basename($file) !== '.gitignore') {
        @unlink($file);
    }
}
foreach (glob($laravelRoot.'/storage/framework/views/*.php') ?: [] as $file) {
    @unlink($file);
}
echo "Bootstrap + view caches cleared.\n";

require $laravelRoot.'/deploy/cpanel-asset-sync.php';
cpanel_sync_public_assets($laravelRoot, dirname(__DIR__).'/public_html');

if (! file_exists($laravelRoot.'/vendor/autoload.php')) {
    exit("\nSTOP: vendor/ missing.\n");
}

require $laravelRoot.'/vendor/autoload.php';
/** @var \Illuminate\Foundation\Application $app */
$app = require_once $laravelRoot.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

foreach (['config:clear', 'route:clear', 'view:clear', 'cache:clear'] as $cmd) {
    try {
        $kernel->call($cmd);
        echo trim($kernel->output())."\n";
    } catch (Throwable $e) {
        echo "{$cmd} failed: ".$e->getMessage()."\n";
    }
}

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
    echo "\n=== Seeding roles ===\n";
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

echo "\n=== RBAC tables ===\n";
foreach (['roles', 'permissions', 'role_permission', 'user_role', 'audit_logs'] as $table) {
    $ok = Illuminate\Support\Facades\Schema::hasTable($table);
    echo ($ok ? '✓' : '✗')." {$table}\n";
}

echo "\n=== Admin route test ===\n";
$host = $_SERVER['HTTP_HOST'] ?? 'urbanfocus.co.za';
try {
    $adminUser = App\Models\User::where('is_admin', true)->orderBy('id')->first();

    if (! $adminUser) {
        echo "No is_admin=1 user found in database.\n";
    } else {
        echo "Testing as: {$adminUser->email}\n";
        Illuminate\Support\Facades\Auth::login($adminUser);

        $http = $app->make(Illuminate\Contracts\Http\Kernel::class);
        $request = Illuminate\Http\Request::create("https://{$host}/admin", 'GET', [], [], [], [
            'HTTP_HOST' => $host,
            'HTTPS' => 'on',
            'SERVER_NAME' => $host,
        ]);
        $request->setLaravelSession($app->make('session.store'));

        $response = $http->handle($request);
        $status = $response->getStatusCode();
        echo "HTTP status: {$status}\n";

        if ($status >= 400) {
            echo "Response preview:\n";
            echo htmlspecialchars(substr($response->getContent(), 0, 800))."\n";
        } else {
            echo "✓ /admin rendered successfully for admin user.\n";
        }

        $http->terminate($request, $response);
        Illuminate\Support\Facades\Auth::logout();
    }
} catch (Throwable $e) {
    echo "ADMIN ERROR:\n";
    echo $e->getMessage()."\n";
    echo $e->getFile().':'.$e->getLine()."\n";
}

echo "\n=== Last error in laravel.log ===\n";
$logFile = $laravelRoot.'/storage/logs/laravel.log';
if (is_readable($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES) ?: [];
    for ($i = count($lines) - 1; $i >= 0; $i--) {
        if (str_contains($lines[$i], '.ERROR:') || str_contains($lines[$i], 'local.ERROR')) {
            echo implode("\n", array_slice($lines, $i, min(20, count($lines) - $i)))."\n";
            break;
        }
    }
} else {
    echo "No readable log file.\n";
}

echo "\n=== Recommended URL ===\n";
echo "Full setup: fix-admin.php?key=YOUR_SECRET&migrate=1&seed=1\n";
echo "Then open: https://{$host}/admin\n";
echo "\nDELETE public_html/fix-admin.php now.\n</pre>";
