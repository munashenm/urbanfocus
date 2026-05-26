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

        return url('/storage/'.ltrim($path, '/'));
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

if (! function_exists('seo_meta_description')) {
    /**
     * Build a meta description within SEO length targets (120–160 chars by default).
     *
     * @param  array{type?: string, name?: string, brand?: string|null, category?: string|null}  $context
     */
    function seo_meta_description(string $base, array $context = []): string
    {
        $base = trim(preg_replace('/\s+/u', ' ', strip_tags($base)) ?? '');
        $min = (int) config('seo.defaults.min_description_length', 120);
        $max = (int) config('seo.defaults.max_description_length', 160);

        if ($base !== '' && mb_strlen($base) >= $min) {
            return \Illuminate\Support\Str::limit($base, $max, '');
        }

        $name = $context['name'] ?? '';
        $brand = $context['brand'] ?? null;
        $category = $context['category'] ?? null;
        $type = $context['type'] ?? null;

        $suffixes = match ($type) {
            'product' => array_filter([
                $brand ? "Genuine {$brand} supply from Urban Focus with VAT invoices, warranty support and nationwide delivery across South Africa." : null,
                'Buy online from Urban Focus with secure PayFast checkout, VAT invoices and fast courier delivery to Johannesburg, Cape Town, Durban and nationwide.',
                'Order from Urban Focus — trusted South African IT distributor with professional support and B2B quote options.',
            ]),
            'category' => array_filter([
                $name ? "Shop {$name} at Urban Focus with competitive pricing, VAT invoices and expert IT support across South Africa." : null,
                'Browse IT products at Urban Focus with secure checkout, nationwide delivery and authorised brand supply.',
            ]),
            'brand' => array_filter([
                $name ? "Authorised {$name} distributor in South Africa. Shop genuine products at Urban Focus with VAT invoices and nationwide delivery." : null,
                'Urban Focus supplies leading IT brands with secure checkout, professional support and delivery across South Africa.',
            ]),
            'article' => [
                'Read IT news and buying guides from Urban Focus — South African supplier of networking, laptops, security and software.',
            ],
            default => [
                'Urban Focus supplies IT hardware and software across South Africa with VAT invoices, secure checkout and nationwide delivery.',
            ],
        };

        $prefix = $base !== '' ? rtrim($base, '.').'. ' : '';

        foreach ($suffixes as $suffix) {
            $combined = $prefix.$suffix;
            if (mb_strlen($combined) >= $min) {
                return \Illuminate\Support\Str::limit($combined, $max, '');
            }
        }

        $fallback = $prefix.($suffixes[0] ?? 'Shop IT products online at Urban Focus with nationwide delivery across South Africa.');

        return \Illuminate\Support\Str::limit($fallback, $max, '');
    }
}
