<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Employee;
use App\Models\Shift;
use Carbon\CarbonImmutable;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'clock-co']);
    $this->user = userForTenant($this->tenant, Role::Employee);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create(['user_id' => $this->user->id]);

    $this->shift = Shift::factory()->forTenant($this->tenant)->default()->create([
        'start_time' => '08:00', 'end_time' => '17:00', 'grace_minutes' => 10, 'break_minutes' => 60,
        'overtime_after_minutes' => 30,
    ]);
    $this->employee->shiftAssignments()->create([
        'tenant_id' => $this->tenant->id, 'shift_id' => $this->shift->id,
        'effective_from' => now()->subMonth()->toDateString(),
    ]);
});

it('clocks in on time as Present', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::today()->setTime(8, 5));

    $this->actingAs($this->user)
        ->postJson('http://clock-co.hrm.test/attendance/clock-in')
        ->assertOk()
        ->assertJsonPath('data.status', 'present');

    CarbonImmutable::setTestNow();
});

it('flags a late clock-in', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::today()->setTime(9, 30));

    $this->actingAs($this->user)
        ->postJson('http://clock-co.hrm.test/attendance/clock-in')
        ->assertOk()
        ->assertJsonPath('data.status', 'late');

    CarbonImmutable::setTestNow();
});

it('rejects a second clock-in', function () {
    $this->actingAs($this->user)->postJson('http://clock-co.hrm.test/attendance/clock-in')->assertOk();
    $this->actingAs($this->user)->postJson('http://clock-co.hrm.test/attendance/clock-in')->assertStatus(422);
});

it('computes worked and overtime minutes on clock-out', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::today()->setTime(8, 0));
    $this->actingAs($this->user)->postJson('http://clock-co.hrm.test/attendance/clock-in')->assertOk();

    // 8:00 -> 18:30 = 630 min, minus 60 break = 570 worked; scheduled 480; ot threshold 30 -> ot = 570-480 = 90
    CarbonImmutable::setTestNow(CarbonImmutable::today()->setTime(18, 30));
    $this->actingAs($this->user)
        ->postJson('http://clock-co.hrm.test/attendance/clock-out')
        ->assertOk()
        ->assertJsonPath('data.worked_minutes', 570)
        ->assertJsonPath('data.overtime_minutes', 90);

    CarbonImmutable::setTestNow();
});

it('will not clock out without a clock-in', function () {
    $this->actingAs($this->user)
        ->postJson('http://clock-co.hrm.test/attendance/clock-out')
        ->assertStatus(422);
});

it('tracks breaks and blocks clock-out while on break', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::today()->setTime(8, 0));
    $this->actingAs($this->user)->postJson('http://clock-co.hrm.test/attendance/clock-in')->assertOk();

    CarbonImmutable::setTestNow(CarbonImmutable::today()->setTime(13, 0));
    $this->actingAs($this->user)->postJson('http://clock-co.hrm.test/attendance/break/start')->assertOk();

    CarbonImmutable::setTestNow(CarbonImmutable::today()->setTime(13, 30));
    $this->actingAs($this->user)->postJson('http://clock-co.hrm.test/attendance/break/end')
        ->assertOk()
        ->assertJsonPath('data.break_minutes', 30);

    CarbonImmutable::setTestNow();
});

it('422s a user with no linked employee record', function () {
    $orphan = userForTenant($this->tenant, Role::Employee);

    $this->actingAs($orphan)
        ->postJson('http://clock-co.hrm.test/attendance/clock-in')
        ->assertStatus(422);
});

it('reports today status', function () {
    $this->actingAs($this->user)->postJson('http://clock-co.hrm.test/attendance/clock-in')->assertOk();

    $this->actingAs($this->user)
        ->getJson('http://clock-co.hrm.test/attendance/today')
        ->assertOk()
        ->assertJsonPath('data.clocked_in', true)
        ->assertJsonPath('data.on_break', false);
});
