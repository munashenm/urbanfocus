<?php

/**
 * Sync Laravel public assets → public_html (cPanel Plan B)
 *
 * 1. Upload to public_html/sync-public.php
 * 2. Visit: https://www.urbanfocus.co.za/sync-public.php?key=YOUR_SECRET
 * 3. DELETE this file immediately after use
 */

declare(strict_types=1);

const SYNC_KEY = 'CHANGE-ME-sync-public-secret';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');

if (str_contains(SYNC_KEY, 'CHANGE-ME') || strlen(SYNC_KEY) < 16) {
    http_response_code(403);
    exit('Refusing to run: edit this file and set a strong, unique secret key (16+ chars, no "CHANGE-ME") before use.');
}

if (! hash_equals(SYNC_KEY, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden');
}

$laravelRoot = dirname(__DIR__).'/urbanfocus';
$sourcePublic = $laravelRoot.'/public';
$targetPublic = dirname(__DIR__).'/public_html';

if (! is_dir($sourcePublic)) {
    exit('Error: urbanfocus/public not found.');
}

header('Content-Type: text/html; charset=utf-8');
echo '<pre>';

function copyDirectory(string $source, string $dest): int
{
    if (! is_dir($source)) {
        echo "Skip (missing): {$source}\n";

        return 0;
    }

    if (! is_dir($dest)) {
        mkdir($dest, 0755, true);
        echo "Created: {$dest}\n";
    }

    $count = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $targetPath = $dest.DIRECTORY_SEPARATOR.$iterator->getSubPathName();

        if ($item->isDir()) {
            if (! is_dir($targetPath)) {
                mkdir($targetPath, 0755, true);
            }
        } else {
            copy($item->getPathname(), $targetPath);
            echo "Copied: {$targetPath}\n";
            $count++;
        }
    }

    return $count;
}

echo "Source: {$sourcePublic}\n";
echo "Target: {$targetPublic}\n\n";

$total = 0;
$total += copyDirectory($sourcePublic.'/images', $targetPublic.'/images');
$total += copyDirectory($sourcePublic.'/css', $targetPublic.'/css');
$total += copyDirectory($sourcePublic.'/js', $targetPublic.'/js');

foreach (['favicon.svg', 'favicon.png', 'robots.txt'] as $file) {
    $src = $sourcePublic.'/'.$file;
    if (file_exists($src)) {
        copy($src, $targetPublic.'/'.$file);
        echo "Copied: {$targetPublic}/{$file}\n";
        $total++;
    }
}

echo "\nDone. {$total} file(s) synced.\n";
echo "Test: ".(getenv('APP_URL') ?: 'https://www.urbanfocus.co.za')."/images/logo.png\n";
echo "\nDELETE public_html/sync-public.php now.\n</pre>";
