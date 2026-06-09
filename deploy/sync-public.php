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

require __DIR__.'/cpanel-asset-sync.php';

header('Content-Type: text/html; charset=utf-8');
echo '<pre>';

echo "Source: {$sourcePublic}\n";
echo "Target: {$targetPublic}\n\n";

$total = cpanel_sync_public_assets($laravelRoot, $targetPublic);

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

// Legacy deep sync for nested image folders (already handled for top-level by cpanel_sync_public_assets)
$extra = 0;
$extra += copyDirectory($sourcePublic.'/images', $targetPublic.'/images');

echo "\nDone. {$total} top-level asset(s) synced";
if ($extra > 0) {
    echo ", {$extra} nested file(s)";
}
echo ".\n";
echo "Test admin CSS: ".(getenv('APP_URL') ?: 'https://www.urbanfocus.co.za')."/css/admin.css\n";
echo "\nDELETE public_html/sync-public.php now.\n</pre>";
