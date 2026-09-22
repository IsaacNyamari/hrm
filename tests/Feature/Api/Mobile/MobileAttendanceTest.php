<?php

declare(strict_types=1);

use App\Enums\AttendanceSource;
use App\Enums\Role;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Services\Attendance\AttendanceService;
use App\Services\Leave\LeaveService;
use App\Support\Attendance\PunchData;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->tenant = tenantWithRoles(['slug' => 'gps-co', 'settings' => ['attendance' => ['geofence_required' => true]]]);
    app(TenantContext::class)->set($this->tenant);
    $this->user = userForTenant($this->tenant, Role::Employee);

    // Office at the KICC, Nairobi. 100m radius.
    $this->branch = Branch::factory()->forTenant($this->tenant)->create([
        'latitude' => -1.288610, 'longitude' => 36.823380, 'geofence_radius_meters' => 100,
    ]);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create([
        'user_id' => $this->user->id, 'branch_id' => $this->branch->id,
    ]);
    $this->token = mobileToken($this->user);
    $this->travelTo(CarbonImmutable::parse('2026-09-22 08:00'));
});

it('clocks in from inside the geofence as a mobile punch with the GPS accuracy noted', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/attendance/clock-in', ['latitude' => -1.288650, 'longitude' => 36.823400, 'accuracy' => 12.4])
        ->assertOk()
        ->assertJsonPath('data.clocked_in', true)
        ->assertJsonPath('data.attendance.source', 'mobile');

    $record = Attendance::query()->where('employee_id', $this->employee->id)->firstOrFail();
    expect($record->source)->toBe(AttendanceSource::Mobile)
        ->and($record->clock_in_location)->toBe('Mobile app GPS (±12 m)')
        ->and((float) $record->clock_in_lat)->toBe(-1.28865);
});

it('returns the geofence and duplicate-punch rules verbatim as 422s', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/attendance/clock-in', ['latitude' => -1.300000, 'longitude' => 36.810000])
        ->assertStatus(422)
        ->assertJsonPath('message', 'You are outside the allowed clock-in area.');

    $this->withToken($this->token)
        ->postJson('/api/v1/attendance/clock-in')
        ->assertStatus(422)
        ->assertJsonPath('message', 'Location is required to clock in or out here.');

    $inside = ['latitude' => -1.288650, 'longitude' => 36.823400];
    $this->withToken($this->token)->postJson('/api/v1/attendance/clock-in', $inside)->assertOk();
    $this->withToken($this->token)
        ->postJson('/api/v1/attendance/clock-in', $inside)
        ->assertStatus(422)
        ->assertJsonPath('message', 'You are already clocked in for today.');
});

it('validates coordinates', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/attendance/clock-in', ['latitude' => 120, 'longitude' => 36.8])
        ->assertStatus(422)
        ->assertJsonValidationErrors('latitude');
});

it('runs a full day: break, end break and clock out', function () {
    $inside = ['latitude' => -1.288650, 'longitude' => 36.823400];

    $this->withToken($this->token)->postJson('/api/v1/attendance/clock-in', $inside)->assertOk();

    $this->travelTo(CarbonImmutable::parse('2026-09-22 12:00'));
    $this->withToken($this->token)->postJson('/api/v1/attendance/break/start')->assertOk()->assertJsonPath('data.on_break', true);

    $this->travelTo(CarbonImmutable::parse('2026-09-22 12:45'));
    $this->withToken($this->token)->postJson('/api/v1/attendance/break/end')->assertOk()->assertJsonPath('data.on_break', false);

    $this->travelTo(CarbonImmutable::parse('2026-09-22 17:00'));
    $this->withToken($this->token)
        ->postJson('/api/v1/attendance/clock-out', $inside)
        ->assertOk()
        ->assertJsonPath('data.clocked_out', true)
        ->assertJsonPath('data.attendance.break_minutes', 45)
        ->assertJsonCount(1, 'data.attendance.breaks');

    $this->withToken($this->token)
        ->getJson('/api/v1/attendance/today')
        ->assertOk()
        ->assertJsonPath('data.clocked_in', false)
        ->assertJsonPath('data.clocked_out', true);
});

it('refuses a break when not clocked in', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/attendance/break/start')
        ->assertStatus(422)
        ->assertJsonPath('message', 'You are not clocked in.');
});

it('returns the month history with approved leave and payroll-style totals', function () {
    $service = app(AttendanceService::class);
    $this->tenant->forceFill(['settings' => ['attendance' => ['geofence_required' => false]]])->save();
    app(TenantContext::class)->set($this->tenant->fresh());

    $this->travelTo(CarbonImmutable::parse('2026-09-21 08:00'));
    $service->clockIn($this->employee, new PunchData(AttendanceSource::Web));
    $this->travelTo(CarbonImmutable::parse('2026-09-21 17:00'));
    $service->clockOut($this->employee, new PunchData(AttendanceSource::Web));

    $annual = LeaveType::query()->where('code', 'ANNUAL')->firstOrFail();
    app(LeaveService::class)->adjustBalance($this->employee, $annual, 2026, 21);
    $leave = app(LeaveService::class)->request($this->employee, $annual, CarbonImmutable::parse('2026-09-28'), CarbonImmutable::parse('2026-09-29'));
    $leave->forceFill(['status' => 'approved'])->save();

    $this->travelTo(CarbonImmutable::parse('2026-09-22 09:00'));

    $this->withToken($this->token)
        ->getJson('/api/v1/attendance/history?month=2026-09')
        ->assertOk()
        ->assertJsonPath('data.month', '2026-09')
        ->assertJsonPath('data.label', 'September 2026')
        ->assertJsonCount(1, 'data.records')
        ->assertJsonPath('data.records.0.work_date', '2026-09-21')
        ->assertJsonCount(1, 'data.leave')
        ->assertJsonStructure(['data' => ['summary' => ['present_days', 'working_days_so_far', 'worked_hours', 'overtime_hours', 'paid_leave_days']]]);

    $this->withToken($this->token)->getJson('/api/v1/attendance/history?month=2026-12')->assertJsonPath('data.summary', null);
    $this->withToken($this->token)->getJson('/api/v1/attendance/history?month=nope')->assertStatus(422);
});

it('never shows another employee\'s attendance', function () {
    $colleague = Employee::factory()->forTenant($this->tenant)->create();
    Attendance::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $colleague->id, 'work_date' => '2026-09-21']);

    $this->withToken($this->token)
        ->getJson('/api/v1/attendance/history?month=2026-09')
        ->assertOk()
        ->assertJsonCount(0, 'data.records');
});
