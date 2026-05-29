<?php

/**
 * Diagnose and fix /blog 500 errors on cPanel (no Terminal)
 *
 * 1. Git pull latest code
 * 2. Copy urbanfocus/deploy/diagnose-blog.php → public_html/diagnose-blog.php
 * 3. Set DIAGNOSE_KEY below
 * 4. Visit: https://www.urbanfocus.co.za/diagnose-blog.php?key=YOUR_SECRET
 * 5. DELETE this file immediately after use
 */

declare(strict_types=1);

const DIAGNOSE_KEY = 'CHANGE-ME-diagnose-blog';

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
$runMigrate = isset($_GET['migrate']);

header('Content-Type: text/html; charset=utf-8');
echo '<pre>';

require $laravelRoot.'/vendor/autoload.php';
$app = require_once $laravelRoot.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Blog diagnostic ===\n\n";

$checks = [
    'config/blog.php' => is_file($laravelRoot.'/config/blog.php'),
    'articles table' => Illuminate\Support\Facades\Schema::hasTable('articles'),
    'articles.category' => Illuminate\Support\Facades\Schema::hasColumn('articles', 'category'),
    'articles.is_featured' => Illuminate\Support\Facades\Schema::hasColumn('articles', 'is_featured'),
    'authors table' => Illuminate\Support\Facades\Schema::hasTable('authors'),
    'articles.author_id' => Illuminate\Support\Facades\Schema::hasColumn('articles', 'author_id'),
    'tags table' => Illuminate\Support\Facades\Schema::hasTable('tags'),
    'article_tag table' => Illuminate\Support\Facades\Schema::hasTable('article_tag'),
];

foreach ($checks as $label => $ok) {
    echo ($ok ? 'OK   ' : 'MISSING   ').$label."\n";
}

$needsMigrate = ! $checks['articles.category']
    || ! $checks['articles.is_featured']
    || ! $checks['authors table']
    || ! $checks['tags table'];

if ($needsMigrate) {
    echo "\n⚠ Blog migrations not fully applied.\n";
    if ($runMigrate) {
        echo "\nRunning migrate --force...\n\n";
        try {
            $exit = $kernel->call('migrate', ['--force' => true]);
            echo $kernel->output();
            echo $exit === 0 ? "\n✓ Migrations complete.\n" : "\n✗ Migration failed.\n";
        } catch (Throwable $e) {
            echo 'ERROR: '.$e->getMessage()."\n";
        }
    } else {
        echo "Re-run with &migrate=1 to apply migrations automatically.\n";
    }
}

echo "\n=== /blog HTTP test ===\n";
try {
    $host = $_SERVER['HTTP_HOST'] ?? 'www.urbanfocus.co.za';
    $http = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $request = Illuminate\Http\Request::create("https://{$host}/blog", 'GET', [], [], [], [
        'HTTP_HOST' => $host,
        'HTTPS' => 'on',
    ]);
    $response = $http->handle($request);
    echo 'Status: '.$response->getStatusCode()."\n";
    if ($response->getStatusCode() >= 400) {
        echo substr(strip_tags($response->getContent()), 0, 400)."\n";
    } else {
        echo "✓ /blog loads successfully.\n";
    }
    $http->terminate($request, $response);

    $request = Illuminate\Http\Request::create("https://{$host}/blog/category/networking", 'GET', [], [], [], [
        'HTTP_HOST' => $host,
        'HTTPS' => 'on',
    ]);
    $response = $http->handle($request);
    echo '/blog/category/networking status: '.$response->getStatusCode()."\n";
    if ($response->getStatusCode() >= 400) {
        echo substr(strip_tags($response->getContent()), 0, 400)."\n";
    } else {
        echo "✓ Category archive loads successfully.\n";
    }
    $http->terminate($request, $response);
} catch (Throwable $e) {
    echo "ERROR: ".$e->getMessage()."\n";
    echo $e->getFile().':'.$e->getLine()."\n";
}

echo "\nDELETE public_html/diagnose-blog.php now.\n</pre>";
