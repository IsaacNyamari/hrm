<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Exceptions\LeaveException;
use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Mobile\LeaveRequestResource;
use App\Http\Resources\Api\V1\Mobile\LeaveTypeResource;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Leave\LeaveService;
use App\Services\SelfService\SelfServiceDashboard;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * The token owner's own leave: the types they can take, balances, their
 * requests, requesting (with an optional attachment) and cancelling — all
 * through LeaveService, so notice, overlap, balance and approval-chain rules
 * are identical to the web.
 */
class LeaveController extends Controller
{
    public function __construct(private readonly LeaveService $service) {}

    public function types(Request $request): AnonymousResourceCollection
    {
        $employee = $this->employee($request);

        $types = LeaveType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (LeaveType $type): bool => $type->allowsGender($employee->gender))
            ->values();

        return LeaveTypeResource::collection($types);
    }

    public function balances(Request $request, SelfServiceDashboard $dashboard): JsonResponse
    {
        $employee = $this->employee($request);
        $year = $request->validate(['year' => ['nullable', 'integer', 'min:2000', 'max:2100']])['year'] ?? null;

        return response()->json([
            'data' => $dashboard->leaveBalances($employee, $year !== null ? (int) $year : null),
            'meta' => ['year' => $year !== null ? (int) $year : CarbonImmutable::now()->year],
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $employee = $this->employee($request);
        $status = $request->validate(['status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'cancelled'])]])['status'] ?? null;

        $requests = LeaveRequest::query()
            ->where('employee_id', $employee->getKey())
            ->with(['leaveType:id,uuid,name', 'approvals'])
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->latest('start_date')
            ->latest('id')
            ->paginate(min($request->integer('per_page', 20), 50));

        return LeaveRequestResource::collection($requests);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', LeaveRequest::class);
        $employee = $this->employee($request);

        $data = $request->validate([
            'leave_type_uuid' => ['required', 'string', Rule::exists('leave_types', 'uuid')->where('tenant_id', Tenancy::id())->where('is_active', true)],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'half_day_start' => ['sometimes', 'boolean'],
            'half_day_end' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,heic,webp', 'max:'.(int) config('mobile.leave_attachment_max_kb', 5120)],
        ]);

        $type = LeaveType::query()->where('uuid', $data['leave_type_uuid'])->firstOrFail();

        $disk = (string) config('mobile.leave_attachment_disk', 'local');
        $path = null;

        if ($request->hasFile('attachment')) {
            $stored = $request->file('attachment')?->store('tenants/'.Tenancy::getOrFail()->uuid.'/leave-attachments', $disk);
            $path = is_string($stored) ? $stored : null;
        }

        try {
            $leaveRequest = $this->service->request(
                $employee,
                $type,
                CarbonImmutable::parse((string) $data['start_date']),
                CarbonImmutable::parse((string) $data['end_date']),
                [
                    'half_day_start' => (bool) ($data['half_day_start'] ?? false),
                    'half_day_end' => (bool) ($data['half_day_end'] ?? false),
                    'reason' => $data['reason'] ?? null,
                    'attachment_path' => $path,
                ],
                $request->user(),
            );
        } catch (LeaveException $e) {
            if ($path !== null) {
                Storage::disk($disk)->delete($path);
            }

            throw $e;
        }

        return (new LeaveRequestResource($leaveRequest->load(['leaveType:id,uuid,name', 'approvals'])))
            ->response()
            ->setStatusCode(201);
    }

    public function cancel(Request $request, string $leaveRequest): LeaveRequestResource
    {
        $employee = $this->employee($request);

        $record = LeaveRequest::query()
            ->where('uuid', $leaveRequest)
            ->where('employee_id', $employee->getKey())
            ->with(['employee', 'leaveType'])
            ->firstOrFail();

        $this->authorize('cancel', $record);

        $updated = $this->service->cancel($record, $request->user());

        return new LeaveRequestResource($updated->load(['leaveType:id,uuid,name', 'approvals']));
    }

    private function employee(Request $request): Employee
    {
        $employee = $request->user()?->employee;
        abort_if($employee === null, 422, 'Your account is not linked to an employee record.');

        return $employee;
    }
}
