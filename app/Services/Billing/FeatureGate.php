<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

/**
 * Single entry point for "what does this tenant's plan allow". Never scatter
 * `if ($plan->slug === 'business')` through the app — ask this service.
 */
class FeatureGate
{
    private const TTL_SECONDS = 300;

    /**
     * The plan's raw attributes are cached rather than the model itself: with
     * `cache.serializable_classes` disabled, a cached model would come back as
     * an incomplete object.
     */
    public function planFor(Tenant $tenant): ?Plan
    {
        /** @var array<string, mixed>|null $attributes */
        $attributes = Cache::remember(
            $this->cacheKey($tenant),
            self::TTL_SECONDS,
            fn (): ?array => $tenant->subscription()->with('plan')->first()?->plan?->getAttributes(),
        );

        return $attributes === null ? null : (new Plan)->newFromBuilder($attributes);
    }

    public function allows(Tenant $tenant, string $feature): bool
    {
        return $this->planFor($tenant)?->hasFeature($feature) === true;
    }

    public function limit(Tenant $tenant, string $key): ?int
    {
        return $this->planFor($tenant)?->limit($key);
    }

    public function withinLimit(Tenant $tenant, string $key, int $current): bool
    {
        $limit = $this->limit($tenant, $key);

        return $limit === null || $current < $limit;
    }

    public function remaining(Tenant $tenant, string $key, int $current): ?int
    {
        $limit = $this->limit($tenant, $key);

        return $limit === null ? null : max(0, $limit - $current);
    }

    public function flush(Tenant $tenant): void
    {
        Cache::forget($this->cacheKey($tenant));
    }

    private function cacheKey(Tenant $tenant): string
    {
        return "tenant:{$tenant->getKey()}:plan";
    }
}
