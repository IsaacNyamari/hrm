<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the tenant for a token-authenticated API request from the token
 * owner's `tenant_id`. The client never supplies a tenant id.
 */
class ResolveApiTenant
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null || $user->tenant_id === null, 403, 'This token is not attached to an organization.');

        $tenant = $user->tenant()->first();

        abort_if($tenant === null, 403, 'Organization not found.');

        $this->context->set($tenant);
        $this->context->markResolved();

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->context->forget();
    }
}
