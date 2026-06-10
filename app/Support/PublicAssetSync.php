<?php

namespace App\Support;

class PublicAssetSync
{
    public static function syncIfNeeded(): void
    {
        $publicPath = env('PUBLIC_PATH');

        if (! $publicPath || ! is_dir($publicPath)) {
            return;
        }

        $sourceRoot = base_path('public');

        if (! is_dir($sourceRoot)) {
            return;
        }

        $sourceReal = realpath($sourceRoot);
        $targetReal = realpath($publicPath);

        if ($sourceReal && $targetReal && $sourceReal === $targetReal) {
            return;
        }

        $canarySource = $sourceRoot.'/css/admin.css';
        $canaryTarget = $publicPath.'/css/admin.css';

        if (! is_file($canarySource)) {
            return;
        }

        if (is_file($canaryTarget) && filemtime($canarySource) <= filemtime($canaryTarget)) {
            return;
        }

        foreach (['css', 'js'] as $dir) {
            self::copyDirectory($sourceRoot.'/'.$dir, $publicPath.'/'.$dir);
        }

        foreach (['favicon.svg', 'favicon.png', 'robots.txt'] as $file) {
            self::copyFile($sourceRoot.'/'.$file, $publicPath.'/'.$file);
        }
    }

    protected static function copyDirectory(string $sourceDir, string $targetDir): void
    {
        if (! is_dir($sourceDir)) {
            return;
        }

        if (! is_dir($targetDir) && ! mkdir($targetDir, 0755, true)) {
            return;
        }

        foreach (glob($sourceDir.'/*') ?: [] as $file) {
            if (is_file($file)) {
                self::copyFile($file, $targetDir.'/'.basename($file));
            }
        }
    }

    protected static function copyFile(string $source, string $target): void
    {
        if (! is_file($source)) {
            return;
        }

        $targetDir = dirname($target);

        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        if (! is_file($target) || filemtime($source) > filemtime($target)) {
            @copy($source, $target);
        }
    }
}
