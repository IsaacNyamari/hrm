<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Actions\Mobile\IssueMobileToken;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Mobile\DeviceSessionResource;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The user's signed-in mobile devices (their `mobile` Sanctum tokens), so they
 * can sign other devices out from the app.
 */
class DeviceSessionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $sessions = $user->tokens()
            ->latest('last_used_at')
            ->latest('id')
            ->get()
            ->filter(fn (PersonalAccessToken $token): bool => in_array(IssueMobileToken::ABILITY, (array) $token->abilities, true))
            ->values();

        return DeviceSessionResource::collection($sessions);
    }

    public function destroy(Request $request, int $session): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $token = $user->tokens()->whereKey($session)->firstOrFail();
        abort_unless(in_array(IssueMobileToken::ABILITY, (array) $token->abilities, true), 404);

        DeviceToken::query()->where('personal_access_token_id', $token->getKey())->delete();
        $token->delete();

        return response()->noContent();
    }

    /**
     * Sign out every other mobile device, keeping the one making the request.
     */
    public function destroyOthers(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $current = $user->currentAccessToken();
        $currentId = $current instanceof PersonalAccessToken ? $current->getKey() : null;

        $others = $user->tokens()
            ->when($currentId !== null, fn ($q) => $q->whereKeyNot($currentId))
            ->get()
            ->filter(fn (PersonalAccessToken $token): bool => in_array(IssueMobileToken::ABILITY, (array) $token->abilities, true))
            ->modelKeys();

        DeviceToken::query()->whereIn('personal_access_token_id', $others)->delete();
        PersonalAccessToken::query()->whereKey($others)->delete();

        return response()->noContent();
    }
}
