<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $query = $user->notifications();

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        $notifications = $query->limit($request->integer('limit', 30))->get();

        return response()->json([
            'data' => $notifications->map(fn (DatabaseNotification $n): array => [
                'id' => $n->id,
                'type' => is_array($n->data) ? ($n->data['type'] ?? class_basename($n->type)) : class_basename($n->type),
                'data' => $n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ]),
            'meta' => ['unread' => $user->unreadNotifications()->count()],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        return response()->json(['data' => ['unread' => $user->unreadNotifications()->count()]]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $record = $user->notifications()->whereKey($notification)->firstOrFail();
        $record->markAsRead();

        return response()->json(['data' => ['unread' => $user->unreadNotifications()->count()]]);
    }

    public function markAllRead(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }

    public function destroy(Request $request, string $notification): Response
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $user->notifications()->whereKey($notification)->delete();

        return response()->noContent();
    }
}
