<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\AttendanceStatus;
use App\Models\Application;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\LeaveBalance;
use App\Models\PayrollRun;
use App\Models\User;
use App\Models\Vacancy;
use App\Support\TenantCache;
use Carbon\CarbonImmutable;
use Spatie\Permission\PermissionRegistrar;

/**
 * Chart-ready datasets for the reports overview dashboard. Each chart is
 * gated behind the same domain permission its underlying report requires,
 * so a viewer without that permission simply doesn't get the card.
 */
class ReportCharts
{
    /**
     * @return array<int, array{key: string, title: string, type: string, labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, int|float>}>}>
     */
    public function for(User $user): array
    {
        if (! $this->can($user, 'reports.view')) {
            return [];
        }

        return TenantCache::remember(
            'dashboard.report-charts:'.$user->getKey(),
            60,
            fn (): array => $this->compute($user),
        );
    }

    /**
     * @return array<int, array{key: string, title: string, type: string, labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, int|float>}>}>
     */
    private function compute(User $user): array
    {
        $charts = [
            $this->headcountByDepartment(),
            $this->headcountByStatus(),
        ];

        if ($this->can($user, 'attendance.view')) {
            $charts[] = $this->attendanceTrend();
        }

        if ($this->can($user, 'leave.view')) {
            $charts[] = $this->leaveUsageByType();
        }

        if ($this->can($user, 'payroll.view')) {
            $charts[] = $this->payrollCostByPeriod();
        }

        if ($this->can($user, 'expenses.view')) {
            $charts[] = $this->expensesByCategory();
            $charts[] = $this->expensesByStatus();
        }

        if ($this->can($user, 'recruitment.view')) {
            $charts[] = $this->recruitmentFunnel();
        }

        return array_values(array_filter($charts, fn (array $c): bool => $c['labels'] !== []));
    }

    /**
     * @return array{key: string, title: string, type: string, labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, int|float>}>}
     */
    private function headcountByDepartment(): array
    {
        $rows = Employee::query()->active()
            ->with('department:id,name')
            ->get(['id', 'department_id'])
            ->groupBy(fn (Employee $e): string => $e->department?->name ?? 'Unassigned')
            ->map->count();

        return [
            'key' => 'headcount_by_department',
            'title' => 'Active headcount by department',
            'type' => 'doughnut',
            'labels' => $rows->keys()->all(),
            'datasets' => [['label' => 'Employees', 'data' => $rows->values()->all()]],
        ];
    }

    /**
     * @return array{key: string, title: string, type: string, labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, int|float>}>}
     */
    private function headcountByStatus(): array
    {
        $rows = Employee::query()
            ->get(['status'])
            ->groupBy(fn (Employee $e): string => $e->status->label())
            ->map->count();

        return [
            'key' => 'headcount_by_status',
            'title' => 'Employees by status',
            'type' => 'bar',
            'labels' => $rows->keys()->all(),
            'datasets' => [['label' => 'Employees', 'data' => $rows->values()->all()]],
        ];
    }

    /**
     * @return array{key: string, title: string, type: string, labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, int|float>}>}
     */
    private function attendanceTrend(): array
    {
        $from = CarbonImmutable::now()->subDays(13)->startOfDay();
        $to = CarbonImmutable::now()->endOfDay();

        $records = Attendance::query()
            ->whereDate('work_date', '>=', $from->toDateString())
            ->whereDate('work_date', '<=', $to->toDateString())
            ->get(['work_date', 'status'])
            ->groupBy(fn (Attendance $a): string => $a->work_date->toDateString());

        $labels = [];
        $present = [];
        $late = [];
        $absent = [];

        for ($d = $from; $d->lessThanOrEqualTo($to); $d = $d->addDay()) {
            $labels[] = $d->format('M j');
            $group = $records->get($d->toDateString()) ?? collect();
            $present[] = $group->where('status', AttendanceStatus::Present)->count();
            $late[] = $group->where('status', AttendanceStatus::Late)->count();
            $absent[] = $group->where('status', AttendanceStatus::Absent)->count();
        }

        return [
            'key' => 'attendance_trend',
            'title' => 'Attendance — last 14 days',
            'type' => 'line',
            'labels' => $labels,
            'datasets' => [
                ['label' => 'Present', 'data' => $present],
                ['label' => 'Late', 'data' => $late],
                ['label' => 'Absent', 'data' => $absent],
            ],
        ];
    }

