<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Tenancy\SendInvitation;
use App\Facades\Tenancy;
use App\Http\Requests\SendUserInvitationRequest;
use Illuminate\Http\JsonResponse;

class UserInvitationController extends Controller
{
    public function store(SendUserInvitationRequest $request, SendInvitation $action): JsonResponse
    {
        $invitation = $action->handle(
            Tenancy::getOrFail(),
            $request->string('email')->toString(),
            $request->role(),
            $request->user(),
        );

        return response()->json([
            'data' => [
                'uuid' => $invitation->uuid,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ],
        ], 201);
    }
}
