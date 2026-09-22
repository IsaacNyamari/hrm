<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Mobile\AttendanceResource;
use App\Models\Attendance;
use App\Support\Attendance\TodaySnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Today's attendance across the organisation — the same figures as the web
 * "Live today" screen — for staff who can see attendance. The app keeps it
 * live on the `tenant.{id}.attendance` channel.
 */
class TeamAttendanceController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Attendance::class);

        $snapshot = TodaySnapshot::build();

        return response()->json(['data' => [
            'date' => CarbonImmutable::today()->toDateString(),
            'stats' => $snapshot->stats,
            'records' => AttendanceResource::collection($snapshot->records)->resolve($request),
            'channel' => 'tenant.'.$request->user()?->tenant_id.'.attendance',
        ]]);
    }
}
