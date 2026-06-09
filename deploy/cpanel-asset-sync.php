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
        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
            echo "Created: {$targetDir}\n";
        }

        foreach (glob($sourceDir.'/*') ?: [] as $file) {
            if (! is_file($file)) {
                continue;
            }

            $target = $targetDir.'/'.basename($file);
            if (copy($file, $target)) {
                echo 'Copied: '.basename($file)."\n";
                $copied++;
            }
        }
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
