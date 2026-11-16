<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Cache helper that survives corrupt database values.
 *
 * The live cache table can truncate large serialized payloads (a TEXT
 * column is only 64KB; even MEDIUMTEXT still competes for DB connections).
 * unserialize() then fails, Laravel throws, and every request reruns the
 * expensive callback while the bad row stays in place.
 *
 * Large catalog payloads use the file store in production, which needs no
 * .env change. Tests use the array store so nothing leaks between cases.
 */
class SafeCache
{
    public static function remember(string $key, int $seconds, callable $callback, ?string $store = null): mixed
    {
        $cache = self::repository($store);

        try {
            $value = $cache->get($key);
            if ($value !== null) {
                return $value;
            }
        } catch (Throwable) {
            self::forgetFrom($cache, $key);
        }

        $value = $callback();

        try {
            $cache->put($key, $value, $seconds);
        } catch (Throwable) {
            // Still return the computed value if the store is unwritable.
        }

        return $value;
    }

    public static function rememberForever(string $key, callable $callback, ?string $store = null): mixed
    {
        $cache = self::repository($store);

        try {
            $value = $cache->get($key);
            if ($value !== null) {
                return $value;
            }
        } catch (Throwable) {
            self::forgetFrom($cache, $key);
        }

        $value = $callback();

        try {
            $cache->forever($key, $value);
        } catch (Throwable) {
            // Still return the computed value if the store is unwritable.
        }

        return $value;
    }

    public static function forget(string $key): void
    {
        foreach (array_unique([self::preferredStore(), 'file', (string) config('cache.default', 'file')]) as $store) {
            try {
                self::repository($store)->forget($key);
            } catch (Throwable) {
                // Ignore a missing or unwritable store.
            }
        }
    }

    /**
     * File store in production so large blobs are not written to the
     * database cache table. Array store in tests so state does not leak.
     */
    public static function preferredStore(): string
    {
        $default = (string) config('cache.default', 'file');

        if ($default === 'array' || app()->runningUnitTests()) {
            return 'array';
        }

        return 'file';
    }

    protected static function repository(?string $store = null): \Illuminate\Contracts\Cache\Repository
    {
        $store = $store ?? self::preferredStore();

        if (self::preferredStore() === 'array' && $store === 'file') {
            $store = 'array';
        }

        try {
            return Cache::store($store);
        } catch (Throwable) {
            return Cache::store();
        }
    }

    protected static function forgetFrom(\Illuminate\Contracts\Cache\Repository $cache, string $key): void
    {
        try {
            $cache->forget($key);
        } catch (Throwable) {
            // The next put() overwrites the key when the store allows it.
        }
    }
}
