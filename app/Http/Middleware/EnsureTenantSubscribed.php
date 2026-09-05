<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks the tenant application when the subscription has lapsed, while always
 * leaving the billing / subscription / settings / support routes reachable so
 * the tenant can recover. Runs after `tenant`.
 */
class EnsureTenantSubscribed
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->context->get();

        if ($tenant === null || $tenant->hasAppAccess()) {
            return $next($request);
        }

        $routeName = (string) ($request->route()?->getName() ?? '');

        foreach ((array) config('billing.always_accessible_routes', []) as $pattern) {
            if (Str::is($pattern, $routeName)) {
                return $next($request);
            }
        }

        if ($request->expectsJson()) {
            abort(Response::HTTP_PAYMENT_REQUIRED, 'Your subscription is inactive. Update billing to continue.');
        }

        return redirect()->route('billing.index');
    }
}
