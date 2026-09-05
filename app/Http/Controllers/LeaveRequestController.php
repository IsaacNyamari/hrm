<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Facades\Tenancy;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Leave\LeaveService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveRequestController extends Controller
{
    public function __construct(private readonly LeaveService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $user = $request->user();
        $canOverseeAll = $user?->can('leave.approve') || $user?->can('leave.manage');
        $ownEmployeeId = $user?->employee?->getKey();
        $managedEmployeeIds = $ownEmployeeId === null ? [] : Employee::query()->where('manager_id', $ownEmployeeId)->pluck('id')->all();

        $records = LeaveRequest::query()
            ->with(['employee:id,first_name,last_name,employee_number', 'leaveType:id,name,code', 'approvals'])
            ->when(! $canOverseeAll, function ($q) use ($ownEmployeeId, $managedEmployeeIds): void {
                $q->where(function ($inner) use ($ownEmployeeId, $managedEmployeeIds): void {
                    $inner->where('employee_id', $ownEmployeeId)->orWhereIn('employee_id', $managedEmployeeIds);
                });
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('employee_id') && $canOverseeAll, fn ($q) => $q->where('employee_id', $request->integer('employee_id')))
            ->orderByDesc('start_date')
            ->paginate($request->integer('per_page', 25));

        return response()->json($records->through(fn (LeaveRequest $r): array => $this->transform($r))->toArray());
    }

    public function show(LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('view', $leaveRequest);

        return response()->json(['data' => $this->transform(
            $leaveRequest->load(['employee', 'leaveType', 'approvals.approver:id,name']),
            detailed: true,
        )]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', LeaveRequest::class);

        $data = $request->validate([
            'employee_uuid' => ['nullable', 'string'],
            'leave_type_uuid' => ['required', Rule::exists('leave_types', 'uuid')->where('tenant_id', Tenancy::id())],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'half_day_start' => ['boolean'],
            'half_day_end' => ['boolean'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'attachment_path' => ['nullable', 'string', 'max:255'],
        ]);

        $employee = $this->resolveEmployee($request, $data['employee_uuid'] ?? null);
        $type = LeaveType::query()->where('uuid', $data['leave_type_uuid'])->firstOrFail();

        $leaveRequest = $this->service->request(
            $employee,
            $type,
            CarbonImmutable::parse((string) $data['start_date']),
            CarbonImmutable::parse((string) $data['end_date']),
            [
                'half_day_start' => (bool) ($data['half_day_start'] ?? false),
                'half_day_end' => (bool) ($data['half_day_end'] ?? false),
                'reason' => $data['reason'] ?? null,
                'attachment_path' => $data['attachment_path'] ?? null,
            ],
            $request->user(),
        );

        return response()->json(['data' => $this->transform($leaveRequest->load(['leaveType', 'approvals']), detailed: true)], 201);
    }

    public function approve(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        return $this->decide($request, $leaveRequest, true);
    }

    public function reject(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        return $this->decide($request, $leaveRequest, false);
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $this->authorize('cancel', $leaveRequest);

        $updated = $this->service->cancel($leaveRequest, $request->user());

        return response()->json(['data' => $this->transform($updated)]);
    }

    private function decide(Request $request, LeaveRequest $leaveRequest, bool $approved): JsonResponse
    {
        $this->authorize('decide', $leaveRequest);

        $comment = $request->validate(['comment' => ['nullable', 'string', 'max:1000']])['comment'] ?? null;

        $updated = $this->service->decide($leaveRequest, $request->user(), $approved, $comment);

        return response()->json(['data' => $this->transform($updated->load('approvals'), detailed: true)]);
    }

    private function resolveEmployee(Request $request, ?string $employeeUuid): Employee
    {
        if ($employeeUuid !== null) {
            // requesting on behalf of someone requires the manage permission
            abort_unless($request->user()?->can('leave.manage'), 403);

            return Employee::query()->where('uuid', $employeeUuid)->firstOrFail();
        }

        $employee = $request->user()?->employee;
        abort_if($employee === null, 422, 'Your account is not linked to an employee record.');

        return $employee;
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(LeaveRequest $request, bool $detailed = false): array
    {
        $base = [
            'uuid' => $request->uuid,
            'employee' => $request->relationLoaded('employee') ? $request->employee->fullName() : null,
            'leave_type' => $request->relationLoaded('leaveType') ? $request->leaveType->name : null,
            'start_date' => $request->start_date->toDateString(),
            'end_date' => $request->end_date->toDateString(),
            'days' => $request->days,
            'status' => $request->status->value,
            'current_level' => $request->current_level,
        ];

        if (! $detailed) {
            return $base;
        }

        return $base + [
            'reason' => $request->reason,
            'half_day_start' => $request->half_day_start,
            'half_day_end' => $request->half_day_end,
            'submitted_at' => $request->submitted_at?->toIso8601String(),
            'decided_at' => $request->decided_at?->toIso8601String(),
            'approvals' => $request->approvals->map(fn ($a): array => [
                'level' => $a->level,
                'approver_type' => $a->approver_type,
                'status' => $a->status->value,
                'comment' => $a->comment,
                'decided_at' => $a->decided_at?->toIso8601String(),
            ])->all(),
        ];
    }
}
