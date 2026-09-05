<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Scopes\TenantScope;
use App\Services\Leave\LeaveService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'lt-co']);
    $this->hr = userForTenant($this->tenant, Role::HrManager);
});

it('provisions the seven default leave types', function () {
    app(TenantContext::class)->set($this->tenant);

    expect(LeaveType::query()->pluck('code')->all())
        ->toContain('ANNUAL', 'SICK', 'MATERNITY', 'PATERNITY', 'COMPASSIONATE', 'STUDY', 'UNPAID');
});

it('creates and updates a custom leave type', function () {
    $create = $this->actingAs($this->hr)
        ->postJson('http://lt-co.hrm.test/leave-types', [
            'name' => 'Sabbatical', 'code' => 'SABBATICAL', 'default_days_per_year' => 30,
            'is_paid' => false, 'requires_approval' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'SABBATICAL');

    $this->actingAs($this->hr)
        ->putJson("http://lt-co.hrm.test/leave-types/{$create->json('data.uuid')}", ['default_days_per_year' => 45])
        ->assertOk()
        ->assertJsonPath('data.default_days_per_year', 45);
});

it('forbids creating a leave type without leave.manage', function () {
    $employee = userForTenant($this->tenant, Role::Employee);

    $this->actingAs($employee)
        ->postJson('http://lt-co.hrm.test/leave-types', ['name' => 'X', 'code' => 'X'])
        ->assertForbidden();
});

it('accrues balances for every employee and leave type', function () {
    app(TenantContext::class)->set($this->tenant);
    Employee::factory()->forTenant($this->tenant)->count(3)->create();

    $touched = app(LeaveService::class)->accrue(2026);

    // 3 employees x 7 default types
    expect($touched)->toBe(21)
        ->and(LeaveBalance::query()->where('year', 2026)->count())->toBe(21);
});

it('prorates a monthly-accrual type and honours the carry-forward cap', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 4, 15)); // month 4
    app(TenantContext::class)->set($this->tenant);
    $employee = Employee::factory()->forTenant($this->tenant)->create();

    $annual = LeaveType::query()->where('code', 'ANNUAL')->firstOrFail(); // 21 days, monthly, carry cap 10
    $service = app(LeaveService::class);

    // prior-year balance with 18 unused days
    LeaveBalance::query()->create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $employee->id, 'leave_type_id' => $annual->id,
        'year' => 2025, 'allocated_days' => 21, 'used_days' => 3,
    ]);

    $service->accrue(2026);

    $balance = $service->balanceFor($employee, $annual, 2026);
    expect($balance->allocated_days)->toBe(round(21 / 12 * 4, 2)) // 7.0
        ->and($balance->carried_forward_days)->toBe(10.0);        // capped from 18

    CarbonImmutable::setTestNow();
});

it('runs the accrual console command across tenants', function () {
    app(TenantContext::class)->set($this->tenant);
    Employee::factory()->forTenant($this->tenant)->count(2)->create();
    app(TenantContext::class)->forget();

    $this->artisan('leave:accrue', ['--year' => 2026])->assertSuccessful();

    expect(LeaveBalance::query()->withoutGlobalScope(TenantScope::class)->where('year', 2026)->count())
        ->toBeGreaterThanOrEqual(14);
});
