<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveBalanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LeaveRequest::class);

        $user = $request->user();
        $canOverseeAll = $user?->can('leave.approve') || $user?->can('leave.manage');

        $employeeId = $user?->employee?->getKey();

        if ($request->filled('employee_id')) {
            abort_unless($canOverseeAll, 403);
            $employeeId = $request->integer('employee_id');
        }

        abort_if($employeeId === null, 422, 'No employee context for a leave balance.');

        $year = $request->integer('year', CarbonImmutable::now()->year);

        $balances = LeaveBalance::query()
            ->with('leaveType:id,name,code,is_paid')
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->get();

        return response()->json([
            'data' => [
                'employee' => Employee::query()->whereKey($employeeId)->first()?->fullName(),
                'year' => $year,
                'balances' => $balances->map(fn (LeaveBalance $b): array => [
                    'leave_type' => $b->leaveType?->name,
                    'code' => $b->leaveType?->code,
                    'allocated' => $b->allocated_days,
                    'carried_forward' => $b->carried_forward_days,
                    'adjustment' => $b->adjustment_days,
                    'used' => $b->used_days,
                    'pending' => $b->pending_days,
                    'expired' => $b->expired_days,
                    'remaining' => $b->remainingDays(),
                ])->all(),
            ],
        ]);
    }
}
