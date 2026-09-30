<?php

declare(strict_types=1);

use App\Enums\LoanStatus;
use App\Enums\Role;
use App\Livewire\Finance\Loans;
use App\Models\Employee;
use App\Models\Loan;
use App\Support\TenantContext;
use Livewire\Livewire;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'loan-co', 'name' => 'Loan Co']);
    app(TenantContext::class)->set($this->tenant);

    $this->payroll = userForTenant($this->tenant, Role::PayrollManager);
    $this->staffUser = userForTenant($this->tenant, Role::Employee);
    $this->staff = Employee::factory()->forTenant($this->tenant)->create(['user_id' => $this->staffUser->id]);
});

it('lets an employee request a salary advance for themselves', function () {
    $this->actingAs($this->staffUser)
        ->postJson('http://loan-co.hrm.test/loans', [
            'type' => 'salary_advance', 'principal_cents' => 300000, 'installments' => 3, 'start_date' => now()->toDateString(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending');

    expect(Loan::query()->where('employee_id', $this->staff->id)->count())->toBe(1);
});

it('stops an employee from raising a loan for someone else', function () {
    $other = Employee::factory()->forTenant($this->tenant)->create();

    $this->actingAs($this->staffUser)
        ->postJson('http://loan-co.hrm.test/loans', [
            'employee_id' => $other->id, 'type' => 'loan', 'principal_cents' => 100000, 'installments' => 2, 'start_date' => now()->toDateString(),
        ])
        ->assertForbidden();
});

it('returns an amortisation schedule that clears the balance', function () {
    $loan = Loan::factory()->create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->staff->id,
        'principal_cents' => 300000, 'interest_rate' => 0, 'installments' => 3,
        'installment_cents' => 100000, 'outstanding_cents' => 300000,
    ]);

    $response = $this->actingAs($this->payroll)
        ->getJson("http://loan-co.hrm.test/loans/{$loan->uuid}/schedule")
        ->assertOk();

    $rows = $response->json('data.schedule');
    expect($rows)->toHaveCount(3)
        ->and(array_sum(array_column($rows, 'amount_cents')))->toBe(300000)
        ->and($rows[2]['balance_cents'])->toBe(0);
});

it('lets the requester cancel a pending loan', function () {
    $loan = Loan::factory()->create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->staff->id, 'status' => 'pending',
    ]);

    $this->actingAs($this->staffUser)
        ->postJson("http://loan-co.hrm.test/loans/{$loan->uuid}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

it('gates loan approval behind the payroll permission', function () {
    $loan = Loan::factory()->create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->staff->id, 'status' => 'pending',
    ]);

    $this->actingAs($this->staffUser)
        ->postJson("http://loan-co.hrm.test/loans/{$loan->uuid}/approve")
        ->assertForbidden();

    $this->actingAs($this->payroll)
        ->postJson("http://loan-co.hrm.test/loans/{$loan->uuid}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');
});

it('lets a payroll manager approve a pending loan from the dashboard', function () {
    $loan = Loan::factory()->create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->staff->id, 'status' => 'pending',
    ]);

    $this->actingAs($this->payroll);

    Livewire::test(Loans::class)
        ->call('approve', $loan->uuid)
        ->assertDispatched('toast-show');

    expect($loan->fresh()->status)->toBe(LoanStatus::Active);
});

it('blocks an employee from approving a loan through the dashboard', function () {
    $loan = Loan::factory()->create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->staff->id, 'status' => 'pending',
    ]);

    $this->actingAs($this->staffUser);

    Livewire::test(Loans::class)
        ->call('approve', $loan->uuid)
        ->assertForbidden();
});

it('lets the requester cancel their own pending loan from the dashboard', function () {
    $loan = Loan::factory()->create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->staff->id, 'status' => 'pending',
    ]);

    $this->actingAs($this->staffUser);

    Livewire::test(Loans::class)->call('cancel', $loan->uuid);

    expect($loan->fresh()->status)->toBe(LoanStatus::Cancelled);
});

it('lets a payroll manager delete a pending loan with no repayments from the dashboard', function () {
    $loan = Loan::factory()->create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->staff->id, 'status' => 'pending',
        'principal_cents' => 100000, 'outstanding_cents' => 100000,
    ]);

    $this->actingAs($this->payroll);

    Livewire::test(Loans::class)->call('remove', $loan->uuid);

    expect(Loan::query()->find($loan->id))->toBeNull();
});

it('lets a payroll manager raise a loan on behalf of an employee from the dashboard', function () {
    $this->actingAs($this->payroll);

    Livewire::test(Loans::class)
        ->set('employeeId', (string) $this->staff->id)
        ->set('type', 'loan')
        ->set('amount', '500')
        ->set('installments', '2')
        ->call('request')
        ->assertDispatched('toast-show');

    expect(Loan::query()->where('employee_id', $this->staff->id)->count())->toBe(1);
});

it('never lets someone approve their own loan, even with payroll approval rights', function () {
    $managerEmployee = Employee::factory()->forTenant($this->tenant)->create(['user_id' => $this->payroll->id]);
    $loan = Loan::factory()->create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $managerEmployee->id, 'status' => 'pending',
    ]);

    $this->actingAs($this->payroll)
        ->postJson("http://loan-co.hrm.test/loans/{$loan->uuid}/approve")
        ->assertForbidden();

    Livewire::test(Loans::class)->call('approve', $loan->uuid)->assertForbidden();

    expect($loan->fresh()->status)->toBe(LoanStatus::Pending);
});
