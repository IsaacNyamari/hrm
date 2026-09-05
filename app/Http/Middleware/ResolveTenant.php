<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant for the current request and binds it into the
 * {@see TenantContext}. Resolution order:
 *
 *   1. exact custom-domain host match
 *   2. subdomain label — when the host is `<label>.<central-domain>` the label
 *      MUST resolve to a tenant, otherwise 404 (no silent fallback)
 *   3. the authenticated user's own tenant (only when the host names no tenant)
 *
 * Aborts 404 when no tenant can be resolved, so tenant routes never run
 * unscoped. `terminate()` clears the context so a long-lived worker (Octane,
 * queue) cannot carry a tenant into the next request.
 */
class ResolveTenant
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host = mb_strtolower($request->getHost());

        $tenant = Tenant::query()->where('custom_domain', $host)->first();

        if (! $tenant instanceof Tenant && config('tenancy.subdomains_enabled') === true) {
            $label = $this->subdomainLabel($host);

            if ($label !== null) {
                $tenant = Tenant::query()->where('slug', $label)->first();

                abort_if(! $tenant instanceof Tenant, 404, 'Organization not found.');
            }
        }

        if (! $tenant instanceof Tenant) {
            $tenant = $this->resolveFromUser($request);
        }

        $this->context->markResolved();

        abort_if(! $tenant instanceof Tenant, 404, 'Organization not found.');

        $this->context->set($tenant);

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->context->forget();
    }

    /**
     * The left-most label when $host is a subdomain of a configured central
     * domain. Null when the host is exactly a central domain, or unrelated to
     * any central domain (dev hosts, etc.).
     */
    private function subdomainLabel(string $host): ?string
    {
        /** @var array<int, string> $centralDomains */
        $centralDomains = config('tenancy.central_domains', []);

        foreach ($centralDomains as $central) {
            $central = mb_strtolower($central);

            if ($host === $central) {
                return null;
            }

            if (str_ends_with($host, '.'.$central)) {
                $label = explode('.', mb_substr($host, 0, -mb_strlen('.'.$central)))[0];

                return $label !== '' ? $label : null;
            }
        }

        return null;
    }

    private function resolveFromUser(Request $request): ?Tenant
    {
        $user = $request->user();

        if ($user === null || $user->tenant_id === null) {
            return null;
        }

        return $user->tenant()->first();
    }
}
