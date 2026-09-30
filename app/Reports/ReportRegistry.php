<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

class ReportRegistry
{
    /** @var array<int, class-string<Report>> */
    private const REPORTS = [
        HeadcountReport::class,
        AttendanceSummaryReport::class,
        LeaveLedgerReport::class,
        PayrollSummaryReport::class,
        PayrollRegisterReport::class,
        PayrollVarianceReport::class,
        P9Report::class,
        LeaveLiabilityReport::class,
        HeadcountMovementReport::class,
        ExpenseClaimsReport::class,
        RecruitmentFunnelReport::class,
    ];

    /**
     * @return array<string, Report>
     */
    public function all(): array
    {
        $out = [];

        foreach (self::REPORTS as $class) {
            $report = new $class;
            $out[$report->key()] = $report;
        }

        return $out;
    }

    public function find(string $key): ?Report
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * Reports the user is allowed to run, given `reports.view` plus each
     * report's own domain permissions, evaluated in the user's tenant scope.
     *
     * @return array<string, Report>
     */
    public function availableFor(User $user): array
    {
        if (! $this->userHolds($user, 'reports.view')) {
            return [];
        }

        return array_filter($this->all(), fn (Report $report): bool => $this->canRun($user, $report));
    }

    public function canRun(User $user, Report $report): bool
    {
        if (! $this->userHolds($user, 'reports.view')) {
            return false;
        }

        foreach ($report->permissions() as $permission) {
            if (! $this->userHolds($user, $permission)) {
                return false;
            }
        }

        return true;
    }

    private function userHolds(User $user, string $permission): bool
    {
        $tenantId = $user->tenant_id;

        if ($tenantId === null) {
            return false;
        }

        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($tenantId);

        try {
            return $user->hasPermissionTo($permission);
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }
}
