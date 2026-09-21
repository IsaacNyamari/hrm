<?php

declare(strict_types=1);

namespace App\Services\SelfService;

use App\Enums\EmploymentStatus;
use App\Enums\PayrollRunStatus;
use App\Enums\TaskStatus;
use App\Models\Announcement;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Payslip;
use App\Models\Task;
use App\Services\Attendance\AttendanceService;
use App\Services\Leave\LeaveService;
use App\Services\Payroll\AttendancePayCalculator;
use App\Services\Payroll\PayrollService;
use App\Support\Payroll\CurrentPayCycle;
use Carbon\CarbonImmutable;

class SelfServiceDashboard
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly LeaveService $leave,
        private readonly PayrollService $payroll,
        private readonly AttendancePayCalculator $attendancePay,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Employee $employee): array
    {
        $today = $this->attendance->todayFor($employee);
        $year = CarbonImmutable::now()->year;

        return [
            'employee' => [
                'name' => $employee->displayName(),
                'number' => $employee->employee_number,
                'job_title' => $employee->job_title,
            ],
            'attendance_today' => [
                'clocked_in' => $today?->isClockedIn() ?? false,
                'on_break' => $today?->isOnBreak() ?? false,
                'clock_in_at' => $today?->clock_in_at?->toIso8601String(),
                'status' => $today?->status->value,
            ],
            'leave_balances' => LeaveType::query()->where('is_active', true)->orderBy('name')->get()
                ->map(fn (LeaveType $type): array => [
                    'type' => $type->name,
                    'remaining' => $this->leave->balanceFor($employee, $type, $year)->remainingDays(),
                ])->all(),
            'pending_leave' => $employee->leaveRequests()->where('status', 'pending')->count(),
            'open_hr_requests' => $employee->hrRequests()->whereNotIn('status', ['resolved', 'closed'])->count(),
            'documents_expiring_30d' => $employee->documents()
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<=', CarbonImmutable::now()->addDays(30)->toDateString())
                ->whereDate('expiry_date', '>=', CarbonImmutable::now()->toDateString())
                ->count(),
            'recent_payslips' => Payslip::query()
                ->where('employee_id', $employee->getKey())
                ->with('run.period:id,name,pay_date')
                ->latest('id')->limit(3)->get()
                ->map(fn (Payslip $p): array => [
                    'uuid' => $p->uuid,
                    'period' => $p->run->period->name,
                    'pay_date' => $p->run->period->pay_date->toDateString(),
                    'net_pay_cents' => $p->net_pay_cents,
                ])->all(),
            'job' => $this->job($employee),
            'earnings_to_date' => $this->earningsToDate($employee),
            'salary' => $this->salary($employee, $year),
            'tasks' => $this->tasks($employee),
            'leave' => $this->leave($employee, $year),
            'attendance_month' => $this->attendanceThisMonth($employee),
            'announcements' => Announcement::query()->visibleTo($employee)
                ->latest('published_at')->limit(5)->get()
                ->map(fn (Announcement $a): array => [
                    'uuid' => $a->uuid,
                    'title' => $a->title,
                    'published_at' => $a->published_at?->toIso8601String(),
                ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function job(Employee $employee): array
    {
        $employee->loadMissing(['department:id,name', 'manager:id,first_name,middle_name,last_name', 'branch:id,name']);

        return [
            'department' => $employee->department?->name,
            'manager' => $employee->manager?->fullName(),
            'branch' => $employee->branch?->name,
            'hire_date' => $employee->hire_date?->toDateString(),
            'status' => $employee->status->label(),
            'on_probation' => $employee->status === EmploymentStatus::Probation,
        ];
    }

    /**
     * What the employee has earned so far this pay cycle (gross, from attendance).
     *
     * @return array{period: string, gross_cents: int, full_basic_cents: int, payable_days: float, overtime_hours: float, currency: string}
     */
    private function earningsToDate(Employee $employee): array
    {
        $tenant = $employee->tenant()->firstOrFail();
        $period = CurrentPayCycle::for($tenant, CarbonImmutable::today());
        $estimate = $this->payroll->estimate($employee, $period, CarbonImmutable::today());

        return [
            'period' => $period->name,
            'gross_cents' => $estimate['gross_cents'],
            'full_basic_cents' => $estimate['full_basic_cents'],
            'payable_days' => (float) ($estimate['attendance']['payable_days'] ?? 0),
            'overtime_hours' => (float) ($estimate['attendance']['overtime_hours'] ?? 0) + (float) ($estimate['attendance']['rest_day_hours'] ?? 0),
            'currency' => $employee->salary_currency ?? $tenant->currency,
        ];
    }

    /**
     * Pay actually released to the employee: payslips from approved, paid or
     * locked runs only (drafts still being checked by payroll are hidden).
     *
     * @return array<string, mixed>
     */
    private function salary(Employee $employee, int $year): array
    {
        $released = Payslip::query()
            ->where('employee_id', $employee->getKey())
            ->whereHas('run', fn ($q) => $q->whereIn('status', [
                PayrollRunStatus::Approved->value, PayrollRunStatus::Paid->value, PayrollRunStatus::Locked->value,
            ]))
            ->with('run.period:id,name,pay_date')
            ->get()
            ->sortByDesc(fn (Payslip $p) => $p->run->period->pay_date)
            ->values();

        $thisYear = $released->filter(fn (Payslip $p) => $p->run->period->pay_date->year === $year);

        return [
            'ytd_gross_cents' => (int) $thisYear->sum('gross_earnings_cents'),
            'ytd_deductions_cents' => (int) $thisYear->sum('total_deductions_cents'),
            'ytd_net_cents' => (int) $thisYear->sum('net_pay_cents'),
            'payslips' => $released->take(12)->map(fn (Payslip $p): array => [
                'uuid' => $p->uuid,
                'period' => $p->run->period->name,
                'pay_date' => $p->run->period->pay_date->toDateString(),
                'gross_cents' => $p->gross_earnings_cents,
                'deductions_cents' => $p->total_deductions_cents,
                'net_pay_cents' => $p->net_pay_cents,
                'currency' => $p->currency,
                'status' => $p->status->value,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tasks(Employee $employee): array
    {
        $open = Task::query()
            ->where('employee_id', $employee->getKey())
            ->whereIn('status', [TaskStatus::Pending->value, TaskStatus::InProgress->value])
            ->orderByRaw('due_date is null')->orderBy('due_date')
            ->get();

        $today = CarbonImmutable::today();

        return [
            'open' => $open->count(),
            'overdue' => $open->filter(fn (Task $t) => $t->due_date?->lessThan($today) ?? false)->count(),
            'completed_this_month' => Task::query()
                ->where('employee_id', $employee->getKey())
                ->where('status', TaskStatus::Completed->value)
                ->where('completed_at', '>=', CarbonImmutable::now()->startOfMonth())
                ->count(),
            'items' => $open->take(6)->map(fn (Task $t): array => [
                'uuid' => $t->uuid,
                'title' => $t->title,
                'priority' => $t->priority->value,
                'status' => $t->status->value,
                'due_date' => $t->due_date?->toDateString(),
                'overdue' => $t->due_date?->lessThan($today) ?? false,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function leave(Employee $employee, int $year): array
    {
        return [
            'balances' => LeaveType::query()->where('is_active', true)->orderBy('name')->get()
                ->map(function (LeaveType $type) use ($employee, $year): array {
                    $balance = $this->leave->balanceFor($employee, $type, $year);

                    return [
                        'type' => $type->name,
                        'entitled' => round($balance->allocated_days + $balance->carried_forward_days + $balance->adjustment_days, 2),
                        'used' => $balance->used_days,
                        'pending' => $balance->pending_days,
                        'remaining' => $balance->remainingDays(),
                    ];
                })->all(),
            'requests' => LeaveRequest::query()
                ->where('employee_id', $employee->getKey())
                ->with('leaveType:id,name')
                ->latest('start_date')->limit(5)->get()
                ->map(fn (LeaveRequest $r): array => [
                    'uuid' => $r->uuid,
                    'type' => $r->leaveType->name,
                    'start_date' => $r->start_date->toDateString(),
                    'end_date' => $r->end_date->toDateString(),
                    'days' => $r->days,
                    'status' => $r->status->value,
                ])->all(),
        ];
    }

    /**
     * @return array{present_days: float, working_days_so_far: float, worked_hours: float, overtime_hours: float, paid_leave_days: float}
     */
    private function attendanceThisMonth(Employee $employee): array
    {
        $summary = $this->attendancePay->summarizeRange($employee, CarbonImmutable::today()->startOfMonth(), CarbonImmutable::today());

        return [
            'present_days' => $summary['present_days'],
            'working_days_so_far' => $summary['working_days'],
            'worked_hours' => $summary['worked_hours'],
            'overtime_hours' => round($summary['overtime_hours'] + $summary['rest_day_hours'], 2),
            'paid_leave_days' => $summary['paid_leave_days'],
        ];
    }
}
