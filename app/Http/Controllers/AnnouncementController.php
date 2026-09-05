<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\AnnouncementPublished;
use App\Facades\Tenancy;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class AnnouncementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Announcement::class);

        $user = $request->user();
        $isPublisher = $user?->can('employees.update') || $user?->can('settings.manage');

        if ($isPublisher && $request->boolean('manage')) {
            $announcements = Announcement::query()->latest('id')->get();
        } else {
            $announcements = Announcement::query()->visibleTo($user?->employee)->latest('published_at')->get();
        }

        $readIds = AnnouncementRead::query()->where('user_id', $user?->getKey())->pluck('announcement_id')->flip();

        return response()->json([
            'data' => $announcements->map(fn (Announcement $a): array => [
                'uuid' => $a->uuid,
                'title' => $a->title,
                'body' => $a->body,
                'audience' => $a->audience,
                'published_at' => $a->published_at?->toIso8601String(),
                'expires_at' => $a->expires_at?->toIso8601String(),
                'read' => $readIds->has($a->getKey()),
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Announcement::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:10000'],
            'audience' => ['required', 'in:all,department,branch'],
            'department_id' => ['nullable', 'required_if:audience,department', Rule::exists('departments', 'id')->where('tenant_id', Tenancy::id())],
            'branch_id' => ['nullable', 'required_if:audience,branch', Rule::exists('branches', 'id')->where('tenant_id', Tenancy::id())],
            'publish' => ['boolean'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $announcement = Announcement::query()->create([
            'title' => $data['title'],
            'body' => $data['body'],
            'audience' => $data['audience'],
            'department_id' => $data['department_id'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'published_at' => ($data['publish'] ?? true) ? CarbonImmutable::now() : null,
            'published_by' => $request->user()?->getKey(),
        ]);

        if ($announcement->published_at !== null) {
            AnnouncementPublished::dispatch($announcement);
        }

        return response()->json(['data' => ['uuid' => $announcement->uuid, 'published' => $announcement->published_at !== null]], 201);
    }

    public function markRead(Request $request, Announcement $announcement): JsonResponse
    {
        $this->authorize('view', $announcement);

        AnnouncementRead::query()->firstOrCreate(
            ['announcement_id' => $announcement->getKey(), 'user_id' => $request->user()?->getKey()],
            ['tenant_id' => $announcement->tenant_id, 'read_at' => CarbonImmutable::now()],
        );

        return response()->json(['data' => ['read' => true]]);
    }

    public function destroy(Announcement $announcement): Response
    {
        $this->authorize('delete', $announcement);
        $announcement->delete();

        return response()->noContent();
    }
}
