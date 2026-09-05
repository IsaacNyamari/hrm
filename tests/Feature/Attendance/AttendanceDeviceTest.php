<?php

declare(strict_types=1);

use App\Enums\AttendanceSource;
use App\Enums\Role;
use App\Models\Attendance;
use App\Models\AttendanceDevice;
use App\Models\Employee;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'device-co']);
    $this->hr = userForTenant($this->tenant, Role::HrManager);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create(['employee_number' => 'DEV-EMP-1']);
});

it('registers a device and returns the secret once', function () {
    $response = $this->actingAs($this->hr)
        ->postJson('http://device-co.hrm.test/attendance-devices', [
            'name' => 'Main Gate', 'type' => 'fingerprint', 'serial_number' => 'ZK-001',
        ])
        ->assertCreated();

    $secret = $response->json('data.secret');
    expect($secret)->toBeString()->and(strlen($secret))->toBe(48);

    // subsequent reads never expose it
    $this->actingAs($this->hr)
        ->getJson('http://device-co.hrm.test/attendance-devices')
        ->assertOk()
        ->assertJsonMissing(['secret' => $secret]);
});

it('ingests a punch from a device with the right secret', function () {
    $device = AttendanceDevice::factory()->forTenant($this->tenant)->create();
    $secret = 'known-secret-value';
    $device->update(['secret' => $secret]);

    $this->postJson("/api/webhooks/attendance/{$device->uuid}", [
        'employee_number' => 'DEV-EMP-1',
        'event' => 'in',
    ], ['X-Device-Secret' => $secret])->assertOk()->assertJsonPath('status', 'ok');

    $attendance = Attendance::query()->where('employee_id', $this->employee->id)->first();
    expect($attendance)->not->toBeNull()
        ->and($attendance->source)->toBe(AttendanceSource::Biometric)
        ->and($device->fresh()->last_seen_at)->not->toBeNull();
});

it('rejects a device punch with a bad secret', function () {
    $device = AttendanceDevice::factory()->forTenant($this->tenant)->create();
    $device->update(['secret' => 'the-real-secret']);

    $this->postJson("/api/webhooks/attendance/{$device->uuid}", [
        'employee_number' => 'DEV-EMP-1', 'event' => 'in',
    ], ['X-Device-Secret' => 'wrong'])->assertStatus(401);

    expect(Attendance::query()->count())->toBe(0);
});

it('rejects an unknown employee', function () {
    $device = AttendanceDevice::factory()->forTenant($this->tenant)->create();
    $device->update(['secret' => 's3cr3t']);

    $this->postJson("/api/webhooks/attendance/{$device->uuid}", [
        'employee_number' => 'NOPE', 'event' => 'in',
    ], ['X-Device-Secret' => 's3cr3t'])->assertStatus(422)->assertJsonPath('status', 'rejected');
});
