<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Shift;
use App\Services\Attendance\AttendanceService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'shift-co']);
    $this->hr = userForTenant($this->tenant, Role::HrManager); // has shifts.manage + attendance.manage
});

it('creates a shift and derives night-shift flag', function () {
    $this->actingAs($this->hr)
        ->postJson('http://shift-co.hrm.test/shifts', [
            'name' => 'Night', 'start_time' => '20:00', 'end_time' => '05:00',
            'working_days' => [1, 2, 3, 4, 5], 'break_minutes' => 30,
        ])
        ->assertCreated()
        ->assertJsonPath('data.is_night_shift', true)
        ->assertJsonPath('data.scheduled_minutes', 510);
});

it('assigns a shift and closes the previous assignment', function () {
    $employee = Employee::factory()->forTenant($this->tenant)->create();
    $shiftA = Shift::factory()->forTenant($this->tenant)->create(['name' => 'A']);
    $shiftB = Shift::factory()->forTenant($this->tenant)->create(['name' => 'B']);

    $this->actingAs($this->hr)->postJson("http://shift-co.hrm.test/employees/{$employee->uuid}/shift", [
        'shift_id' => $shiftA->id, 'effective_from' => now()->subMonth()->toDateString(),
    ])->assertCreated();

    $this->actingAs($this->hr)->postJson("http://shift-co.hrm.test/employees/{$employee->uuid}/shift", [
        'shift_id' => $shiftB->id, 'effective_from' => now()->toDateString(),
    ])->assertCreated();

    expect($employee->shiftAssignments()->whereNull('effective_to')->count())->toBe(1)
        ->and($employee->shiftAssignments()->whereNull('effective_to')->first()->shift_id)->toBe($shiftB->id);
});

it('resolveShift prefers the assignment then falls back to the default', function () {
    app(TenantContext::class)->set($this->tenant);
    $service = app(AttendanceService::class);

    $default = Shift::factory()->forTenant($this->tenant)->default()->create(['name' => 'Default']);
    $assigned = Shift::factory()->forTenant($this->tenant)->create(['name' => 'Assigned']);

    $withAssignment = Employee::factory()->forTenant($this->tenant)->create();
    $withAssignment->shiftAssignments()->create([
        'tenant_id' => $this->tenant->id, 'shift_id' => $assigned->id,
        'effective_from' => now()->subDay()->toDateString(),
    ]);
    $withoutAssignment = Employee::factory()->forTenant($this->tenant)->create();

    expect($service->resolveShift($withAssignment, CarbonImmutable::now())->id)->toBe($assigned->id)
        ->and($service->resolveShift($withoutAssignment, CarbonImmutable::now())->id)->toBe($default->id);
});

it('lets an HR manager record and correct attendance manually', function () {
    $employee = Employee::factory()->forTenant($this->tenant)->create();

    $create = $this->actingAs($this->hr)->postJson('http://shift-co.hrm.test/attendance', [
        'employee_id' => $employee->id,
        'work_date' => now()->toDateString(),
        'clock_in_at' => now()->setTime(8, 0)->toIso8601String(),
        'clock_out_at' => now()->setTime(17, 0)->toIso8601String(),
        'break_minutes' => 60,
    ])->assertCreated();

    expect($create->json('data.worked_minutes'))->toBe(480);

    $uuid = $create->json('data.uuid');
    $this->actingAs($this->hr)->putJson("http://shift-co.hrm.test/attendance/{$uuid}", [
        'status' => 'remote_work',
    ])->assertOk()->assertJsonPath('data.status', 'remote_work');
});

it('scopes the attendance list: employees see only their own', function () {
    $mine = Employee::factory()->forTenant($this->tenant)->create();
    $employeeUser = userForTenant($this->tenant, Role::Employee);
    $mine->update(['user_id' => $employeeUser->id]);

    Attendance::factory()->forEmployee($mine)->count(2)->create();
    $other = Employee::factory()->forTenant($this->tenant)->create();
    Attendance::factory()->forEmployee($other)->count(3)->create();

    $this->actingAs($employeeUser)
        ->getJson('http://shift-co.hrm.test/attendance')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->actingAs($this->hr)
        ->getJson('http://shift-co.hrm.test/attendance')
        ->assertOk()
        ->assertJsonCount(5, 'data');
});

it('cannot correct attendance in another tenant', function () {
    $other = tenantWithRoles(['slug' => 'rival-shift-co']);
    $victim = Attendance::factory()->forEmployee(Employee::factory()->forTenant($other)->create())->create();

    $this->actingAs($this->hr)
        ->putJson("http://shift-co.hrm.test/attendance/{$victim->uuid}", ['status' => 'absent'])
        ->assertNotFound();
});
