<?php

declare(strict_types=1);

namespace App\Support;

use App\Facades\Tenancy;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use RuntimeException;

/**
 * Holds the tenant bound to the current request, queued job or console command.
 *
 * Registered as a singleton and reached through the {@see Tenancy}
 * facade. When no tenant is bound the {@see TenantScope}
 * global scope becomes a no-op — that is the intended "platform / cross-tenant"
 * mode used by platform-admin screens, the scheduler and batch jobs.
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    private bool $resolved = false;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->resolved = true;
    }

    public function forget(): void
    {
        $this->tenant = null;
        $this->resolved = false;
    }

    public function has(): bool
    {
        return $this->tenant instanceof Tenant;
    }

    /**
     * True once resolution has been attempted for this lifecycle, regardless of
     * whether a tenant was found. Lets middleware distinguish "no tenant here"
     * from "not looked yet".
     */
    public function resolved(): bool
    {
        return $this->resolved;
    }

    public function markResolved(): void
    {
        $this->resolved = true;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function getOrFail(): Tenant
    {
        if (! $this->tenant instanceof Tenant) {
            throw new RuntimeException('No tenant is bound to the current context.');
        }

        return $this->tenant;
    }

    public function id(): ?int
    {
        $key = $this->tenant?->getKey();

        return $key === null ? null : (int) $key;
    }

    /**
     * Bind a tenant for the duration of the callback, then restore the previous
     * context (whatever it was, including "none").
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function run(Tenant $tenant, callable $callback)
    {
        [$previous, $previousResolved] = [$this->tenant, $this->resolved];

        $this->set($tenant);

        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
            $this->resolved = $previousResolved;
        }
    }

    /**
     * Clear the tenant for the duration of the callback (platform / cross-tenant
     * work), then restore the previous context.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function runWithout(callable $callback)
    {
        [$previous, $previousResolved] = [$this->tenant, $this->resolved];

        $this->forget();

        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
            $this->resolved = $previousResolved;
        }
    }
}
