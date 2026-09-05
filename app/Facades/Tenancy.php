<?php

declare(strict_types=1);

namespace App\Facades;

use App\Support\TenantContext;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void set(\App\Models\Tenant $tenant)
 * @method static void forget()
 * @method static bool has()
 * @method static bool resolved()
 * @method static void markResolved()
 * @method static \App\Models\Tenant|null get()
 * @method static \App\Models\Tenant getOrFail()
 * @method static int|null id()
 * @method static mixed run(\App\Models\Tenant $tenant, callable $callback)
 * @method static mixed runWithout(callable $callback)
 *
 * @see TenantContext
 */
class Tenancy extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TenantContext::class;
    }
}
