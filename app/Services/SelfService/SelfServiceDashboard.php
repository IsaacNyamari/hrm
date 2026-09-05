<?php

declare(strict_types=1);

namespace App\Services\SelfService;

use App\Models\Announcement;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\Payslip;
use App\Services\Attendance\AttendanceService;
use App\Services\Leave\LeaveService;
use Carbon\CarbonImmutable;

class SelfServiceDashboard
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly LeaveService $leave,
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
            'announcements' => Announcement::query()->visibleTo($employee)
                ->latest('published_at')->limit(5)->get()
                ->map(fn (Announcement $a): array => [
                    'uuid' => $a->uuid,
                    'title' => $a->title,
                    'published_at' => $a->published_at?->toIso8601String(),
                ])->all(),
        ];
    }
}
