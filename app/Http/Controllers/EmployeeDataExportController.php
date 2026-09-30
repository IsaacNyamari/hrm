<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Employee;
use App\Support\Auditor;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Data subject access: everything the system holds about one employee, as a
 * JSON file, for a request under the Kenya Data Protection Act, 2019. Available
 * to the employee themselves and to HR; every export is recorded.
 */
class EmployeeDataExportController extends Controller
{
    public function __invoke(Request $request, Employee $employee): StreamedResponse
    {
        $user = $request->user();
        $isSelf = $user?->employee?->getKey() === $employee->getKey();

        abort_unless($isSelf || $user?->can(Permission::EmployeesUpdate->value), 403);

        $employee->load([
            'department:id,name', 'designation:id,name', 'branch:id,name', 'employmentType:id,name',
            'emergencyContacts', 'bankAccounts', 'statutoryDetail', 'leaveRequests.leaveType:id,name',
            'leaveBalances.leaveType:id,name', 'attendances', 'payslips', 'documents', 'loans',
            'assetAssignments.asset:id,asset_tag,name', 'expenses', 'performanceReviews',
        ]);

        app(Auditor::class)->log('employee.data_exported', $employee, ['by_self' => $isSelf]);

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'notice' => 'Personal data held about this employee, exported under the Kenya Data Protection Act, 2019.',
            'profile' => $employee->only([
                'employee_number', 'first_name', 'middle_name', 'last_name', 'preferred_name', 'email', 'personal_email',
                'phone', 'alternate_phone', 'date_of_birth', 'gender', 'marital_status', 'nationality', 'job_title',
                'hire_date', 'exit_date', 'status',
            ]) + [
                'department' => $employee->department?->name,
                'designation' => $employee->designation?->name,
                'branch' => $employee->branch?->name,
                'employment_type' => $employee->employmentType?->name,
            ],
            'statutory' => $employee->statutoryDetail?->only(['national_id', 'tax_pin', 'nssf_number', 'shif_number']),
            'emergency_contacts' => $employee->emergencyContacts->map->only(['name', 'relationship', 'phone', 'is_primary'])->all(),
            'bank_accounts' => $employee->bankAccounts->map(fn ($b): array => ['bank_name' => $b->bank_name, 'account_name' => $b->account_name, 'account_number' => $b->maskedAccountNumber()])->all(),
            'leave_requests' => $employee->leaveRequests->map(fn ($l): array => ['type' => $l->leaveType?->name, 'from' => $l->start_date?->toDateString(), 'to' => $l->end_date?->toDateString(), 'days' => $l->days, 'status' => $l->status->value])->all(),
            'leave_balances' => $employee->leaveBalances->map(fn ($b): array => ['type' => $b->leaveType?->name, 'year' => $b->year, 'allocated' => $b->allocated_days, 'used' => $b->used_days])->all(),
            'attendance' => $employee->attendances->map(fn ($a): array => ['date' => $a->work_date->toDateString(), 'clock_in' => $a->clock_in_at?->toIso8601String(), 'clock_out' => $a->clock_out_at?->toIso8601String(), 'status' => $a->status->value])->all(),
            'payslips' => $employee->payslips->map(fn ($p): array => ['gross_cents' => $p->gross_earnings_cents, 'net_cents' => $p->net_pay_cents, 'status' => $p->status->value, 'issued' => $p->created_at?->toDateString()])->all(),
            'documents' => $employee->documents->map(fn ($d): array => ['title' => $d->title, 'expiry_date' => $d->expiry_date?->toDateString()])->all(),
            'loans' => $employee->loans->map(fn ($l): array => ['type' => $l->type->value, 'principal_cents' => $l->principal_cents, 'outstanding_cents' => $l->outstanding_cents, 'status' => $l->status->value])->all(),
            'assets' => $employee->assetAssignments->map(fn ($a): array => ['asset' => $a->asset?->name, 'tag' => $a->asset?->asset_tag])->all(),
            'expense_claims' => $employee->expenses->map(fn ($e): array => ['reference' => $e->reference, 'total_cents' => $e->total_cents, 'status' => $e->status->value])->all(),
        ];

        return response()->streamDownload(
            function () use ($payload): void {
                echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            },
            "personal-data-{$employee->employee_number}.json",
            ['Content-Type' => 'application/json'],
        );
    }
}
