<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Permission as P;

/**
 * Named roles. Platform roles live in the null team; tenant roles are created
 * per tenant at provisioning time. Authorization checks always resolve to
 * permissions — role names are for assignment and display only.
 */
enum Role: string
{
    // Platform
    case SuperAdmin = 'Super Admin';
    case PlatformAdmin = 'Platform Admin';
    case SupportAdmin = 'Support Admin';
    case FinanceAdmin = 'Finance Admin';

    // Tenant
    case TenantOwner = 'Tenant Owner';
    case HrAdministrator = 'HR Administrator';
    case HrManager = 'HR Manager';
    case PayrollManager = 'Payroll Manager';
    case FinanceManager = 'Finance Manager';
    case DepartmentManager = 'Department Manager';
    case Supervisor = 'Supervisor';
    case Employee = 'Employee';

    public function isPlatform(): bool
    {
        return in_array($this, [
            self::SuperAdmin, self::PlatformAdmin, self::SupportAdmin, self::FinanceAdmin,
        ], true);
    }

    /**
     * @return array<int, self>
     */
    public static function tenantRoles(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $r): bool => ! $r->isPlatform()));
    }

    /**
     * Default permission set for a tenant role. `['*']` means every tenant
     * permission.
     *
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::TenantOwner, self::HrAdministrator => P::values(),

            self::HrManager => [
                P::EmployeesView->value, P::EmployeesCreate->value, P::EmployeesUpdate->value,
                P::EmployeesDocumentsView->value, P::EmployeesDocumentsUpload->value,
                P::DepartmentsManage->value, P::DesignationsManage->value, P::BranchesManage->value,
                P::AttendanceView->value, P::AttendanceManage->value, P::ShiftsManage->value,
                P::LeaveView->value, P::LeaveApprove->value, P::LeaveManage->value,
                P::ReportsView->value, P::ReportsExport->value,
            ],

            self::PayrollManager => [
                P::EmployeesView->value,
                P::PayrollView->value, P::PayrollManage->value, P::PayrollProcess->value,
                P::ReportsView->value, P::ReportsExport->value,
            ],

            self::FinanceManager => [
                P::PayrollView->value, P::PayrollApprove->value,
                P::BillingView->value, P::ReportsView->value, P::ReportsExport->value,
            ],

            self::DepartmentManager => [
                P::EmployeesView->value,
                P::AttendanceView->value, P::AttendanceManage->value,
                P::LeaveView->value, P::LeaveApprove->value,
                P::ReportsView->value,
            ],

            self::Supervisor => [
                P::EmployeesView->value,
                P::AttendanceView->value,
                P::LeaveView->value, P::LeaveApprove->value,
            ],

            self::Employee => [
                P::LeaveRequest->value, P::LeaveView->value,
            ],

            default => [],
        };
    }
}
