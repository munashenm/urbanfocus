<?php

use Illuminate\Support\Str;

if (! function_exists('public_asset_version')) {
    /** Cache-busting version for a file in Laravel's public directory. */
    function public_asset_version(string $path): ?int
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        foreach ([public_path($path), base_path('public/'.$path)] as $file) {
            if (is_file($file)) {
                return filemtime($file) ?: null;
            }
        }

        return null;
    }
}

if (! function_exists('public_asset_url')) {
    /**
     * URL for static public assets (css, js, images).
     * Works on cPanel when files live in Laravel's public/ but the web root is public_html.
     */
    function public_asset_url(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $url = asset($path);
        $version = public_asset_version($path);

        return $version ? $url.'?v='.$version : $url;
    }
}

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
            return Str::limit($base, $max, '');
        }

        $name = $context['name'] ?? '';
        $brand = $context['brand'] ?? null;
        $category = $context['category'] ?? null;
        $type = $context['type'] ?? null;

        $suffixes = match ($type) {
            'product' => array_filter([
                $brand ? "Genuine {$brand} supply from Urban Focus with VAT invoices, warranty support and nationwide delivery across South Africa." : null,
                'Buy online from Urban Focus with secure Paystack checkout, VAT invoices and fast courier delivery to Johannesburg, Cape Town, Durban and nationwide.',
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
                return Str::limit($combined, $max, '');
            }
        }

        $fallback = $prefix.($suffixes[0] ?? 'Shop IT products online at Urban Focus with nationwide delivery across South Africa.');

        return Str::limit($fallback, $max, '');
    }
}

if (! function_exists('clean_html')) {
    /**
     * Sanitise rich HTML before rendering it unescaped in Blade.
     *
     * Product descriptions and blog content can originate from CSV/feed imports
     * and content sync jobs, so they are treated as untrusted. This strips
     * scripts, iframes, inline event handlers and javascript: URLs while keeping
     * a safe allowlist of formatting tags.
     */
    function clean_html(?string $html): string
    {
        if ($html === null) {
            return '';
        }

        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $allowedTags = [
            'p', 'br', 'b', 'strong', 'i', 'em', 'u', 's', 'ul', 'ol', 'li', 'a',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'span', 'div',
            'table', 'thead', 'tbody', 'tr', 'td', 'th', 'caption', 'img', 'figure',
            'figcaption', 'hr', 'pre', 'code', 'sub', 'sup', 'small',
        ];
        $allowedAttrs = ['href', 'title', 'alt', 'src', 'width', 'height', 'colspan', 'rowspan', 'target', 'rel', 'id', 'class'];

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML(
            '<?xml encoding="UTF-8"?><div id="uf-clean-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($dom);
        $root = $xpath->query('//*[@id="uf-clean-root"]')->item(0);

        if (! $root instanceof DOMElement) {
            return '';
        }

        foreach (iterator_to_array($xpath->query('//*')) as $node) {
            if (! $node instanceof DOMElement || $node === $root) {
                continue;
            }

            if (! in_array(strtolower($node->nodeName), $allowedTags, true)) {
                $node->parentNode?->removeChild($node);

                continue;
            }

            if (! $node->hasAttributes()) {
                continue;
            }

            foreach (iterator_to_array($node->attributes) as $attr) {
                $name = strtolower($attr->nodeName);

                if (str_starts_with($name, 'on') || ! in_array($name, $allowedAttrs, true)) {
                    $node->removeAttribute($attr->nodeName);

                    continue;
                }

                if (in_array($name, ['href', 'src'], true)) {
                    $value = strtolower(preg_replace('/\s+/', '', (string) $attr->nodeValue) ?? '');

                    $unsafe = str_starts_with($value, 'javascript:')
                        || str_starts_with($value, 'vbscript:')
                        || (str_starts_with($value, 'data:') && ! str_starts_with($value, 'data:image/'));

                    if ($unsafe) {
                        $node->removeAttribute($attr->nodeName);
                    }
                }
            }

            if (strtolower($node->nodeName) === 'a' && strtolower((string) $node->getAttribute('target')) === '_blank') {
                $node->setAttribute('rel', 'noopener noreferrer');
            }
        }

        $out = '';

        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }

        return $out;
    }
}
