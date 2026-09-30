<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The full tenant-side permission catalogue. Permission rows are global
 * (guard "web"); roles that reference them are tenant-scoped (Spatie "teams",
 * keyed by tenant_id).
 */
enum Permission: string
{
    // People
    case EmployeesView = 'employees.view';
    case EmployeesCreate = 'employees.create';
    case EmployeesUpdate = 'employees.update';
    case EmployeesDelete = 'employees.delete';
    case EmployeesSalaryView = 'employees.salary.view';
    case EmployeesDocumentsView = 'employees.documents.view';
    case EmployeesDocumentsUpload = 'employees.documents.upload';

    // Org
    case DepartmentsManage = 'departments.manage';
    case DesignationsManage = 'designations.manage';
    case BranchesManage = 'branches.manage';

    // Attendance
    case AttendanceView = 'attendance.view';
    case AttendanceManage = 'attendance.manage';
    case ShiftsManage = 'shifts.manage';

    // Leave
    case LeaveView = 'leave.view';
    case LeaveRequest = 'leave.request';
    case LeaveApprove = 'leave.approve';
    case LeaveManage = 'leave.manage';

    // Payroll
    case PayrollView = 'payroll.view';
    case PayrollManage = 'payroll.manage';
    case PayrollProcess = 'payroll.process';
    case PayrollApprove = 'payroll.approve';

    // Performance
    case PerformanceView = 'performance.view';
    case PerformanceManage = 'performance.manage';
    case PerformanceReview = 'performance.review';

    // Recruitment
    case RecruitmentView = 'recruitment.view';
    case RecruitmentManage = 'recruitment.manage';
    case RecruitmentInterview = 'recruitment.interview';

    // Expenses
    case ExpensesView = 'expenses.view';
    case ExpensesApprove = 'expenses.approve';
    case ExpensesManage = 'expenses.manage';

    // Assets
    case AssetsView = 'assets.view';
    case AssetsManage = 'assets.manage';

    // Tasks
    case TasksView = 'tasks.view';
    case TasksAssign = 'tasks.assign';
    case TasksManage = 'tasks.manage';

    // Developer / integrations
    case WebhooksManage = 'webhooks.manage';
    case ApiTokensManage = 'api.tokens.manage';

    // Reports
    case ReportsView = 'reports.view';
    case ReportsExport = 'reports.export';

    // Administration
    case SettingsManage = 'settings.manage';
    case UsersManage = 'users.manage';
    case RolesManage = 'roles.manage';
    case BillingView = 'billing.view';
    case SubscriptionManage = 'subscription.manage';
    case AuditView = 'audit.view';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $p): string => $p->value, self::cases());
    }
}
