<?php

/**
 * Copy product uploads to public_html/storage (cPanel Plan B)
 *
 * Run once if product images 404 after upload to urbanfocus/storage/app/public.
 *
 * 1. Copy to public_html/sync-storage.php
 * 2. Visit: https://www.urbanfocus.co.za/sync-storage.php?key=YOUR_SECRET
 * 3. DELETE this file immediately after success
 */

declare(strict_types=1);

const SYNC_STORAGE_KEY = 'CHANGE-ME-sync-storage-secret';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');

if (str_contains(SYNC_STORAGE_KEY, 'CHANGE-ME') || strlen(SYNC_STORAGE_KEY) < 16) {
    http_response_code(403);
    exit('Refusing to run: edit this file and set a strong, unique secret key (16+ chars, no "CHANGE-ME") before use.');
}

if (! hash_equals(SYNC_STORAGE_KEY, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden');
}

$home = dirname(__DIR__);
$source = $home.'/urbanfocus/storage/app/public';
$target = $home.'/public_html/storage';

header('Content-Type: text/plain; charset=utf-8');

if (! is_dir($source)) {
    exit("Source not found: {$source}\n");
}

if (! is_dir($target)) {
    mkdir($target, 0755, true);
    echo "Created {$target}\n";
}

$count = 0;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (! $file->isFile()) {
        continue;
    }

    $relative = substr($file->getPathname(), strlen($source) + 1);
    $dest = $target.'/'.$relative;
    $destDir = dirname($dest);

    if (! is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }

    if (! file_exists($dest) || filesize($dest) !== $file->getSize()) {
        copy($file->getPathname(), $dest);
        echo "Copied: {$relative}\n";
        $count++;
    }
}

echo "\nDone. {$count} file(s) synced to public_html/storage.\n";
echo "Test a product image URL, then DELETE public_html/sync-storage.php.\n";