    /**
     * @return array{key: string, title: string, type: string, labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, int|float>}>}
     */
    private function leaveUsageByType(): array
    {
        $year = CarbonImmutable::now()->year;

        $rows = LeaveBalance::query()
            ->where('year', $year)
            ->with('leaveType:id,name')
            ->get(['leave_type_id', 'used_days'])
            ->groupBy(fn (LeaveBalance $b): string => $b->leaveType->name ?? '—')
            ->map(fn ($group): float => (float) $group->sum('used_days'))
            ->filter(fn (float $v): bool => $v > 0);

        return [
            'key' => 'leave_usage_by_type',
            'title' => "Leave days used ({$year})",
            'type' => 'doughnut',
            'labels' => $rows->keys()->all(),
            'datasets' => [['label' => 'Days used', 'data' => $rows->values()->all()]],
        ];
    }

    /**
     * @return array{key: string, title: string, type: string, labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, int|float>}>}
     */
    private function payrollCostByPeriod(): array
    {
        $runs = PayrollRun::query()
            ->whereIn('status', ['approved', 'paid', 'locked'])
            ->with('period:id,name')
            ->latest('id')
            ->limit(6)
            ->get(['id', 'payroll_period_id', 'net_cents'])
            ->reverse()
            ->values();

        return [
            'key' => 'payroll_cost_by_period',
            'title' => 'Net payroll cost — last 6 runs',
            'type' => 'bar',
            'labels' => $runs->map(fn (PayrollRun $r): string => $r->period->name ?? '#'.$r->payroll_period_id)->all(),
            'datasets' => [['label' => 'Net paid', 'data' => $runs->map(fn (PayrollRun $r): float => round($r->net_cents / 100, 2))->all()]],
        ];
    }

    /**
     * @return array{key: string, title: string, type: string, labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, int|float>}>}
     */
    private function expensesByCategory(): array
    {
        $rows = ExpenseItem::query()
            ->get(['category', 'amount_cents'])
            ->groupBy(fn (ExpenseItem $item): string => $item->category->value)
            ->map(fn ($group): float => round((float) $group->sum('amount_cents') / 100, 2));

        return [
            'key' => 'expenses_by_category',
            'title' => 'Expense value by category',
            'type' => 'bar',
            'labels' => $rows->keys()->map(fn ($c): string => ucfirst(str_replace('_', ' ', (string) $c)))->all(),
            'datasets' => [['label' => 'Total', 'data' => $rows->values()->all()]],
        ];
    }

    /**
     * @return array{key: string, title: string, type: string, labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, int|float>}>}
     */
    private function expensesByStatus(): array
    {
        $rows = Expense::query()
            ->get(['status'])
            ->groupBy(fn (Expense $e): string => $e->status->value)
            ->map->count();

        return [
            'key' => 'expenses_by_status',
            'title' => 'Expense claims by status',
            'type' => 'doughnut',
            'labels' => $rows->keys()->map(fn ($s): string => ucfirst((string) $s))->all(),
            'datasets' => [['label' => 'Claims', 'data' => $rows->values()->all()]],
        ];
    }

    /**
     * @return array{key: string, title: string, type: string, labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, int|float>}>}
     */
    private function recruitmentFunnel(): array
    {
        $vacancies = Vacancy::query()->where('status', 'open')->get(['id', 'pipeline_stages']);

        $applications = Application::query()
            ->whereIn('vacancy_id', $vacancies->pluck('id')->all())
            ->get(['vacancy_id', 'stage']);

        $stageOrder = [];
        foreach ($vacancies as $vacancy) {
            foreach ($vacancy->stages() as $stage) {
                if (! in_array($stage, $stageOrder, true)) {
                    $stageOrder[] = $stage;
                }
            }
        }

        $counts = [];
        foreach ($stageOrder as $stage) {
            $counts[$stage] = $applications->where('stage', $stage)->count();
        }

        return [
            'key' => 'recruitment_funnel',
            'title' => 'Recruitment funnel — open vacancies',
            'type' => 'bar',
            'labels' => array_map(fn (string $s): string => ucfirst(str_replace('_', ' ', $s)), array_keys($counts)),
            'datasets' => [['label' => 'Candidates', 'data' => array_values($counts)]],
        ];
    }

    private function can(User $user, string $permission): bool
    {
        if ($user->tenant_id === null) {
            return false;
        }

        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($user->tenant_id);

        try {
            return $user->hasPermissionTo($permission);
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }
}
