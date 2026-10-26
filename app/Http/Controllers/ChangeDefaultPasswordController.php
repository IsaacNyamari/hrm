<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Forced password change after signing in with the default password (email).
 */
class ChangeDefaultPasswordController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return redirect()->route('portal');
        }

        return view('welcome-new-hire.show', [
            'user' => $request->user(),
            'action' => route('password.change.update'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $email = $request->user()->email;

        $request->validate([
            'password' => [
                'required', 'string', Password::default(), 'confirmed',
                function (string $attribute, mixed $value, Closure $fail) use ($email): void {
                    if (mb_strtolower(trim((string) $value)) === mb_strtolower($email)) {
                        $fail(__('Choose a password other than your email address.'));
                    }
                },
            ],
        ]);

        $request->user()->forceFill(['password' => (string) $request->string('password')])->save();

        return redirect()->route('portal')->with('status', __('Password changed. Welcome aboard!'));
    }
}
