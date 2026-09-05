<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Soft tenant binding for the whole `web` group: if a tenant context is not
 * already bound and the authenticated user belongs to a tenant, bind it. Unlike
 * {@see ResolveTenant} this never aborts — it just makes the current tenant
 * available to requests that are not inside the strict `tenant` route group,
 * most importantly Livewire component-update requests (which post to Livewire's
 * own route, bypassing the tenant group).
 *
 * The strict `tenant` + `tenant.user` middleware still runs on tenant routes and
 * remains the security boundary; this only fills the gap for in-app AJAX.
 */
class BindTenantContext
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->context->has()) {
            $tenant = $request->user()?->tenant()->first();

            if ($tenant !== null) {
                $this->context->set($tenant);
            }
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->context->forget();
    }
}
