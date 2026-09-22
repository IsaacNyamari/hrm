<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Mobile\AnnouncementResource;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Announcements visible to the token owner (their organisation, department or
 * branch), newest first, with their read state.
 */
class AnnouncementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Announcement::class);
        $user = $request->user();

        $announcements = Announcement::query()
            ->visibleTo($user?->employee)
            ->latest('published_at')
            ->limit(50)
            ->get();

        /** @var array<int, true> $readIds */
        $readIds = AnnouncementRead::query()
            ->where('user_id', $user?->getKey())
            ->pluck('announcement_id')
            ->mapWithKeys(fn ($id): array => [(int) $id => true])
            ->all();

        return response()->json([
            'data' => $announcements->map(fn (Announcement $a): array => (new AnnouncementResource($a, $readIds))->resolve($request))->all(),
        ]);
    }

    public function markRead(Request $request, string $announcement): JsonResponse
    {
        $record = Announcement::query()->visibleTo($request->user()?->employee)->where('uuid', $announcement)->firstOrFail();
        $this->authorize('view', $record);

        AnnouncementRead::query()->firstOrCreate(
            ['announcement_id' => $record->getKey(), 'user_id' => $request->user()?->getKey()],
            ['tenant_id' => $record->tenant_id, 'read_at' => CarbonImmutable::now()],
        );

        return response()->json(['data' => ['read' => true]]);
    }
}
