<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenController extends Controller
{
    /**
     * The abilities a token may be granted. Read-only by default.
     */
    public const ABILITIES = [
        'employees:read',
        'departments:read',
        'leave:read',
        'leave:write',
        'payroll:read',
    ];

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        return response()->json([
            'data' => $user->tokens()->latest('id')->get()->map(fn (PersonalAccessToken $token): array => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
            ]),
            'meta' => ['available_abilities' => self::ABILITIES],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'abilities' => ['sometimes', 'array'],
            'abilities.*' => [Rule::in(self::ABILITIES)],
        ]);

        $abilities = $data['abilities'] ?? ['employees:read'];

        $token = $user->createToken($data['name'], $abilities);

        return response()->json([
            'data' => [
                'id' => $token->accessToken->id,
                'name' => $token->accessToken->name,
                'abilities' => $token->accessToken->abilities,
                'plain_text_token' => $token->plainTextToken,
            ],
        ], 201);
    }

    public function destroy(Request $request, int $token): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $user->tokens()->whereKey($token)->delete();

        return response()->noContent();
    }
}
