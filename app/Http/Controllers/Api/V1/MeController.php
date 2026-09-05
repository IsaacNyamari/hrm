<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $token = $user->currentAccessToken();

        return response()->json([
            'data' => [
                'user' => [
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'organization' => [
                    'uuid' => $user->tenant?->uuid,
                    'name' => $user->tenant?->name,
                ],
                'token' => [
                    'name' => $token->name,
                    'abilities' => $token->abilities,
                ],
            ],
        ]);
    }
}
