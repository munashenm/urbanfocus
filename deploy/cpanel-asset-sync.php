<?php

declare(strict_types=1);

/**
 * Copy Laravel public assets into public_html (cPanel Plan B layout).
 */
function cpanel_sync_public_assets(string $laravelRoot, string $publicHtml): int
{
    $sourcePublic = $laravelRoot.'/public';
    $copied = 0;

    if (! is_dir($sourcePublic)) {
        echo "Asset sync skipped: {$sourcePublic} not found.\n";

        return 0;
    }

    echo "=== Sync public assets → public_html ===\n";

    foreach (['css', 'js', 'images'] as $dir) {
        $sourceDir = $sourcePublic.'/'.$dir;
        if (! is_dir($sourceDir)) {
            continue;
        }

        $targetDir = $publicHtml.'/'.$dir;
        $copied += cpanel_copy_public_tree($sourceDir, $targetDir);
    }

    foreach (['favicon.svg', 'favicon.png', 'robots.txt'] as $file) {
        $source = $sourcePublic.'/'.$file;
        if (! is_file($source)) {
            continue;
        }

        $target = $publicHtml.'/'.$file;
        if (copy($source, $target)) {
            echo "Copied: {$file}\n";
            $copied++;
        }
    }

    $adminCss = $publicHtml.'/css/admin.css';
    if (is_file($adminCss)) {
        echo 'admin.css size: '.number_format(filesize($adminCss))." bytes\n";
    } else {
        echo "WARNING: public_html/css/admin.css still missing.\n";
    }

    echo $copied > 0 ? "✓ Synced {$copied} asset file(s).\n\n" : "No asset files copied.\n\n";

    return $copied;
}

/**
 * Recursively copy a public asset tree (e.g. images/brands, images/partners).
 */
function cpanel_copy_public_tree(string $sourceDir, string $targetDir): int
{
    $copied = 0;

    if (! is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
        echo "Created: {$targetDir}\n";
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($sourceDir) + 1);
        $target = $targetDir.'/'.$relative;

        if ($item->isDir()) {
            if (! is_dir($target)) {
                mkdir($target, 0755, true);
            }

            continue;
        }

        $targetParent = dirname($target);
        if (! is_dir($targetParent)) {
            mkdir($targetParent, 0755, true);
        }

        if (copy($item->getPathname(), $target)) {
            echo 'Copied: '.$relative."\n";
            $copied++;
        }
    }

    return $copied;
}
