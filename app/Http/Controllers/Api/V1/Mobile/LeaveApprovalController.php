<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Enums\LeaveStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Mobile\LeaveRequestResource;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\Leave\LeaveService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Leave requests waiting for the token owner's decision: every pending request
 * for leave approvers / managers (as on the web leave panel), otherwise their
 * direct reports' requests that sit at the line-manager step. Decisions go
 * through the same policy and LeaveService::decide as the web.
 */
class LeaveApprovalController extends Controller
{
    public function __construct(private readonly LeaveService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $this->user($request);
        $canOversee = $user->can('leave.approve') || $user->can('leave.manage');
        $ownEmployeeId = $user->employee?->getKey();

        $requests = LeaveRequest::query()
            ->where('status', LeaveStatus::Pending->value)
            ->when(! $canOversee, function ($q) use ($ownEmployeeId): void {
                $q->whereHas('employee', fn ($e) => $e->where('manager_id', $ownEmployeeId ?? 0))
                    ->whereHas('approvals', fn ($a) => $a->whereColumn('leave_request_approvals.level', 'leave_requests.current_level')
                        ->where('approver_type', 'manager'));
            })
            ->with(['employee:id,first_name,middle_name,last_name,employee_number', 'leaveType:id,uuid,name', 'approvals'])
            ->oldest('start_date')
            ->paginate(min($request->integer('per_page', 30), 50));

        return LeaveRequestResource::collection($requests);
    }

    public function approve(Request $request, string $leaveRequest): LeaveRequestResource
    {
        return $this->decide($request, $leaveRequest, true);
    }

    public function reject(Request $request, string $leaveRequest): LeaveRequestResource
    {
        return $this->decide($request, $leaveRequest, false);
    }

    private function decide(Request $request, string $uuid, bool $approved): LeaveRequestResource
    {
        $record = LeaveRequest::query()->where('uuid', $uuid)->with(['employee.manager', 'leaveType'])->firstOrFail();

        $this->authorize('decide', $record);

        $comment = $request->validate(['comment' => ['nullable', 'string', 'max:1000']])['comment'] ?? null;

        $updated = $this->service->decide($record, $this->user($request), $approved, $comment);

        return new LeaveRequestResource($updated->load(['employee:id,first_name,middle_name,last_name,employee_number', 'leaveType:id,uuid,name', 'approvals']));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_if($user === null, 401);

        return $user;
    }
}
