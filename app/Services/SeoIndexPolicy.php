<?php

namespace App\Services;

use Illuminate\Http\Request;

/**
 * Decides which public URLs may be indexed. Filters, search, leftover WooCommerce
 * parameters and homepage/shop pagination are noindexed. Category, brand and
 * knowledge-centre pagination stay indexable for product/article discovery.
 */
class SeoIndexPolicy
{
    /** Query keys that never deserve their own index entry. */
    public const JUNK_PARAM_KEYS = [
        'filter_brand',
        'filter_cat',
        'filter_tag',
        'filter_stock',
        'min_price',
        'max_price',
        'price_min',
        'price_max',
        'q',
        'brand',
        'category',
        'deals',
        'sort',
        'orderby',
        'order',
        'sale_status',
        'stock_status',
        'include_out_of_stock',
        'in_stock',
    ];

    /** @var list<string> */
    public const JUNK_PARAM_PREFIXES = [
        'filter_',
        'etheme-',
        'etheme_',
    ];

    public function shouldNoindex(Request $request): bool
    {
        if ($this->isPrivatePath($request)) {
            return true;
        }

        // Parameter junk on a PDP must canonicalize, not noindex, so Google
        // can still consolidate to the clean product URL.
        if ($this->isProductPath($request)) {
            return false;
        }

        if ($this->hasNonIndexableQuery($request)) {
            return true;
        }

        return $this->isLowValuePagination($request);
    }

    public function robotsDirective(Request $request): ?string
    {
        if ($this->isPrivatePath($request)) {
            return 'noindex, nofollow';
        }

        if ($this->shouldNoindex($request)) {
            return 'noindex, follow, max-image-preview:large';
        }

        return null;
    }

    public function hasNonIndexableQuery(Request $request): bool
    {
        foreach ($request->query() as $key => $value) {
            $key = strtolower((string) $key);

            if ($key === 'page') {
                continue;
            }

            if (in_array($key, self::JUNK_PARAM_KEYS, true)) {
                if ($key === 'sort' && app(CatalogBrowseService::class)->isDefaultSort($request)) {
                    continue;
                }

                return true;
            }

            foreach (self::JUNK_PARAM_PREFIXES as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Homepage widget pagination and the mixed /shop catalogue must not be indexed
     * as independent pages. Category/brand/blog ?page= remains crawlable.
     */
    public function isLowValuePagination(Request $request): bool
    {
        $page = (int) $request->query('page', 1);
        if ($page <= 1) {
            return false;
        }

        $path = '/'.ltrim($request->path(), '/');
        if ($path === '//') {
            $path = '/';
        }

        return in_array($path, ['/', '/shop'], true);
    }

    /** @return list<string> */
    public function robotsDisallowPaths(): array
    {
        return [
            '/*?filter_brand=',
            '/*?*filter_brand=',
            '/*?filter_cat=',
            '/*?*filter_cat=',
            '/*?filter_',
            '/*?*filter_',
            '/*?min_price=',
            '/*?*min_price=',
            '/*?max_price=',
            '/*?*max_price=',
            '/*?brand=',
            '/*?*brand=',
            '/*?category=',
            '/*?*category=',
            '/*?etheme-product-grid',
            '/*?*etheme-product-grid',
            '/*?etheme-',
            '/*?*etheme-',
            '/shop?page=',
            '/shop?*page=',
            '/?page=',
            '/*?q=',
            '/*?*q=',
            '/*?sort=',
            '/*?*sort=',
            '/*?price_min=',
            '/*?*price_min=',
            '/*?price_max=',
            '/*?*price_max=',
        ];
    }

    /** @return list<string> */
    public function canonicalStripParams(): array
    {
        return array_values(array_unique(array_merge(
            config('seo.tracking_query_params', []),
            self::JUNK_PARAM_KEYS,
        )));
    }

    public function isPrivatePath(Request $request): bool
    {
        $path = $request->path();

        foreach (['admin', 'cart', 'checkout', 'account', 'login', 'register', 'password'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    public function isProductPath(Request $request): bool
    {
        $path = $request->path();

        return $path === 'product' || str_starts_with($path, 'product/');
    }
}
