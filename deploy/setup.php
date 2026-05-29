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

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');

if (str_contains(SETUP_KEY, 'CHANGE-ME') || strlen(SETUP_KEY) < 16) {
    http_response_code(403);
    exit('Refusing to run: edit this file and set a strong, unique secret key (16+ chars, no "CHANGE-ME") before use.');
}

if (! hash_equals(SETUP_KEY, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden');
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
echo '<h1>Urban Focus Setup <small style="font-weight:normal;color:#666">v2</small></h1>';
echo '<p>If you do <strong>not</strong> see "Clear old config cache" as the first step below, you still have the old setup file.</p>';

function parseCommand(string $command): array
{
    $parts = preg_split('/\s+/', trim($command));
    $name = array_shift($parts);
    $parameters = [];

    foreach ($parts as $part) {
        if (str_starts_with($part, '--')) {
            if (str_contains($part, '=')) {
                [$key, $value] = explode('=', substr($part, 2), 2);
                $parameters['--'.$key] = $value;
            } else {
                $parameters[$part] = true;
            }
        } else {
            $parameters[] = $part;
        }
    }

    return [$name, $parameters];
}

function generateAppKey(string $envPath): string
{
    $key = 'base64:'.base64_encode(random_bytes(32));
    $env = file_get_contents($envPath);
    $env = preg_replace('/^APP_KEY=.*/m', 'APP_KEY='.$key, $env);

    if (! str_contains($env, 'APP_KEY=')) {
        $env .= "\nAPP_KEY={$key}\n";
    }

    file_put_contents($envPath, $env);

    return $key;
}

function linkPublicStorage(string $laravelRoot): string
{
    $publicPath = rtrim(
        getenv('PUBLIC_PATH') ?: (function () use ($laravelRoot) {
            $envFile = $laravelRoot.'/.env';
            if (file_exists($envFile) && preg_match('/^PUBLIC_PATH=(.*)$/m', file_get_contents($envFile), $m)) {
                return trim($m[1], " \t\"'");
            }

            return dirname($laravelRoot).'/public_html';
        })(),
        '/'
    );

    $target = $laravelRoot.'/storage/app/public';
    $link = $publicPath.'/storage';

    if (! is_dir($target)) {
        mkdir($target, 0755, true);
    }

    if (is_link($link) || file_exists($link)) {
        return "Storage path already exists: {$link}";
    }

    if (@symlink($target, $link)) {
        return "Symlink created: {$link} → {$target}";
    }

    // Fallback when host disables symlinks: mirror directory
    mkdir($link, 0755, true);
    file_put_contents($link.'/.htaccess', "Options -Indexes\n");

    return "Symlink not allowed. Created {$link} — in File Manager, create a symbolic link from public_html/storage to urbanfocus/storage/app/public if your host allows it. Product uploads will still save on the server.";
}

$steps = [
    ['action' => 'artisan', 'command' => 'config:clear', 'label' => 'Clear old config cache'],
    ['action' => 'key', 'label' => 'Generate APP_KEY'],
    ['action' => 'artisan', 'command' => 'migrate --force', 'label' => 'Create database tables'],
    ['action' => 'artisan', 'command' => 'db:seed --force', 'label' => 'Seed admin user and sample data'],
    ['action' => 'storage', 'label' => 'Link storage for uploads'],
    ['action' => 'artisan', 'command' => 'config:cache', 'label' => 'Cache configuration'],
    ['action' => 'artisan', 'command' => 'route:cache', 'label' => 'Cache routes'],
    ['action' => 'artisan', 'command' => 'view:cache', 'label' => 'Cache views'],
];

foreach ($steps as $step) {
    echo '<h3>'.htmlspecialchars($step['label']).'</h3><pre>';
    try {
        if ($step['action'] === 'key') {
            $envPath = $laravelRoot.'/.env';
            if (! file_exists($envPath)) {
                throw new RuntimeException('.env file not found in urbanfocus/');
            }
            $key = generateAppKey($envPath);
            echo "APP_KEY generated successfully.\n✓ Done";
        } elseif ($step['action'] === 'storage') {
            echo htmlspecialchars(linkPublicStorage($laravelRoot))."\n✓ Done";
        } else {
            [$name, $parameters] = parseCommand($step['command']);
            $exitCode = $kernel->call($name, $parameters);
            echo htmlspecialchars($kernel->output());
            echo $exitCode === 0 ? "\n✓ Done" : "\n✗ Exit code: {$exitCode}";
        }
    } catch (Throwable $e) {
        echo 'ERROR: '.htmlspecialchars($e->getMessage());
    }
    echo '</pre>';
}

echo '<p style="background:#d4edda;padding:15px;border-radius:8px"><strong>Setup finished.</strong><br>';
echo 'Test: <a href="/">Homepage</a> | <a href="/login">Login</a> | <a href="/admin">Admin</a><br><br>';
echo '<strong style="color:red">DELETE public_html/setup.php NOW via File Manager.</strong></p>';
echo '</body></html>';
