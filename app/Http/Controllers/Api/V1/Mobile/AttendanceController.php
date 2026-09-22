<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Enums\AttendanceSource;
use App\Enums\LeaveStatus;
use App\Exceptions\AttendanceException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Mobile\AttendanceResource;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\Attendance\AttendanceService;
use App\Services\SelfService\SelfServiceDashboard;
use App\Support\Attendance\PunchData;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The token owner's own attendance: today's status, GPS clock in / out and
 * breaks (through AttendanceService, so shifts, lateness and the branch
 * geofence apply exactly as on the web), and a month's history.
 */
class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $service) {}

    public function today(Request $request): JsonResponse
    {
        $employee = $this->employee($request);

        return response()->json(['data' => $this->todayPayload($employee)]);
    }

    public function clockIn(Request $request): JsonResponse
    {
        return $this->punch($request, fn (Employee $e, PunchData $p) => $this->service->clockIn($e, $p));
    }

    public function clockOut(Request $request): JsonResponse
    {
        return $this->punch($request, fn (Employee $e, PunchData $p) => $this->service->clockOut($e, $p));
    }

    public function startBreak(Request $request): JsonResponse
    {
        return $this->punch($request, fn (Employee $e, PunchData $p) => $this->service->startBreak($e, $p));
    }

    public function endBreak(Request $request): JsonResponse
    {
        return $this->punch($request, fn (Employee $e, PunchData $p) => $this->service->endBreak($e, $p));
    }

    /**
     * A month of records (`?month=2026-09`, default this month), the approved
     * leave that falls in it, and the month's totals as payroll counts them.
     */
    public function history(Request $request, SelfServiceDashboard $dashboard): JsonResponse
    {
        $employee = $this->employee($request);

        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        $start = isset($data['month'])
            ? CarbonImmutable::createFromFormat('!Y-m', $data['month'])->startOfMonth()
            : CarbonImmutable::today()->startOfMonth();
        $end = $start->endOfMonth()->startOfDay();
        $today = CarbonImmutable::today();

        $records = $employee->attendances()
            ->with(['shift:id,name,start_time,end_time', 'breaks'])
            ->whereDate('work_date', '>=', $start->toDateString())
            ->whereDate('work_date', '<=', $end->toDateString())
            ->orderByDesc('work_date')
            ->get();

        $leave = LeaveRequest::query()
            ->where('employee_id', $employee->getKey())
            ->where('status', LeaveStatus::Approved->value)
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->with('leaveType:id,name')
            ->orderBy('start_date')
            ->get();

        return response()->json(['data' => [
            'month' => $start->format('Y-m'),
            'label' => $start->format('F Y'),
            'summary' => $start->greaterThan($today)
                ? null
                : $dashboard->attendanceBetween($employee, $start, $end->lessThan($today) ? $end : $today),
            'records' => AttendanceResource::collection($records)->resolve($request),
            'leave' => $leave->map(fn (LeaveRequest $r): array => [
                'uuid' => $r->uuid,
                'type' => $r->leaveType->name,
                'start_date' => $r->start_date->toDateString(),
                'end_date' => $r->end_date->toDateString(),
                'days' => $r->days,
                'half_day_start' => $r->half_day_start,
                'half_day_end' => $r->half_day_end,
            ])->all(),
        ]]);
    }

    /**
     * @param  Closure(Employee, PunchData): Attendance  $action
     */
    private function punch(Request $request, Closure $action): JsonResponse
    {
        $employee = $this->employee($request);

        $data = $request->validate([
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ]);

        $accuracy = isset($data['accuracy']) ? (int) round((float) $data['accuracy']) : null;

        $action($employee, new PunchData(
            source: AttendanceSource::Mobile,
            ip: $request->ip(),
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
            location: $accuracy === null ? 'Mobile app' : "Mobile app GPS (±{$accuracy} m)",
            recordedBy: $request->user()?->getKey(),
        ));

        return response()->json(['data' => $this->todayPayload($employee)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function todayPayload(Employee $employee): array
    {
        $attendance = $this->service->todayFor($employee);
        $attendance?->load(['shift:id,name,start_time,end_time', 'breaks']);
        $shift = $attendance?->shift ?? $this->service->resolveShift($employee, CarbonImmutable::today());

        return [
            'date' => CarbonImmutable::today()->toDateString(),
            'clocked_in' => $attendance?->isClockedIn() ?? false,
            'on_break' => $attendance?->isOnBreak() ?? false,
            'clocked_out' => $attendance?->clock_out_at !== null,
            'shift' => $shift === null ? null : [
                'name' => $shift->name,
                'start_time' => substr($shift->start_time, 0, 5),
                'end_time' => substr($shift->end_time, 0, 5),
            ],
            'attendance' => $attendance === null ? null : (new AttendanceResource($attendance))->resolve(),
        ];
    }

    private function employee(Request $request): Employee
    {
        $employee = $request->user()?->employee;

        if ($employee === null) {
            throw AttendanceException::noEmployeeRecord();
        }

        return $employee;
    }
}
