<?php

declare(strict_types=1);

namespace App\Services\Leave;

use App\Enums\LeaveApprovalStatus;
use App\Enums\LeaveStatus;
use App\Enums\Permission;
use App\Events\LeaveApproved;
use App\Events\LeaveRejected;
use App\Events\LeaveRequested;
use App\Exceptions\LeaveException;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestApproval;
use App\Models\LeaveType;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class LeaveService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly WorkingDayCalculator $calculator,
    ) {}

    /**
     * @param  array{reason?: string|null, attachment_path?: string|null, half_day_start?: bool, half_day_end?: bool}  $options
     */
    public function request(
        Employee $employee,
        LeaveType $type,
        CarbonImmutable $start,
        CarbonImmutable $end,
        array $options = [],
        ?User $requestedBy = null,
    ): LeaveRequest {
        if ($end->lessThan($start)) {
            throw LeaveException::invalidRange();
        }

        if (! $type->allowsGender($employee->gender)) {
            throw LeaveException::genderRestricted();
        }

        $noticeDays = (int) $start->diffInDays(CarbonImmutable::now()->startOfDay(), false) * -1;
        if ($noticeDays < $type->min_notice_days) {
            throw LeaveException::insufficientNotice($type->min_notice_days);
        }

        $halfStart = (bool) ($options['half_day_start'] ?? false);
        $halfEnd = (bool) ($options['half_day_end'] ?? false);
        $days = $this->calculator->countWorkingDays($start, $end, $halfStart, $halfEnd);

        if ($days <= 0) {
            throw LeaveException::zeroWorkingDays();
        }

        if ($type->max_consecutive_days !== null && $days > $type->max_consecutive_days) {
            throw LeaveException::exceedsConsecutive($type->max_consecutive_days);
        }

        if ($type->requires_attachment && empty($options['attachment_path'])) {
            throw LeaveException::attachmentRequired();
        }

        if ($this->overlaps($employee, $start, $end)) {
            throw LeaveException::overlapping();
        }

        $year = $this->leaveYear($start);
        $balance = $this->balanceFor($employee, $type, $year);

        if ($type->is_paid && $balance->remainingDays() < $days) {
            throw LeaveException::insufficientBalance($balance->remainingDays(), $days);
        }

        return DB::transaction(function () use ($employee, $type, $start, $end, $days, $halfStart, $halfEnd, $options, $requestedBy, $balance): LeaveRequest {
            $autoApprove = ! $type->requires_approval;

            $leaveRequest = LeaveRequest::query()->create([
                'employee_id' => $employee->getKey(),
                'leave_type_id' => $type->getKey(),
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'half_day_start' => $halfStart,
                'half_day_end' => $halfEnd,
                'days' => $days,
                'reason' => $options['reason'] ?? null,
                'attachment_path' => $options['attachment_path'] ?? null,
                'status' => $autoApprove ? LeaveStatus::Approved : LeaveStatus::Pending,
                'current_level' => 1,
                'requested_by' => $requestedBy?->getKey(),
                'submitted_at' => CarbonImmutable::now(),
                'decided_at' => $autoApprove ? CarbonImmutable::now() : null,
            ]);

            $this->buildApprovalChain($leaveRequest, $employee);

            if ($autoApprove) {
                $balance->forceFill(['used_days' => $balance->used_days + $days])->save();
                LeaveApproved::dispatch($leaveRequest);
            } else {
                $balance->forceFill(['pending_days' => $balance->pending_days + $days])->save();
                LeaveRequested::dispatch($leaveRequest);
            }

            return $leaveRequest->load('approvals');
        });
    }

    public function decide(LeaveRequest $request, User $approver, bool $approved, ?string $comment = null): LeaveRequest
    {
        if (! $request->status->isOpen()) {
            throw LeaveException::notPending();
        }

        $approval = $request->approvals()->where('level', $request->current_level)->first();

        if ($approval === null || ! $this->canActOn($request, $approval, $approver)) {
            throw LeaveException::notAnApprover();
        }

        return DB::transaction(function () use ($request, $approval, $approver, $approved, $comment): LeaveRequest {
            $approval->forceFill([
                'status' => $approved ? LeaveApprovalStatus::Approved : LeaveApprovalStatus::Rejected,
                'approver_id' => $approver->getKey(),
                'comment' => $comment,
                'decided_at' => CarbonImmutable::now(),
            ])->save();

            $balance = $this->balanceFor($request->employee, $request->leaveType, $this->leaveYear($request->start_date));

            if (! $approved) {
                $request->forceFill([
                    'status' => LeaveStatus::Rejected,
                    'decided_at' => CarbonImmutable::now(),
                    'decision_note' => $comment,
                ])->save();
                $balance->forceFill(['pending_days' => max(0, $balance->pending_days - $request->days)])->save();
                LeaveRejected::dispatch($request);

                return $request;
            }

            $nextLevel = $request->approvals()->where('level', '>', $request->current_level)->min('level');

            if ($nextLevel !== null) {
                $request->forceFill(['current_level' => $nextLevel])->save();

                return $request;
            }

            $request->forceFill([
                'status' => LeaveStatus::Approved,
                'decided_at' => CarbonImmutable::now(),
                'decision_note' => $comment,
            ])->save();

            $balance->forceFill([
                'pending_days' => max(0, $balance->pending_days - $request->days),
                'used_days' => $balance->used_days + $request->days,
            ])->save();

            LeaveApproved::dispatch($request);

            return $request;
        });
    }

    public function cancel(LeaveRequest $request, User $actor): LeaveRequest
    {
        $isOwner = $request->requested_by === $actor->getKey()
            || $request->employee->user_id === $actor->getKey();
        $canManage = $this->userCan($actor, $request->tenant_id, Permission::LeaveManage, Permission::LeaveApprove);

        $cancellable = $request->status === LeaveStatus::Pending
            || ($request->status === LeaveStatus::Approved && $request->start_date->isFuture());

        if (! $cancellable || (! $isOwner && ! $canManage)) {
            throw LeaveException::cannotCancel();
        }

        return DB::transaction(function () use ($request): LeaveRequest {
            $balance = $this->balanceFor($request->employee, $request->leaveType, $this->leaveYear($request->start_date));

            if ($request->status === LeaveStatus::Pending) {
                $balance->forceFill(['pending_days' => max(0, $balance->pending_days - $request->days)])->save();
            } elseif ($request->status === LeaveStatus::Approved) {
                $balance->forceFill(['used_days' => max(0, $balance->used_days - $request->days)])->save();
            }

            $request->forceFill(['status' => LeaveStatus::Cancelled, 'decided_at' => CarbonImmutable::now()])->save();

            return $request;
        });
    }

    public function balanceFor(Employee $employee, LeaveType $type, int $year): LeaveBalance
    {
        return LeaveBalance::query()->firstOrCreate(
            [
                'tenant_id' => $employee->tenant_id,
                'employee_id' => $employee->getKey(),
                'leave_type_id' => $type->getKey(),
                'year' => $year,
            ],
            ['allocated_days' => $this->accruedAllocation($type, $year)],
        );
    }

    public function adjustBalance(Employee $employee, LeaveType $type, int $year, float $delta): LeaveBalance
    {
        $balance = $this->balanceFor($employee, $type, $year);
        $balance->forceFill(['adjustment_days' => $balance->adjustment_days + $delta])->save();

        return $balance;
    }

    /**
     * Grant / refresh allocations for every active employee and leave type in
     * the bound tenant for $year, carrying forward from the prior year where the
     * type allows it. Idempotent.
     */
    public function accrue(?int $year = null): int
    {
        $year ??= $this->leaveYear(CarbonImmutable::now());
        $touched = 0;

        $types = LeaveType::query()->where('is_active', true)->get();

        Employee::query()->active()->select(['id', 'tenant_id', 'employment_type_id'])
            ->chunkById(500, function ($employees) use ($types, $year, &$touched): void {
                foreach ($employees as $employee) {
                    $touched += $this->accrueEmployee($employee, $types, $year);
                }
            });

        return $touched;
    }

    /**
     * @param  EloquentCollection<int, LeaveType>  $types
     */
    private function accrueEmployee(Employee $employee, EloquentCollection $types, int $year): int
    {
        $touched = 0;

        foreach ($types as $type) {
            if ($type->employment_type_id !== null && $type->employment_type_id !== $employee->employment_type_id) {
                continue;
            }

            $balance = $this->balanceFor($employee, $type, $year);
            $carry = 0.0;

            if ($type->allow_carry_forward) {
                $prior = LeaveBalance::query()
                    ->where('employee_id', $employee->getKey())
                    ->where('leave_type_id', $type->getKey())
                    ->where('year', $year - 1)
                    ->first();

                if ($prior !== null) {
                    $carry = max(0.0, $prior->remainingDays());
                    if ($type->max_carry_forward_days !== null) {
                        $carry = min($carry, $type->max_carry_forward_days);
                    }
                }
            }

            $balance->forceFill([
                'allocated_days' => $this->accruedAllocation($type, $year),
                'carried_forward_days' => $carry,
            ])->save();
            $touched++;
        }

        return $touched;
    }

    private function accruedAllocation(LeaveType $type, int $year): float
    {
        if ($type->accrual->value !== 'monthly') {
            return $type->default_days_per_year;
        }

        $now = CarbonImmutable::now();
        $monthsElapsed = $year < $now->year ? 12 : ($year > $now->year ? 0 : $now->month);

        return round($type->default_days_per_year / 12 * $monthsElapsed, 2);
    }

    private function overlaps(Employee $employee, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return $employee->leaveRequests()
            ->whereIn('status', [LeaveStatus::Pending->value, LeaveStatus::Approved->value])
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->exists();
    }

    private function buildApprovalChain(LeaveRequest $request, Employee $employee): void
    {
        $chain = $this->approvalChain();
        $level = 0;

        foreach ($chain as $step) {
            if ($step === 'manager' && $employee->manager_id === null) {
                continue; // no manager to route through
            }

            $request->approvals()->create([
                'tenant_id' => $request->tenant_id,
                'level' => ++$level,
                'approver_type' => $step,
                'status' => LeaveApprovalStatus::Pending,
            ]);
        }

        if ($level === 0) {
            $request->approvals()->create([
                'tenant_id' => $request->tenant_id,
                'level' => 1,
                'approver_type' => 'hr',
                'status' => LeaveApprovalStatus::Pending,
            ]);
        }
    }

    private function canActOn(LeaveRequest $request, LeaveRequestApproval $approval, User $approver): bool
    {
        if ($this->userCan($approver, $request->tenant_id, Permission::LeaveApprove, Permission::LeaveManage)) {
            return true;
        }

        if ($approval->approver_type === 'manager') {
            $managerId = $request->employee->manager?->user_id;

            return $managerId !== null && $managerId === $approver->getKey();
        }

        return false;
    }

    private function userCan(User $user, ?int $tenantId, Permission ...$permissions): bool
    {
        if ($tenantId === null) {
            return false;
        }

        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($tenantId);

        try {
            foreach ($permissions as $permission) {
                if ($user->hasPermissionTo($permission->value)) {
                    return true;
                }
            }

            return false;
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }

    /**
     * @return array<int, string>
     */
    private function approvalChain(): array
    {
        $tenant = $this->context->get();
        $configured = $tenant?->settings['leave']['approval_chain'] ?? null;

        /** @var array<int, string> $chain */
        $chain = is_array($configured) && $configured !== [] ? $configured : config('leave.approval_chain');

        return array_values(array_map('strval', $chain));
    }

    private function leaveYear(CarbonImmutable $date): int
    {
        $startMonth = (int) config('leave.year_start_month', 1);

        return $date->month >= $startMonth ? $date->year : $date->year - 1;
    }
}
