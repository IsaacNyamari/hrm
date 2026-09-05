<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Exceptions\AttendanceException;
use App\Facades\Tenancy;
use App\Models\Attendance;
use App\Models\Employee;
use App\Services\Attendance\AttendanceService;
use App\Support\Attendance\PunchData;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $service) {}

    public function index(Request $request): JsonResponse|View
    {
        if (! $request->wantsJson()) {
            return view('attendance.index');
        }

        $canViewAll = $request->user()?->can('viewAny', Attendance::class) === true;
        $ownEmployeeId = $request->user()?->employee?->getKey();

        abort_if(! $canViewAll && $ownEmployeeId === null, 403);

        $records = Attendance::query()
            ->with(['employee:id,first_name,last_name,employee_number', 'shift:id,name'])
            ->when(! $canViewAll, fn ($q) => $q->where('employee_id', $ownEmployeeId))
            ->when($canViewAll && $request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->integer('employee_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('work_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('work_date', '<=', $request->date('to')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->orderByDesc('work_date')
            ->paginate($request->integer('per_page', 31));

        return response()->json($records->through(fn (Attendance $a): array => $this->transform($a))->toArray());
    }

    public function today(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);
        $attendance = $this->service->todayFor($employee);

        return response()->json(['data' => [
            'date' => CarbonImmutable::now()->toDateString(),
            'clocked_in' => $attendance?->isClockedIn() ?? false,
            'on_break' => $attendance?->isOnBreak() ?? false,
            'attendance' => $attendance === null ? null : $this->transform($attendance),
        ]]);
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

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Attendance::class);

        $data = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('tenant_id', Tenancy::id())],
            'work_date' => ['required', 'date'],
            'clock_in_at' => ['nullable', 'date'],
            'clock_out_at' => ['nullable', 'date', 'after_or_equal:clock_in_at'],
            'break_minutes' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', new Enum(AttendanceStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $employee = Employee::query()->whereKey($data['employee_id'])->firstOrFail();

        $attendance = $this->service->recordManual(
            $employee,
            CarbonImmutable::parse((string) $data['work_date']),
            array_filter(
                Arr::only($data, ['clock_in_at', 'clock_out_at', 'break_minutes', 'status', 'notes']),
                static fn ($v): bool => $v !== null,
            ),
            (int) $request->user()?->getKey(),
        );

        return response()->json(['data' => $this->transform($attendance)], 201);
    }

    public function update(Request $request, Attendance $attendance): JsonResponse
    {
        $this->authorize('update', $attendance);

        $data = $request->validate([
            'clock_in_at' => ['nullable', 'date'],
            'clock_out_at' => ['nullable', 'date', 'after_or_equal:clock_in_at'],
            'break_minutes' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', new Enum(AttendanceStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $attendance = $this->service->recordManual(
            $attendance->employee,
            $attendance->work_date,
            array_filter($data, static fn ($v): bool => $v !== null),
            (int) $request->user()?->getKey(),
        );

        return response()->json(['data' => $this->transform($attendance)]);
    }

    /**
     * @param  \Closure(Employee, PunchData): Attendance  $action
     */
    private function punch(Request $request, \Closure $action): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $punch = new PunchData(
            source: AttendanceSource::Web,
            ip: $request->ip(),
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
            location: $data['location'] ?? null,
            recordedBy: $request->user()?->getKey(),
        );

        $attendance = $action($employee, $punch);

        return response()->json(['data' => $this->transform($attendance)]);
    }

    private function currentEmployee(Request $request): Employee
    {
        $employee = $request->user()?->employee;

        if ($employee === null) {
            throw AttendanceException::noEmployeeRecord();
        }

        return $employee;
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Attendance $attendance): array
    {
        return [
            'uuid' => $attendance->uuid,
            'work_date' => $attendance->work_date->toDateString(),
            'employee' => $attendance->relationLoaded('employee')
                ? $attendance->employee->fullName()
                : null,
            'clock_in_at' => $attendance->clock_in_at?->toIso8601String(),
            'clock_out_at' => $attendance->clock_out_at?->toIso8601String(),
            'break_minutes' => $attendance->break_minutes,
            'worked_minutes' => $attendance->worked_minutes,
            'overtime_minutes' => $attendance->overtime_minutes,
            'status' => $attendance->status->value,
            'source' => $attendance->source->value,
        ];
    }
}
