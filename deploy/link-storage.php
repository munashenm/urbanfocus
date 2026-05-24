<?php

/**
 * Link uploaded files for product images (cPanel Plan B)
 *
 * 1. Upload to public_html/link-storage.php
 * 2. Visit: https://www.urbanfocus.co.za/link-storage.php?key=YOUR_SECRET
 * 3. DELETE this file immediately after use
 */

declare(strict_types=1);

const LINK_KEY = 'CHANGE-ME-link-storage-secret';

if (($_GET['key'] ?? '') !== LINK_KEY) {
    http_response_code(403);
    exit('Forbidden');
}

$home = dirname(__DIR__);
$laravelRoot = $home.'/urbanfocus';
$publicHtml = $home.'/public_html';
$uploads = $laravelRoot.'/storage/app/public';
$link = $publicHtml.'/storage';

header('Content-Type: text/html; charset=utf-8');
echo '<pre>';

if (! is_dir($uploads)) {
    mkdir($uploads, 0755, true);
    echo "Created uploads folder: {$uploads}\n";
}

@chmod($uploads, 0755);

if (is_link($link)) {
    echo "Symlink already exists: {$link}\n";
    echo 'Target: '.readlink($link)."\n";
} elseif (is_dir($link) && ! is_link($link)) {
    $items = array_diff(scandir($link) ?: [], ['.', '..', '.htaccess']);
    if (count($items) === 0) {
        rmdir($link);
        echo "Removed empty public_html/storage folder.\n";
    } else {
        echo "Warning: public_html/storage exists as a folder with files.\n";
        echo "Rename it to storage_backup, then run this script again.\n";
        echo '</pre>';
        exit;
    }
}

if (@symlink($uploads, $link)) {
    echo "Symlink created:\n  {$link}\n  → {$uploads}\n";
} else {
    echo "Could not create symlink (common on shared hosting).\n";
    echo "Product images will still work via Laravel /storage/ route after git pull.\n";
    echo "Ensure urbanfocus/storage/app/public is writable (755 or 775).\n";
}

$productDirs = glob($uploads.'/products/*') ?: [];
echo "\nUpload folders found: ".count($productDirs)."\n";

if ($productDirs) {
    $sample = glob($productDirs[0].'/*')[0] ?? null;
    if ($sample) {
        $relative = str_replace($uploads.'/', '', $sample);
        echo "Sample file on disk: storage/app/public/{$relative}\n";
        echo "Test URL: https://www.urbanfocus.co.za/storage/{$relative}\n";
    }
}

echo "\nDELETE public_html/link-storage.php now.\n</pre>";
