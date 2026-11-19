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

    // public_html/index.php is the live front controller (it boots ../urbanfocus).
    // Never copy public/index.php over it.
    foreach (['favicon.svg', 'favicon.png'] as $file) {
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

    $htaccess = $sourcePublic.'/.htaccess';
    if (is_file($htaccess) && cpanel_copy_htaccess($htaccess, $publicHtml.'/.htaccess')) {
        $copied++;
    }

    cpanel_remove_public_strays($publicHtml);

    $staleRobots = $publicHtml.'/robots.txt';
    if (is_file($staleRobots) && @unlink($staleRobots)) {
        echo "Removed leftover static robots.txt so Laravel can serve crawler rules.\n";
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
 * Delete repo files that must not be downloadable from the web root.
 * index.php is the live front controller and is never removed.
 */
function cpanel_remove_public_strays(string $publicHtml): void
{
    $names = [
        'README.md',
        'readme.md',
        'README.txt',
        'composer.json',
        'composer.lock',
        'package.json',
        'package-lock.json',
        'artisan',
        '.env',
        '.env.example',
        '.gitignore',
        '.gitattributes',
    ];

    foreach ($names as $name) {
        $path = $publicHtml.'/'.$name;
        if (is_file($path) && @unlink($path)) {
            echo "Removed stray web file: {$name}\n";
        }
    }
}

/**
 * Copy .htaccess and keep a cPanel PHP handler that exists only on the server.
 */
function cpanel_copy_htaccess(string $source, string $target): bool
{
    $contents = file_get_contents($source);
    if ($contents === false) {
        echo "WARNING: could not read {$source}\n";

        return false;
    }

    if (is_file($target)) {
        $existing = file_get_contents($target);
        $handler = '';
        if (is_string($existing) && preg_match(
            '/# php -- BEGIN cPanel-generated handler.*?# php -- END cPanel-generated handler, do not edit\s*/s',
            $existing,
            $match
        )) {
            $handler = $match[0];
        }

        if ($handler !== '' && ! str_contains($contents, '# php -- BEGIN cPanel-generated handler')) {
            $contents = rtrim($contents)."\n\n".$handler;
            if (! str_ends_with($contents, "\n")) {
                $contents .= "\n";
            }
            echo "Kept cPanel PHP handler from existing .htaccess\n";
        }
    }

    $directory = dirname($target);
    if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
        echo "WARNING: could not create {$directory}\n";

        return false;
    }

    if (file_put_contents($target, $contents) === false) {
        echo "WARNING: could not write {$target}\n";

        return false;
    }

    echo "Copied: .htaccess\n";

    return true;
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

        if (basename($item->getPathname()) === 'index.php') {
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
