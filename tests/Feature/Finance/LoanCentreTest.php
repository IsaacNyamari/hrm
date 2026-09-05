<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Employee;
use App\Models\Loan;
use App\Support\TenantContext;

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
