<?php

declare(strict_types=1);

use App\Enums\EmploymentStatus;
use App\Enums\ExpenseCategory;
use App\Enums\Role;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\Vacancy;
use App\Services\Reporting\ReportCharts;
use App\Support\TenantContext;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'charts-co']);
    app(TenantContext::class)->set($this->tenant);
});

it('returns no charts for a user without reports.view', function () {
    $employee = userForTenant($this->tenant, Role::Employee);

    expect(app(ReportCharts::class)->for($employee))->toBe([]);
});

it('gates each chart behind its own domain permission', function () {
    // Data exists for every domain, but PayrollManager only holds
    // reports.view + payroll.view + expenses.view — not attendance/leave/recruitment.
    Employee::factory()->forTenant($this->tenant)->create(['status' => EmploymentStatus::Active]);
    Vacancy::factory()->forTenant($this->tenant)->create(['status' => 'open']);

    $payrollManager = userForTenant($this->tenant, Role::PayrollManager);

    $charts = app(ReportCharts::class)->for($payrollManager);
    $keys = collect($charts)->pluck('key')->all();

    expect($keys)->toContain('headcount_by_department')
        ->toContain('headcount_by_status')
        ->not->toContain('attendance_trend')
        ->not->toContain('leave_usage_by_type')
        ->not->toContain('recruitment_funnel');
});

it('gives the tenant owner every chart that has data', function () {
    Employee::factory()->forTenant($this->tenant)->create(['status' => EmploymentStatus::Active]);
    Vacancy::factory()->forTenant($this->tenant)->create(['status' => 'open']);

    $owner = userForTenant($this->tenant, Role::TenantOwner);

    $charts = app(ReportCharts::class)->for($owner);
    $keys = collect($charts)->pluck('key')->all();

    expect($keys)->toContain('headcount_by_department')
        ->toContain('headcount_by_status');
});

it('computes headcount by department correctly, excluding inactive employees', function () {
    $engineering = Department::factory()->forTenant($this->tenant)->create(['name' => 'Engineering']);
    $sales = Department::factory()->forTenant($this->tenant)->create(['name' => 'Sales']);

    Employee::factory()->forTenant($this->tenant)->count(2)->create(['department_id' => $engineering->id, 'status' => EmploymentStatus::Active]);
    Employee::factory()->forTenant($this->tenant)->create(['department_id' => $sales->id, 'status' => EmploymentStatus::Active]);
    Employee::factory()->forTenant($this->tenant)->create(['department_id' => $sales->id, 'status' => EmploymentStatus::Terminated]);

    $owner = userForTenant($this->tenant, Role::TenantOwner);

    $chart = collect(app(ReportCharts::class)->for($owner))->firstWhere('key', 'headcount_by_department');

    expect($chart)->not->toBeNull();
    $byLabel = array_combine($chart['labels'], $chart['datasets'][0]['data']);
    expect($byLabel['Engineering'])->toBe(2)
        ->and($byLabel['Sales'])->toBe(1);
});

it('computes expense value by category from expense line items, not the claim itself', function () {
    $employee = Employee::factory()->forTenant($this->tenant)->create();
    $expense = Expense::factory()->forEmployee($employee)->create();

    ExpenseItem::factory()->forExpense($expense)->create(['category' => ExpenseCategory::Meals, 'amount_cents' => 1000]);
    ExpenseItem::factory()->forExpense($expense)->create(['category' => ExpenseCategory::Meals, 'amount_cents' => 500]);
    ExpenseItem::factory()->forExpense($expense)->create(['category' => ExpenseCategory::Travel, 'amount_cents' => 2000]);

    $owner = userForTenant($this->tenant, Role::TenantOwner);

    $chart = collect(app(ReportCharts::class)->for($owner))->firstWhere('key', 'expenses_by_category');

    expect($chart)->not->toBeNull();
    $byLabel = array_combine($chart['labels'], $chart['datasets'][0]['data']);
    expect($byLabel['Meals'])->toBe(15.0)
        ->and($byLabel['Travel'])->toBe(20.0);
});
