<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * A token created with an IP allowlist only works from those addresses or
 * CIDR ranges, so a leaked token is useless elsewhere. Tokens without an
 * allowlist are unrestricted.
 */
class EnforceTokenIpAllowlist
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();
        $allowed = $token instanceof PersonalAccessToken ? self::allowedFor($token) : [];

        if ($allowed !== [] && ! IpUtils::checkIp((string) $request->ip(), $allowed)) {
            abort(Response::HTTP_FORBIDDEN, 'This API token cannot be used from your IP address.');
        }

        return $next($request);
    }

    /**
     * The token's stored allowlist (kept as a JSON column).
     *
     * @return list<string>
     */
    public static function allowedFor(PersonalAccessToken $token): array
    {
        $value = $token->getAttribute('allowed_ips');
        $value = is_string($value) ? json_decode($value, true) : $value;

        return is_array($value) ? array_values(array_map('strval', $value)) : [];
    }
}
