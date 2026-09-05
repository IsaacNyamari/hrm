<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Tenancy\AcceptInvitation;
use App\Http\Requests\AcceptInvitationRequest;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function show(Invitation $invitation): View
    {
        return view($invitation->isPending() ? 'invitations.show' : 'invitations.expired', [
            'invitation' => $invitation,
        ]);
    }

    public function store(AcceptInvitationRequest $request, Invitation $invitation, AcceptInvitation $action): RedirectResponse
    {
        $user = $action->handle(
            $invitation,
            $request->string('name')->toString(),
            $request->string('password')->toString(),
        );

        Auth::login($user);

        return redirect()->intended(route('dashboard'));
    }
}
