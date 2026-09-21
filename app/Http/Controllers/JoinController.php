<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Tenancy\RegisterOrganization;
use App\Http\Requests\RegisterOrganizationRequest;
use App\Models\RegistrationInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Invite-only registration. Public sign-up is closed; a platform admin sends
 * an access link. The link is consumed the first time it is opened: the
 * invitation is bound to that browser session, which then has
 * RegistrationInvitation::SESSION_MINUTES to create the organization.
 */
class JoinController extends Controller
{
    private const SESSION_KEY = 'registration_invitation_id';

    /**
     * Landing page for the emailed link. Deliberately does NOT consume the
     * link: mail security scanners pre-fetch links with GET, which would burn
     * it before the recipient ever clicks. The link is consumed by claim().
     */
    public function open(string $token): View
    {
        $invitation = RegistrationInvitation::findByToken($token);

        if ($invitation === null || ! $invitation->canBeOpened()) {
            return view('join.expired');
        }

        return view('join.open', ['invitation' => $invitation, 'token' => $token]);
    }

    /**
     * The recipient opens the link: it is consumed now and bound to this session.
     */
    public function claim(Request $request, string $token): RedirectResponse|View
    {
        $invitation = RegistrationInvitation::findByToken($token);

        if ($invitation === null || ! $invitation->canBeOpened()) {
            return view('join.expired');
        }

        // Atomic: of two simultaneous clicks only one can claim the link.
        $claimed = RegistrationInvitation::query()
            ->whereKey($invitation->getKey())
            ->whereNull('opened_at')
            ->update(['opened_at' => now()]);

        if ($claimed === 0) {
            return view('join.expired');
        }

        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, $invitation->getKey());

        return to_route('join.create');
    }

    public function create(Request $request): View
    {
        $invitation = $this->sessionInvitation($request);

        if ($invitation === null) {
            return view('join.expired');
        }

        return view('join.create', ['invitation' => $invitation]);
    }

    public function store(RegisterOrganizationRequest $request, RegisterOrganization $register): RedirectResponse|View
    {
        $invitation = $this->sessionInvitation($request);

        if ($invitation === null) {
            return view('join.expired');
        }

        if (User::query()->where('email', $invitation->email)->exists()) {
            return back()->withInput()->withErrors([
                'organization' => __('An account already exists for :email. Please log in instead.', ['email' => $invitation->email]),
            ]);
        }

        $owner = $register->handle(
            $invitation,
            $request->string('organization')->trim()->toString(),
            $request->string('name')->trim()->toString(),
            $request->string('password')->toString(),
        );

        $request->session()->forget(self::SESSION_KEY);
        Auth::login($owner);
        $request->session()->regenerate();

        return to_route('dashboard')->with('status', __('Welcome! Your workspace is ready.'));
    }

    private function sessionInvitation(Request $request): ?RegistrationInvitation
    {
        $id = $request->session()->get(self::SESSION_KEY);
        $invitation = $id !== null ? RegistrationInvitation::query()->find($id) : null;

        if ($invitation === null || ! $invitation->canCompleteRegistration()) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        return $invitation;
    }
}
