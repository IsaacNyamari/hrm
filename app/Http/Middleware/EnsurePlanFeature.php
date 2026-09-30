<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Billing\FeatureGate;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces plan entitlements on the server. The plans page promises features
 * per tier, so the matching routes must refuse a tenant whose plan lacks the
 * flag rather than merely hiding links. Tenants with no plan on record are left
 * to the subscription middleware. Runs after `tenant`.
 */
class EnsurePlanFeature
{
    public function __construct(private readonly TenantContext $context, private readonly FeatureGate $features) {}

    public function handle(Request $request, Closure $next, ?string $feature = null): Response
    {
        $tenant = $this->context->get();
        $flag = $feature ?? $this->requiredFlag($request);

        if ($tenant === null || $flag === null || $this->features->planFor($tenant) === null || $this->features->allows($tenant, $flag)) {
            return $next($request);
        }

        $message = "Your current plan does not include {$flag}. Upgrade your plan to use it.";

        if ($request->expectsJson()) {
            abort(Response::HTTP_PAYMENT_REQUIRED, $message);
        }

        return redirect()->route('billing.index')->with('error', $message);
    }

    private function requiredFlag(Request $request): ?string
    {
        $path = trim($request->path(), '/');
        $path = preg_replace('#^api/v1/#', '', $path) ?? $path;

        foreach ((array) config('billing.route_features', []) as $pattern => $flag) {
            if (fnmatch($pattern, $path)) {
                return $flag;
            }
        }

        return null;
    }
}
