<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Short-lived file/database cache for data rebuilt on every HTML request
 * (nav, mega menu, excluded categories, brand filter lists). No Redis.
 */
class StorefrontCache
{
    public const NAV = 'storefront.nav.v1';

    public const EXCLUDED_CATEGORIES = 'storefront.excluded_category_ids.v1';

    public const SHOP_CATEGORIES = 'storefront.shop_categories.v1';

    public const PARENT_ROWS = 'storefront.category_parent_rows';

    public static function ttl(): int
    {
        return 900;
    }

    public static function remember(string $key, callable $callback, ?int $seconds = null): mixed
    {
        try {
            return Cache::remember($key, $seconds ?? self::ttl(), $callback);
        } catch (\Throwable) {
            return $callback();
        }
    }

    public static function shopBrandsKey(): string
    {
        return 'storefront.shop_brands.'.self::brandVersion();
    }

    public static function categoryBrandsKey(int $categoryId): string
    {
        return 'storefront.category_brands.'.$categoryId.'.'.self::brandVersion();
    }

    public static function brandVersion(): int
    {
        try {
            return (int) Cache::get('storefront.brand_version', 1);
        } catch (\Throwable) {
            return 1;
        }
    }

    public static function bumpBrandLists(): void
    {
        try {
            Cache::forever('storefront.brand_version', time());
        } catch (\Throwable) {
            // A stale brand list expires with the previous key's TTL.
        }
    }

    public static function forgetNavigation(): void
    {
        try {
            Cache::forget(self::NAV);
            Cache::forget(self::EXCLUDED_CATEGORIES);
            Cache::forget(self::SHOP_CATEGORIES);
        } catch (\Throwable) {
            // Navigation falls back to a live query.
        }

        app()->instance(self::PARENT_ROWS, null);
    }
}
