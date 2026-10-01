<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Mobile\NotificationResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The token owner's in-app notification inbox (the same one as the web bell).
 */
class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $this->user($request);

        $notifications = $user->notifications()
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->paginate(min($request->integer('per_page', 20), 50));

        return NotificationResource::collection($notifications)
            ->additional(['meta' => ['unread' => $user->unreadNotifications()->count()]]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['data' => ['unread' => $this->user($request)->unreadNotifications()->count()]]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $user = $this->user($request);

        $user->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return response()->json(['data' => ['unread' => $user->unreadNotifications()->count()]]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['data' => ['unread' => 0]]);
    }

    public function destroy(Request $request, string $notification): JsonResponse
    {
        $user = $this->user($request);

        $user->notifications()->whereKey($notification)->delete();

        return response()->json(['data' => ['unread' => $user->unreadNotifications()->count()]]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_if($user === null, 401);

        return $user;
    }
}
