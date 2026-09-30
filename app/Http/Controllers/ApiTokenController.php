<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\EnforceTokenIpAllowlist;
use Closure;
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
        'tasks:read',
        'tasks:write',
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
                'expires_at' => $token->expires_at?->toIso8601String(),
                'allowed_ips' => EnforceTokenIpAllowlist::allowedFor($token),
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
            'allowed_ips' => ['sometimes', 'array', 'max:20'],
            'allowed_ips.*' => ['string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! filter_var(explode('/', (string) $value)[0], FILTER_VALIDATE_IP)) {
                    $fail('Each allowed address must be an IP address or CIDR range.');
                }
            }],
            'expires_in_days' => ['sometimes', 'integer', 'min:1', 'max:'.(int) config('security.api_token_max_days')],
        ]);

        $abilities = $data['abilities'] ?? ['employees:read'];

        $expiresAt = now()->addDays((int) ($data['expires_in_days'] ?? config('security.api_token_default_days')));

        $token = $user->createToken($data['name'], $abilities, $expiresAt);
        $allowedIps = array_values(array_unique($data['allowed_ips'] ?? []));
        $token->accessToken->forceFill(['allowed_ips' => $allowedIps === [] ? null : json_encode($allowedIps)])->save();

        return response()->json([
            'data' => [
                'id' => $token->accessToken->id,
                'name' => $token->accessToken->name,
                'abilities' => $token->accessToken->abilities,
                'expires_at' => $expiresAt->toIso8601String(),
                'allowed_ips' => $allowedIps,
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
