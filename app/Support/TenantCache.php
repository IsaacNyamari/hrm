<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Cache helper whose keys are always namespaced by the bound tenant, so one
 * tenant can never read (or bust) another tenant's cached value. Falls back to
 * a `platform` namespace when no tenant is bound.
 */
class TenantCache
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function remember(string $key, int $ttlSeconds, Closure $callback): mixed
    {
        return Cache::remember(self::key($key), $ttlSeconds, $callback);
    }

    public static function forget(string $key): void
    {
        Cache::forget(self::key($key));
    }

    /**
     * Bump this to invalidate every value under a logical group for the current
     * tenant (keys built with {@see versionedKey}).
     */
    public static function bumpVersion(string $group): void
    {
        Cache::forever(self::key("version:{$group}"), (string) now()->getTimestamp());
    }

    public static function versionedKey(string $group, string $suffix): string
    {
        $version = Cache::get(self::key("version:{$group}"), '0');

        return "{$group}:v{$version}:{$suffix}";
    }

    private static function key(string $key): string
    {
        $tenantId = app(TenantContext::class)->id();

        return 'tenant:'.($tenantId ?? 'platform').':'.$key;
    }
}
