<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Employee;
use App\Support\TenantContext;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'geo-co', 'settings' => ['attendance' => ['geofence_required' => true]]]);
    $this->user = userForTenant($this->tenant, Role::Employee);

    // Office at the KICC, Nairobi. 100m radius.
    $this->branch = Branch::factory()->forTenant($this->tenant)->create([
        'latitude' => -1.288610, 'longitude' => 36.823380, 'geofence_radius_meters' => 100,
    ]);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create([
        'user_id' => $this->user->id, 'branch_id' => $this->branch->id,
    ]);
});

it('allows a clock-in from inside the geofence', function () {
    $this->actingAs($this->user)
        ->postJson('http://geo-co.hrm.test/attendance/clock-in', ['latitude' => -1.288650, 'longitude' => 36.823400])
        ->assertOk();
});

it('rejects a clock-in from outside the geofence', function () {
    // ~2 km away
    $this->actingAs($this->user)
        ->postJson('http://geo-co.hrm.test/attendance/clock-in', ['latitude' => -1.300000, 'longitude' => 36.810000])
        ->assertStatus(422)
        ->assertJsonPath('message', 'You are outside the allowed clock-in area.');
});

it('requires coordinates when geofencing is on', function () {
    $this->actingAs($this->user)
        ->postJson('http://geo-co.hrm.test/attendance/clock-in')
        ->assertStatus(422)
        ->assertJsonPath('message', 'Location is required to clock in or out here.');
});

it('ignores location when geofencing is off', function () {
    $this->tenant->update(['settings' => ['attendance' => ['geofence_required' => false]]]);
    app(TenantContext::class)->set($this->tenant->fresh());

    $this->actingAs($this->user)
        ->postJson('http://geo-co.hrm.test/attendance/clock-in')
        ->assertOk();
});
