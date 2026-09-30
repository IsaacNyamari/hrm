<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps privileged areas closed until the signed-in user has confirmed
 * two-factor authentication.
 */
class EnsureTwoFactorEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! config('security.require_two_factor_platform') || $user === null || $user->two_factor_confirmed_at !== null) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(Response::HTTP_FORBIDDEN, 'Two-factor authentication must be enabled for this area.');
        }

        return redirect()->route('security.edit')->with('status', __('Enable two-factor authentication to access the platform area.'));
    }
}
