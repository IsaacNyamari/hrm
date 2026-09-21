<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Employees\CreateStaffAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * First sign-in for a newly hired employee: the signed link from the welcome
 * email lets them choose a password, then drops them on their dashboard.
 * The link dies as soon as a password is set.
 */
class WelcomeNewHireController extends Controller
{
    public function show(Request $request, string $user): View
    {
        $account = $this->resolve($request, $user);

        if ($account === null) {
            return view('welcome-new-hire.expired');
        }

        return view('welcome-new-hire.show', ['user' => $account, 'action' => $request->fullUrl()]);
    }

    public function store(Request $request, string $user): RedirectResponse|View
    {
        $account = $this->resolve($request, $user);

        if ($account === null) {
            return view('welcome-new-hire.expired');
        }

        $request->validate([
            'password' => ['required', 'string', Password::default(), 'confirmed'],
        ]);

        $account->forceFill([
            'password' => Hash::make((string) $request->string('password')),
            'email_verified_at' => $account->email_verified_at ?? now(),
        ])->save();

        Auth::login($account);
        $request->session()->regenerate();

        return redirect()->route('portal')->with('status', __('Welcome aboard! Your account is ready.'));
    }

    private function resolve(Request $request, string $uuid): ?User
    {
        if (! $request->hasValidSignature()) {
            return null;
        }

        $account = User::query()->where('uuid', $uuid)->first();

        if ($account === null || ! hash_equals(CreateStaffAccount::fingerprint($account), (string) $request->query('fp'))) {
            return null;
        }

        return $account;
    }
}
