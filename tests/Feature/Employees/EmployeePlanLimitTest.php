<?php

declare(strict_types=1);

use App\Actions\Employees\CreateEmployee;
use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Exceptions\PlanLimitExceededException;
use App\Models\Employee;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\FeatureGate;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'limit-co']);
    $plan = Plan::factory()->create(['employee_limit' => 3]);
    Subscription::factory()->forTenant($this->tenant)->onPlan($plan)->create();
    app(FeatureGate::class)->flush($this->tenant);
});

it('blocks creation once the plan employee limit is reached', function () {
    Employee::factory()->forTenant($this->tenant)->count(3)->create();

    app(CreateEmployee::class)->handle($this->tenant, ['first_name' => 'Over', 'last_name' => 'Limit']);
})->throws(PlanLimitExceededException::class);

it('does not count terminated employees against the limit', function () {
    Employee::factory()->forTenant($this->tenant)->count(2)->create();
    Employee::factory()->forTenant($this->tenant)->terminated()->count(5)->create();

    $employee = app(CreateEmployee::class)->handle($this->tenant, ['first_name' => 'Still', 'last_name' => 'Ok']);

    expect($employee->status)->toBe(EmploymentStatus::Active);
});

it('returns a helpful error from the endpoint when the limit is hit', function () {
    Employee::factory()->forTenant($this->tenant)->count(3)->create();
    $hr = userForTenant($this->tenant, Role::HrManager);

    $this->actingAs($hr)
        ->postJson('http://limit-co.hrm.test/employees', ['first_name' => 'Nope', 'last_name' => 'Limit'])
        ->assertStatus(402)
        ->assertJsonPath('message', 'You have reached your plan limit for employees. Upgrade your plan to add more.');

    // and nothing was written
    expect(Employee::query()->where('tenant_id', $this->tenant->id)->count())->toBe(3);
});
