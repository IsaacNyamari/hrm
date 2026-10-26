<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff who signed in with their default password (their email) must choose
 * a new one before they can use anything else.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->must_change_password || $request->routeIs('password.change', 'password.change.update', 'logout')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, __('You must change your password first.'));
        }

        return redirect()->route('password.change');
    }
}
