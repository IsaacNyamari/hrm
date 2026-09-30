<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Employees\CreateEmployee;
use App\Actions\Employees\UpdateEmployee;
use App\Enums\Permission;
use App\Facades\Tenancy;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): JsonResponse|View
    {
        $this->authorize('viewAny', Employee::class);

        if (! $request->wantsJson()) {
            return view('employees.index');
        }

        $employees = Employee::query()
            ->search($request->string('search')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->with(['department:id,name', 'designation:id,name', 'branch:id,name'])
            ->orderBy('employee_number')
            ->paginate($request->integer('per_page', 25));

        return response()->json(
            $employees->through(fn (Employee $employee): array => $this->transform($employee))->toArray(),
        );
    }

    public function show(Employee $employee): JsonResponse
    {
        $this->authorize('view', $employee);

        $employee->load([
            'department', 'designation', 'branch', 'employmentType', 'manager',
            'emergencyContacts', 'bankAccounts', 'statutoryDetail',
        ]);

        return response()->json(['data' => $this->transform($employee, detailed: true)]);
    }

    public function store(StoreEmployeeRequest $request, CreateEmployee $action): JsonResponse
    {
        $employee = $action->handle(Tenancy::getOrFail(), $request->validated());

        return response()->json(['data' => $this->transform($employee)], 201);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee, UpdateEmployee $action): JsonResponse
    {
        $employee = $action->handle($employee, $request->validated());

        return response()->json(['data' => $this->transform($employee)]);
    }

    public function destroy(Employee $employee): Response
    {
        $this->authorize('delete', $employee);

        $employee->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Employee $employee, bool $detailed = false): array
    {
        $base = [
            'uuid' => $employee->uuid,
            'employee_number' => $employee->employee_number,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'full_name' => $employee->fullName(),
            'email' => $employee->email,
            'job_title' => $employee->job_title,
            'status' => $employee->status->value,
            'department' => $employee->department?->name,
            'designation' => $employee->designation?->name,
            'branch' => $employee->branch?->name,
            'hire_date' => $employee->hire_date?->toDateString(),
        ];

        if (! $detailed) {
            return $base;
        }

        // Pay is shown to people who hold the salary permission, and to the employee themselves.
        $user = request()->user();
        $canSeeSalary = $user !== null && ($user->can(Permission::EmployeesSalaryView->value) || $user->employee?->getKey() === $employee->getKey());
        $salary = $canSeeSalary
            ? ['salary_amount_cents' => $employee->salary_amount_cents, 'salary_currency' => $employee->salary_currency]
            : [];

        return $base + $salary + [
            'middle_name' => $employee->middle_name,
            'preferred_name' => $employee->preferred_name,
            'personal_email' => $employee->personal_email,
            'phone' => $employee->phone,
            'alternate_phone' => $employee->alternate_phone,
            'date_of_birth' => $employee->date_of_birth?->toDateString(),
            'gender' => $employee->gender?->value,
            'marital_status' => $employee->marital_status?->value,
            'nationality' => $employee->nationality,
            'manager' => $employee->manager?->fullName(),
            'employment_type' => $employee->employmentType?->name,
            'probation_ends_at' => $employee->probation_ends_at?->toDateString(),
            'pay_frequency' => $employee->pay_frequency,
            'emergency_contacts' => $employee->emergencyContacts->map(fn ($c): array => [
                'name' => $c->name,
                'relationship' => $c->relationship,
                'phone' => $c->phone,
                'is_primary' => $c->is_primary,
            ])->all(),
            'bank_accounts' => $employee->bankAccounts->map(fn ($b): array => [
                'bank_name' => $b->bank_name,
                'account_name' => $b->account_name,
                'account_number' => $b->maskedAccountNumber(),
                'is_primary' => $b->is_primary,
            ])->all(),
            'statutory' => $employee->statutoryDetail === null ? null : [
                'national_id' => $employee->statutoryDetail->national_id,
                'tax_pin' => $employee->statutoryDetail->tax_pin,
                'nssf_number' => $employee->statutoryDetail->nssf_number,
                'shif_number' => $employee->statutoryDetail->shif_number,
            ],
        ];
    }
}
