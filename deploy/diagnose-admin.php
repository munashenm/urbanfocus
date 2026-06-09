<?php

/**
 * Diagnose admin 500 errors on cPanel (no Terminal)
 *
 * 1. Copy urbanfocus/deploy/diagnose-admin.php → public_html/diagnose-admin.php
 * 2. Set DIAG_KEY below
 * 3. Visit: https://www.urbanfocus.co.za/diagnose-admin.php?key=YOUR_SECRET
 * 4. DELETE this file after use
 */

declare(strict_types=1);

const DIAG_KEY = 'CHANGE-ME-diagnose-admin-secret';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');

if (str_contains(DIAG_KEY, 'CHANGE-ME') || strlen(DIAG_KEY) < 16) {
    http_response_code(403);
    exit('Refusing to run: edit this file and set a strong, unique secret key (16+ chars, no "CHANGE-ME") before use.');
}

if (! hash_equals(DIAG_KEY, (string) ($_GET['key'] ?? ''))) {
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
$kernel->bootstrap();

echo "=== Urban Focus admin diagnostics ===\n\n";

$checks = [
    'roles' => 'RBAC roles table',
    'permissions' => 'RBAC permissions table',
    'role_permission' => 'RBAC role_permission table',
    'user_role' => 'RBAC user_role table',
    'audit_logs' => 'Audit logs table',
];

foreach ($checks as $table => $label) {
    $ok = \Illuminate\Support\Facades\Schema::hasTable($table);
    echo ($ok ? '✓' : '✗')." {$label}\n";
}

$userColumns = ['is_active', 'last_login_at', 'failed_login_attempts', 'locked_until'];
echo "\nUser security columns:\n";
foreach ($userColumns as $column) {
    $ok = \Illuminate\Support\Facades\Schema::hasColumn('users', $column);
    echo ($ok ? '✓' : '✗')." users.{$column}\n";
}

echo "\n=== Pending migrations ===\n";
try {
    $kernel->call('migrate:status');
    echo $kernel->output();
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
}

echo "\n=== Recent Laravel log (last 40 lines) ===\n";
$logFile = $laravelRoot.'/storage/logs/laravel.log';
if (is_readable($logFile)) {
    $lines = file($logFile) ?: [];
    echo implode('', array_slice($lines, -40));
} else {
    echo "No readable log at storage/logs/laravel.log\n";
}

echo "\n=== Recommended fix ===\n";
echo "1. clear-cache.php?key=YOUR_SECRET&migrate=1\n";
echo "2. seed-roles.php?key=YOUR_SECRET\n";
echo "3. Delete this diagnose file\n";
echo "</pre>";
