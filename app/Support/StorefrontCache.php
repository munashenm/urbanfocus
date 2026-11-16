<?php

namespace App\Support;

/**
 * Short-lived file cache for data rebuilt on every HTML request
 * (nav, mega menu, excluded categories, brand filter lists). No Redis.
 *
 * Values go to the file store so they cannot be truncated by the
 * database cache table, and corrupt keys are forgotten and recomputed.
 */
class StorefrontCache
{
    public const NAV = 'storefront.nav.v1';

    public const EXCLUDED_CATEGORIES = 'storefront.excluded_category_ids.v1';

    public const SHOP_CATEGORIES = 'storefront.shop_categories.v1';

    public const PARENT_ROWS = 'storefront.category_parent_rows';

    public const PRIORITY_CATEGORY_IDS = 'storefront.priority_category_ids.v1';

    public const BLACK_FRIDAY_ACTIVE = 'storefront.black_friday_active.v1';

    public static function ttl(): int
    {
        return 900;
    }

    public static function remember(string $key, callable $callback, ?int $seconds = null): mixed
    {
        return SafeCache::remember($key, $seconds ?? self::ttl(), $callback, 'file');
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
            return (int) SafeCache::rememberForever('storefront.brand_version', fn () => 1, 'file');
        } catch (\Throwable) {
            return 1;
        }
    }

    public static function bumpBrandLists(): void
    {
        try {
            $version = time();
            \Illuminate\Support\Facades\Cache::store('file')->forever('storefront.brand_version', $version);
        } catch (\Throwable) {
            // A stale brand list expires with the previous key's TTL.
        }
    }

    public static function forgetNavigation(): void
    {
        SafeCache::forget(self::NAV);
        SafeCache::forget(self::EXCLUDED_CATEGORIES);
        SafeCache::forget(self::SHOP_CATEGORIES);
        SafeCache::forget(self::PRIORITY_CATEGORY_IDS);
        SafeCache::forget(self::BLACK_FRIDAY_ACTIVE);

        try {
            app()->instance(self::PARENT_ROWS, null);
        } catch (\Throwable) {
            // The container may not be ready in some console contexts.
        }

        self::bumpBrandLists();
    }
}
