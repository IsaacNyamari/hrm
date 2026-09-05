<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Facades\Tenancy;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LeaveRequestResource;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Leave\LeaveService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class LeaveRequestController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $requests = LeaveRequest::query()
            ->with(['employee:id,uuid,first_name,last_name', 'leaveType:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('employee_uuid'), fn ($q) => $q->whereRelation('employee', 'uuid', $request->string('employee_uuid')->toString()))
            ->orderByDesc('start_date')
            ->paginate(min($request->integer('per_page', 25), 100));

        return LeaveRequestResource::collection($requests);
    }

    public function store(Request $request, LeaveService $service): LeaveRequestResource
    {
        $data = $request->validate([
            'employee_uuid' => ['required', Rule::exists('employees', 'uuid')->where('tenant_id', Tenancy::id())],
            'leave_type_uuid' => ['required', Rule::exists('leave_types', 'uuid')->where('tenant_id', Tenancy::id())],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $employee = Employee::query()->where('uuid', $data['employee_uuid'])->firstOrFail();
        $type = LeaveType::query()->where('uuid', $data['leave_type_uuid'])->firstOrFail();

        $leaveRequest = $service->request(
            $employee,
            $type,
            CarbonImmutable::parse((string) $data['start_date']),
            CarbonImmutable::parse((string) $data['end_date']),
            ['reason' => $data['reason'] ?? null],
            $request->user(),
        );

        return new LeaveRequestResource($leaveRequest->load(['employee', 'leaveType']));
    }
}
