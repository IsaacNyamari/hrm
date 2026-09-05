<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Tenant-scoped employee export. The query inherits App\Models\Scopes\TenantScope
 * from the bound context, so it can only ever emit the current tenant's people.
 */
class EmployeesExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(private readonly ?string $search = null) {}

    /**
     * @return Builder<Employee>
     */
    public function query(): Builder
    {
        return Employee::query()
            ->with(['department', 'designation', 'branch', 'employmentType', 'manager'])
            ->search($this->search)
            ->orderBy('employee_number');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Employee Number', 'First Name', 'Middle Name', 'Last Name', 'Preferred Name',
            'Work Email', 'Personal Email', 'Phone', 'Date of Birth', 'Gender', 'Marital Status',
            'Nationality', 'Job Title', 'Department', 'Designation', 'Branch', 'Employment Type',
            'Manager', 'Hire Date', 'Status', 'Salary', 'Currency', 'Pay Frequency',
        ];
    }

    /**
     * @param  Employee  $employee
     * @return array<int, string|null>
     */
    public function map($employee): array
    {
        return [
            $employee->employee_number,
            $employee->first_name,
            $employee->middle_name,
            $employee->last_name,
            $employee->preferred_name,
            $employee->email,
            $employee->personal_email,
            $employee->phone,
            $employee->date_of_birth?->toDateString(),
            $employee->gender?->value,
            $employee->marital_status?->value,
            $employee->nationality,
            $employee->job_title,
            $employee->department?->name,
            $employee->designation?->name,
            $employee->branch?->name,
            $employee->employmentType?->name,
            $employee->manager?->fullName(),
            $employee->hire_date?->toDateString(),
            $employee->status->value,
            $employee->salary_amount_cents !== null ? number_format($employee->salary_amount_cents / 100, 2, '.', '') : null,
            $employee->salary_currency,
            $employee->pay_frequency,
        ];
    }
}
