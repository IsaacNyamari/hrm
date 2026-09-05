<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\LeaveStatus;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveCalendarController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $from = CarbonImmutable::parse($request->string('from', CarbonImmutable::now()->startOfMonth()->toDateString())->toString());
        $to = CarbonImmutable::parse($request->string('to', CarbonImmutable::now()->endOfMonth()->toDateString())->toString());

        $leave = LeaveRequest::query()
            ->with(['employee:id,first_name,last_name', 'leaveType:id,name,code,color'])
            ->where('status', LeaveStatus::Approved->value)
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->get()
            ->map(fn (LeaveRequest $r): array => [
                'type' => 'leave',
                'employee' => $r->employee->fullName(),
                'leave_type' => $r->leaveType?->name,
                'color' => $r->leaveType?->color,
                'start' => $r->start_date->toDateString(),
                'end' => $r->end_date->toDateString(),
            ]);

        $holidays = Holiday::query()
            ->where('is_active', true)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->get()
            ->map(fn (Holiday $h): array => [
                'type' => 'holiday',
                'name' => $h->name,
                'start' => $h->date->toDateString(),
                'end' => $h->date->toDateString(),
            ]);

        return response()->json(['data' => $leave->concat($holidays)->values()]);
    }
}
