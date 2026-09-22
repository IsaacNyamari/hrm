<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Registers the phone's Expo push token for the signed-in user. A push token
 * identifies one device, so registering it again (after a different person
 * signs in on that phone) moves it to the new user.
 */
class DeviceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $data = $request->validate([
            'expo_push_token' => ['required', 'string', 'max:255', 'regex:/^Expo(nent)?PushToken\[[^\]]+\]$/'],
            'platform' => ['required', Rule::in(DeviceToken::PLATFORMS)],
        ]);

        $accessToken = $user->currentAccessToken();

        $device = DeviceToken::withoutTenantScope()->updateOrCreate(
            ['expo_push_token' => $data['expo_push_token']],
            [
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->getKey(),
                'personal_access_token_id' => $accessToken instanceof PersonalAccessToken ? $accessToken->getKey() : null,
                'platform' => $data['platform'],
                'last_seen_at' => CarbonImmutable::now(),
            ],
        );

        return response()->json(['data' => [
            'expo_push_token' => $device->expo_push_token,
            'platform' => $device->platform,
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
        ]], $device->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, string $token): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        DeviceToken::query()
            ->where('user_id', $user->getKey())
            ->where('expo_push_token', $token)
            ->delete();

        return response()->noContent();
    }
}
