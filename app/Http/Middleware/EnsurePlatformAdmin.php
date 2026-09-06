<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the platform (SaaS operator) area. The route group sits outside the
 * tenant `ResolveTenant` middleware, so no tenant is bound and queries run
 * cross-tenant. Only users flagged `is_platform_admin` who also hold a platform
 * role may enter.
 */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user !== null && $user->isPlatformAdmin() && $user->hasPlatformRole(), 403);

        return $next($request);
    }
}
