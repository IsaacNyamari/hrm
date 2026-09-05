<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Auditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Platform operators may impersonate a tenant user to troubleshoot. Every
 * start/stop is written to the audit trail, and the impersonated session is
 * flagged so downstream audit entries record `via_impersonation`.
 */
class ImpersonationController extends Controller
{
    public function start(Request $request, User $user, Auditor $auditor): RedirectResponse
    {
        $operator = $request->user();

        abort_unless($operator !== null && $operator->isPlatformAdmin(), 403);
        abort_if($user->isPlatformAdmin(), 422, 'You cannot impersonate another platform operator.');
        abort_if($user->getKey() === $operator->getKey(), 422, 'You are already yourself.');
        abort_if($request->session()->has('impersonator_id'), 409, 'Stop the current impersonation first.');

        $auditor->log('impersonation.started', $user, [
            'operator' => $operator->email,
            'target' => $user->email,
            'tenant_id' => $user->tenant_id,
        ]);

        $request->session()->put('impersonator_id', $operator->getKey());
        Auth::login($user);

        return redirect()->route('dashboard');
    }

    public function stop(Request $request, Auditor $auditor): RedirectResponse
    {
        $originalId = $request->session()->pull('impersonator_id');

        if ($originalId === null) {
            return redirect()->route('dashboard');
        }

        $impersonated = $request->user();
        $operator = User::query()->find((int) $originalId);

        if ($operator !== null) {
            Auth::login($operator);
        }

        $auditor->log('impersonation.stopped', $impersonated, [
            'operator_id' => $originalId,
        ]);

        return redirect()->route('dashboard');
    }
}
