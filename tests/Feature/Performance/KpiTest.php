<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Employee;
use App\Models\Kpi;
use App\Support\TenantContext;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'kpi-co', 'name' => 'KPI Co']);
    app(TenantContext::class)->set($this->tenant);

    $this->employeeUser = userForTenant($this->tenant, Role::Employee);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create(['user_id' => $this->employeeUser->id]);
});

it('lets an employee create and record against their own KPI', function () {
    $uuid = $this->actingAs($this->employeeUser)
        ->postJson('http://kpi-co.hrm.test/kpis', [
            'name' => 'Tickets resolved', 'unit' => 'count',
            'target_value' => 200, 'current_value' => 0, 'weight' => 25,
        ])
        ->assertCreated()
        ->json('data.uuid');

    $this->actingAs($this->employeeUser)
        ->postJson("http://kpi-co.hrm.test/kpis/{$uuid}/record", ['value' => 150])
        ->assertOk();

    $kpi = Kpi::query()->where('uuid', $uuid)->firstOrFail();
    expect($kpi->current_value)->toBe(150.0)
        ->and($kpi->attainment())->toBe(0.75)
        ->and($kpi->last_recorded_at)->not->toBeNull();
});

it('caps attainment at 1.0 once the target is beaten', function () {
    $kpi = Kpi::factory()->forEmployee($this->employee)->create(['target_value' => 100, 'current_value' => 130]);

    expect($kpi->attainment())->toBe(1.0);
});

it('does not let an employee record against a colleague\'s KPI', function () {
    $other = Employee::factory()->forTenant($this->tenant)->create();
    $kpi = Kpi::factory()->forEmployee($other)->create();

    $this->actingAs($this->employeeUser)
        ->postJson("http://kpi-co.hrm.test/kpis/{$kpi->uuid}/record", ['value' => 5])
        ->assertForbidden();
});
