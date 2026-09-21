<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Services\Payroll\PayrollService;
use App\Support\TenantContext;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'pay-co']);
    app(TenantContext::class)->set($this->tenant);

    $this->payrollManager = userForTenant($this->tenant, Role::PayrollManager);
    $this->financeManager = userForTenant($this->tenant, Role::FinanceManager); // payroll.approve
    $this->employeeUser = userForTenant($this->tenant, Role::Employee);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create([
        'user_id' => $this->employeeUser->id, 'salary_amount_cents' => 8_000_000,
    ]);
});

it('creates a period and processes a run', function () {
    $period = $this->actingAs($this->payrollManager)
        ->postJson('http://pay-co.hrm.test/payroll-periods', [
            'name' => 'September 2026', 'frequency' => 'monthly',
            'start_date' => '2026-09-01', 'end_date' => '2026-09-30', 'pay_date' => '2026-09-30',
        ])
        ->assertCreated();

    $uuid = $period->json('data.uuid');

    $this->actingAs($this->payrollManager)
        ->postJson("http://pay-co.hrm.test/payroll-periods/{$uuid}/run")
        ->assertCreated()
        ->assertJsonPath('data.status', 'processed')
        ->assertJsonPath('data.employee_count', 1);
});

it('rejects a second regular period for the same dates', function () {
    PayrollPeriod::factory()->forTenant($this->tenant)->forMonth(2026, 9)->create();

    $this->actingAs($this->payrollManager)
        ->postJson('http://pay-co.hrm.test/payroll-periods', [
            'name' => 'September again', 'frequency' => 'monthly',
            'start_date' => '2026-09-01', 'end_date' => '2026-09-30', 'pay_date' => '2026-09-30',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('start_date');
});

it('gates processing and approval by permission', function () {
    $period = PayrollPeriod::factory()->forTenant($this->tenant)->forMonth(2026, 9)->create();

    // an employee cannot process
    $this->actingAs($this->employeeUser)
        ->postJson("http://pay-co.hrm.test/payroll-periods/{$period->uuid}/run")
        ->assertForbidden();

    $run = app(PayrollService::class)->run($period);

    // payroll manager can process but NOT approve (no payroll.approve)
    $this->actingAs($this->payrollManager)
        ->postJson("http://pay-co.hrm.test/payroll-runs/{$run->uuid}/approve")
        ->assertForbidden();

    // finance manager has payroll.approve
    $this->actingAs($this->financeManager)
        ->postJson("http://pay-co.hrm.test/payroll-runs/{$run->uuid}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');
});

it('lets an employee see only their own payslip and download the PDF', function () {
    $period = PayrollPeriod::factory()->forTenant($this->tenant)->forMonth(2026, 9)->create();
    app(PayrollService::class)->run($period);

    $other = Employee::factory()->forTenant($this->tenant)->create(['salary_amount_cents' => 9_000_000]);
    app(PayrollService::class)->run($period->fresh()); // re-run to include the new employee

    $list = $this->actingAs($this->employeeUser)
        ->getJson('http://pay-co.hrm.test/payslips')
        ->assertOk();

    expect($list->json('data'))->toHaveCount(1);

    $payslipUuid = $list->json('data.0.uuid');
    $document = $this->actingAs($this->employeeUser)->get("http://pay-co.hrm.test/payslips/{$payslipUuid}/pdf");
    $document->assertOk();
    expect($document->headers->get('content-type'))->toMatch('#application/pdf|text/html#');
});

it('validates a salary component formula on save', function () {
    $this->actingAs($this->payrollManager)
        ->postJson('http://pay-co.hrm.test/salary-components', [
            'name' => 'Broken', 'code' => 'BROKEN', 'type' => 'earning',
            'calculation_method' => 'formula', 'formula' => 'basic_salary * unknown_var',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('formula');
});

it('assigns a salary structure to an employee', function () {
    $this->actingAs($this->payrollManager)
        ->putJson("http://pay-co.hrm.test/employees/{$this->employee->uuid}/salary", [
            'base_salary_cents' => 12_000_000,
            'components' => [
                ['code' => 'HOUSE_ALLOWANCE'],
                ['code' => 'TRANSPORT_ALLOWANCE', 'amount_cents' => 1_000_000],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.base_salary_cents', 12_000_000);

    expect($this->employee->fresh()->salaryComponents()->count())->toBe(2);
});

it('cannot process another tenant\'s payroll period', function () {
    $other = tenantWithRoles(['slug' => 'rival-pay-co']);
    $period = PayrollPeriod::factory()->forTenant($other)->forMonth(2026, 9)->create();

    $this->actingAs($this->payrollManager)
        ->postJson("http://pay-co.hrm.test/payroll-periods/{$period->uuid}/run")
        ->assertNotFound();
});
