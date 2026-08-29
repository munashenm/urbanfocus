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

        if (! is_file($canaryTarget) || filemtime($canarySource) > filemtime($canaryTarget)) {
            self::syncAll($sourceRoot, $publicPath);

            return;
        }

        $imageCanary = '/images/target-range/tr-laptop-14.jpg';
        if (is_file($sourceRoot.$imageCanary) && ! is_file($publicPath.$imageCanary)) {
            self::copyDirectory($sourceRoot.'/images/target-range', $publicPath.'/images/target-range');
        }

        $specialistCanary = '/images/specialist/security-key.jpg';
        $specialistPhotoCanary = '/images/specialist/products/uf-nk-passkey.webp';
        if (
            (is_file($sourceRoot.$specialistCanary) && ! is_file($publicPath.$specialistCanary))
            || (is_file($sourceRoot.$specialistPhotoCanary) && ! is_file($publicPath.$specialistPhotoCanary))
        ) {
            self::copyDirectory($sourceRoot.'/images/specialist', $publicPath.'/images/specialist');
        }

        $jsCanary = '/js/checkout.js';
        if (is_file($sourceRoot.$jsCanary) && (! is_file($publicPath.$jsCanary) || filemtime($sourceRoot.$jsCanary) > filemtime($publicPath.$jsCanary))) {
            self::copyDirectory($sourceRoot.'/js', $publicPath.'/js');
        }
    }

    public static function syncAll(string $sourceRoot, string $publicPath): void
    {
        foreach (['css', 'js', 'images'] as $dir) {
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

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($sourceDir) + 1);
            $target = $targetDir.'/'.$relative;

            if ($item->isDir()) {
                if (! is_dir($target) && ! mkdir($target, 0755, true)) {
                    continue;
                }

                continue;
            }

            self::copyFile($item->getPathname(), $target);
        }
    }

    public static function ensureFile(string $relativePath): void
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return;
        }

        $source = base_path('public/'.$relativePath);
        $target = public_path($relativePath);

        if (! is_file($source)) {
            return;
        }

        $sourceRoot = realpath(base_path('public'));
        $targetRoot = realpath(public_path());

        if ($sourceRoot && $targetRoot && $sourceRoot === $targetRoot) {
            return;
        }

        self::copyFile($source, $target);
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
