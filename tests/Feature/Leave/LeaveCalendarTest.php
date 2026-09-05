<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Support\TenantContext;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'cal-co', 'name' => 'Cal Co']);
    app(TenantContext::class)->set($this->tenant);
    $this->hr = userForTenant($this->tenant, Role::HrManager);
});

it('returns approved leave and active holidays within the window', function () {
    $employee = Employee::factory()->forTenant($this->tenant)->create(['first_name' => 'Cal']);

    LeaveRequest::factory()->forEmployee($employee)->approved()->create([
        'tenant_id' => $this->tenant->id,
        'leave_type_id' => LeaveType::query()->firstOrFail()->id,
        'start_date' => now()->startOfMonth()->addDays(3)->toDateString(),
        'end_date' => now()->startOfMonth()->addDays(5)->toDateString(),
    ]);
    Holiday::factory()->forTenant($this->tenant)->create([
        'name' => 'Founders Day',
        'date' => now()->startOfMonth()->addDays(10)->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->hr)
        ->getJson('http://cal-co.hrm.test/leave-calendar?from='.now()->startOfMonth()->toDateString().'&to='.now()->endOfMonth()->toDateString())
        ->assertOk();

    $types = collect($response->json('data'))->pluck('type');
    expect($types)->toContain('leave')->toContain('holiday');
});

it('excludes another tenant\'s leave from the calendar', function () {
    $other = tenantWithRoles(['slug' => 'rival-cal-co']);
    $otherEmployee = Employee::factory()->forTenant($other)->create();
    LeaveRequest::factory()->forEmployee($otherEmployee)->approved()->create([
        'tenant_id' => $other->id,
        'leave_type_id' => LeaveType::query()->withoutTenantScope()->where('tenant_id', $other->id)->firstOrFail()->id,
        'start_date' => now()->startOfMonth()->addDays(2)->toDateString(),
        'end_date' => now()->startOfMonth()->addDays(4)->toDateString(),
    ]);

    $response = $this->actingAs($this->hr)
        ->getJson('http://cal-co.hrm.test/leave-calendar')
        ->assertOk();

    expect(collect($response->json('data'))->where('type', 'leave'))->toBeEmpty();
});

it('is reachable by any authenticated tenant user', function () {
    $staff = userForTenant($this->tenant, Role::Employee);

    $this->actingAs($staff)->getJson('http://cal-co.hrm.test/leave-calendar')->assertOk();
});

it('requires authentication', function () {
    $this->getJson('http://cal-co.hrm.test/leave-calendar')->assertUnauthorized();
});
