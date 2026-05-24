<?php

if (! function_exists('storage_public_url')) {
    /**
     * Public URL for files in storage/app/public.
     * Uses the Laravel storage route so uploads work on cPanel
     * even when public_html/storage symlink is missing.
     */
    function storage_public_url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return route('storage.serve', ['path' => ltrim($path, '/')]);
    }
}

if (! function_exists('product_image_url')) {
    /** Display URL for product images — always returns a valid image (placeholder fallback). */
    function product_image_url(?string $storagePath = null): string
    {
        if ($storagePath) {
            return storage_public_url($storagePath) ?? asset('images/product-placeholder.svg');
        }

        return asset('images/product-placeholder.svg');
    }
}
