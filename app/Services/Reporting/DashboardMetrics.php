<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Models\Application;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\HrRequest;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\User;
use App\Models\Vacancy;
use App\Support\TenantCache;
use Carbon\CarbonImmutable;
use Spatie\Permission\PermissionRegistrar;

/**
 * Headline figures for the operations dashboard. Everything is read through
 * the bound tenant scope; only the cards the viewer is permitted to see are
 * returned.
 */
class DashboardMetrics
{
    /**
     * @return array<int, array{key: string, label: string, value: int|string, href?: string}>
     */
    public function for(User $user): array
    {
        return TenantCache::remember(
            'dashboard.metrics:'.$user->getKey(),
            30,
            fn (): array => $this->compute($user),
        );
    }

    /**
     * @return array<int, array{key: string, label: string, value: int|string, href?: string}>
     */
    private function compute(User $user): array
    {
        $today = CarbonImmutable::now()->toDateString();
        $cards = [];

        $cards[] = ['key' => 'headcount', 'label' => 'Active employees', 'value' => Employee::query()->active()->count()];

        if ($this->can($user, 'attendance.view')) {
            $cards[] = [
                'key' => 'present_today',
                'label' => 'Clocked in today',
                'value' => Attendance::query()->whereDate('work_date', $today)->whereNotNull('clock_in_at')->count(),
            ];
        }

        if ($this->can($user, 'leave.approve') || $this->can($user, 'leave.manage')) {
            $cards[] = [
                'key' => 'leave_pending',
                'label' => 'Leave awaiting approval',
                'value' => LeaveRequest::query()->where('status', 'pending')->count(),
                'href' => '/leave',
            ];
        }

        if ($this->can($user, 'expenses.approve') || $this->can($user, 'expenses.manage')) {
            $cards[] = [
                'key' => 'expenses_pending',
                'label' => 'Expense claims to review',
                'value' => Expense::query()->where('status', 'submitted')->count(),
                'href' => '/expenses',
            ];
        }

        if ($this->can($user, 'payroll.view')) {
            $cards[] = [
                'key' => 'payroll_open',
                'label' => 'Payroll runs in progress',
                'value' => PayrollRun::query()->whereIn('status', ['draft', 'processed', 'approved'])->count(),
                'href' => '/payroll',
            ];
        }

        if ($this->can($user, 'recruitment.view')) {
            $cards[] = [
                'key' => 'vacancies_open',
                'label' => 'Open vacancies',
                'value' => Vacancy::query()->where('status', 'open')->count(),
                'href' => '/recruitment',
            ];
            $cards[] = [
                'key' => 'applications_active',
                'label' => 'Active applications',
                'value' => Application::query()->where('status', 'active')->count(),
            ];
        }

        if ($this->can($user, 'employees.view')) {
            $cards[] = [
                'key' => 'hr_requests_open',
                'label' => 'Open HR requests',
                'value' => HrRequest::query()->whereIn('status', ['open', 'assigned', 'in_progress'])->count(),
                'href' => '/hr-requests',
            ];
        }

        return $cards;
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
