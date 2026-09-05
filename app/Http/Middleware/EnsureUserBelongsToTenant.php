<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects an authenticated user whose tenant is not the one resolved for the
 * request. Platform admins pass through (impersonation is handled separately in
 * P7). Must run after `auth` and `tenant`.
 */
class EnsureUserBelongsToTenant
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenant = $this->context->get();

        if ($user === null || $tenant === null) {
            return $next($request);
        }

        if ($user->isPlatformAdmin()) {
            return $next($request);
        }

        abort_unless($user->belongsToTenant($tenant), 403, 'You do not have access to this organization.');

        return $next($request);
    }
}
