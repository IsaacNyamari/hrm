<?php

declare(strict_types=1);

use App\Exceptions\ZktecoException;
use App\Listeners\RecordZktecoAttendance;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Scopes\TenantScope;
use App\Services\Zkteco\ZktecoDeviceService;
use App\Support\TenantContext;
use Athwari\LaravelZktecoAdms\DTOs\AttendanceRecord;
use Athwari\LaravelZktecoAdms\Events\AttendanceReceived;
use Athwari\LaravelZktecoAdms\Models\ZktecoDevice;
use Athwari\LaravelZktecoAdms\Models\ZktecoDeviceCommand;
use Athwari\LaravelZktecoAdms\Models\ZktecoUser;
use Carbon\CarbonImmutable;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'zk-co']);
    app(TenantContext::class)->set($this->tenant);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create();
});

it('claims an unregistered device for a tenant', function () {
    $device = app(ZktecoDeviceService::class)->claim($this->tenant->id, 'SN0001', 'Main gate');

    expect($device->tenant_id)->toBe($this->tenant->id)
        ->and($device->name)->toBe('Main gate');
});

it('refuses to claim a device already owned by another tenant', function () {
    $other = tenantWithRoles(['slug' => 'zk-rival']);
    ZktecoDevice::factory()->forTenant($other->id)->create(['serial_number' => 'SN0002']);

    expect(fn () => app(ZktecoDeviceService::class)->claim($this->tenant->id, 'SN0002'))
        ->toThrow(ZktecoException::class);
});

it('lets the owning tenant re-claim (rename) its own device without duplicating it', function () {
    $service = app(ZktecoDeviceService::class);
    $service->claim($this->tenant->id, 'SN0003', 'Old name');
    $device = $service->claim($this->tenant->id, 'SN0003', 'New name');

    expect($device->name)->toBe('New name')
        ->and(ZktecoDevice::query()->where('serial_number', 'SN0003')->count())->toBe(1);
});

it('queues a user-add command keyed by the employee\'s global id, not the tenant-scoped employee number', function () {
    $service = app(ZktecoDeviceService::class);
    $device = $service->claim($this->tenant->id, 'SN0004');

    $service->syncEmployee($device, $this->employee);

    $command = ZktecoDeviceCommand::query()->where('device_id', $device->id)->firstOrFail();
    expect($command->command_content)->toContain('PIN='.$this->employee->id)
        ->and($command->command_content)->toContain($this->employee->fullName());
});

it('generates a persistent device password the first time an employee is synced, and reuses it after', function () {
    $service = app(ZktecoDeviceService::class);
    $deviceA = $service->claim($this->tenant->id, 'SN0004A');
    $deviceB = $service->claim($this->tenant->id, 'SN0004B');

    $service->syncEmployee($deviceA, $this->employee);
    $firstPassword = $this->employee->fresh()->device_password;
    expect($firstPassword)->not->toBeNull()->and($firstPassword)->toMatch('/^\d{6}$/');

    $service->syncEmployee($deviceB, $this->employee->fresh());
    expect($this->employee->fresh()->device_password)->toBe($firstPassword);

    $commandA = ZktecoDeviceCommand::query()->where('device_id', $deviceA->id)->firstOrFail();
    expect($commandA->command_content)->toContain('Passwd='.$firstPassword);
});

it('regenerates and re-pushes a device password to every device the employee is on', function () {
    $service = app(ZktecoDeviceService::class);
    $deviceA = $service->claim($this->tenant->id, 'SN0004C');
    $deviceB = $service->claim($this->tenant->id, 'SN0004D');

    $service->syncEmployee($deviceA, $this->employee);
    $oldPassword = $this->employee->fresh()->device_password;

    $newPassword = $service->regenerateDevicePassword($this->employee->fresh());

    expect($newPassword)->not->toBe($oldPassword)
        ->and($this->employee->fresh()->device_password)->toBe($newPassword);

    foreach ([$deviceA, $deviceB] as $device) {
        expect(ZktecoDeviceCommand::query()->where('device_id', $device->id)->where('command_content', 'like', '%Passwd='.$newPassword.'%')->exists())->toBeTrue();
    }
});

it('includes the employee\'s card number when syncing to a device', function () {
    $service = app(ZktecoDeviceService::class);
    $device = $service->claim($this->tenant->id, 'SN0004F');
    $this->employee->forceFill(['device_card_number' => '0011223344'])->save();

    $service->syncEmployee($device, $this->employee->fresh());

    $command = ZktecoDeviceCommand::query()->where('device_id', $device->id)->firstOrFail();
    expect($command->command_content)->toContain('Card=0011223344');
});

it('sets a card number and pushes it to every device the employee is on', function () {
    $service = app(ZktecoDeviceService::class);
    $deviceA = $service->claim($this->tenant->id, 'SN0004G');
    $deviceB = $service->claim($this->tenant->id, 'SN0004H');
    $service->syncEmployee($deviceA, $this->employee);

    $service->setCardNumber($this->employee->fresh(), '0055667788');

    expect($this->employee->fresh()->device_card_number)->toBe('0055667788');
    foreach ([$deviceA, $deviceB] as $device) {
        expect(ZktecoDeviceCommand::query()->where('device_id', $device->id)->where('command_content', 'like', '%Card=0055667788%')->exists())->toBeTrue();
    }
});

it('queues a user-delete command when removing an employee from a device', function () {
    $service = app(ZktecoDeviceService::class);
    $device = $service->claim($this->tenant->id, 'SN0007');

    $service->removeEmployee($device, $this->employee);

    $command = ZktecoDeviceCommand::query()->where('device_id', $device->id)->firstOrFail();
    expect($command->command_content)->toBe('DATA DELETE USERINFO PIN='.$this->employee->id);
});

it('queues a fingerprint template push for a specific finger slot', function () {
    $service = app(ZktecoDeviceService::class);
    $device = $service->claim($this->tenant->id, 'SN0004E');

    $template = base64_encode('fake-template-bytes');
    $service->pushFingerprintTemplate($device, $this->employee, 0, $template);

    $command = ZktecoDeviceCommand::query()->where('device_id', $device->id)->firstOrFail();
    expect($command->command_content)->toBe('DATA UPDATE FINGERTMP PIN='.$this->employee->id."\tFID=0\tSize=".strlen($template)."\tValid=1\tTMP=".$template);
});

it('captures a fingerprint template reported back by the terminal after in-person enrolment', function () {
    $service = app(ZktecoDeviceService::class);
    $device = $service->claim($this->tenant->id, 'SN-FP-TEST');

    $template = base64_encode('captured-template-bytes');
    $body = "FP PIN={$this->employee->id}\tFID=0\tSize=".strlen($template)."\tValid=1\tTMP={$template}";

    $this->call('POST', "/iclock/cdata?SN={$device->serial_number}&table=OPERLOG", content: $body)
        ->assertOk();

    $stored = $service->fingerprintsFor($this->employee);

    expect($stored)->toHaveKey('0')
        ->and($stored['0']['template'])->toBe($template)
        ->and($stored['0']['device_id'])->toBe($device->id);
});

it('adopts a card number the terminal reports after a local edit at the device', function () {
    $service = app(ZktecoDeviceService::class);
    $device = $service->claim($this->tenant->id, 'SN-CARD-TERM');

    $body = "USER PIN={$this->employee->id}\tName=Terminal User\tPri=0\tPasswd=\tCard=0012345678\tGrp=1\tTZ=0000000000000000";

    $this->call('POST', "/iclock/cdata?SN={$device->serial_number}&table=OPERLOG", content: $body)
        ->assertOk();

    expect($this->employee->fresh()->device_card_number)->toBe('0012345678');

    $zktecoUser = ZktecoUser::query()->where('pin', (string) $this->employee->id)->firstOrFail();
    expect($zktecoUser->device_id)->toBe($device->id);
});

it('stores the users list returned by a device query and adopts card numbers from it', function () {
    $service = app(ZktecoDeviceService::class);
    $device = $service->claim($this->tenant->id, 'SN-USERLIST');

    $body = "PIN={$this->employee->id}\tName=Jane Doe\tPrivilege=0\tCard=0099999999\tPasswd=";

    $this->call('POST', "/iclock/cdata?SN={$device->serial_number}&table=USERINFO", content: $body)
        ->assertOk();

    $zktecoUser = ZktecoUser::query()->where('pin', (string) $this->employee->id)->firstOrFail();
    expect($zktecoUser->card_number)->toBe('0099999999')
        ->and($zktecoUser->device_id)->toBe($device->id)
        ->and($this->employee->fresh()->device_card_number)->toBe('0099999999');
});

it('pushes a captured fingerprint to every other device the employee is not enrolled on directly', function () {
    $service = app(ZktecoDeviceService::class);
    $capturedOn = $service->claim($this->tenant->id, 'SN-FP-A');
    $otherDevice = $service->claim($this->tenant->id, 'SN-FP-B');

    ZktecoUser::query()->create([
        'pin' => (string) $this->employee->id,
        'fingerprints' => [
            '0' => ['template' => 'tmpl-data', 'size' => 9, 'valid' => true, 'device_id' => $capturedOn->id, 'captured_at' => now()->toIso8601String()],
        ],
    ]);

    $pushed = 0;
    foreach ($service->devicesForTenant($this->tenant->id) as $device) {
        if ($device->id === $capturedOn->id) {
            continue;
        }
        $service->pushFingerprintTemplate($device, $this->employee, 0, 'tmpl-data');
        $pushed++;
    }

    expect($pushed)->toBe(1);
    $command = ZktecoDeviceCommand::query()->where('device_id', $otherDevice->id)->firstOrFail();
    expect($command->command_content)->toContain('TMP=tmpl-data');
});

it('deletes a fingerprint from a device and clears the local record', function () {
    $service = app(ZktecoDeviceService::class);
    $device = $service->claim($this->tenant->id, 'SN-FP-DEL');

    ZktecoUser::query()->create([
        'pin' => (string) $this->employee->id,
        'fingerprints' => ['0' => ['template' => 'x', 'size' => 1, 'valid' => true, 'device_id' => $device->id, 'captured_at' => now()->toIso8601String()]],
    ]);

    $service->deleteFingerprint($device, $this->employee, 0);

    expect($service->fingerprintsFor($this->employee))->toBe([]);
    $command = ZktecoDeviceCommand::query()->where('device_id', $device->id)->firstOrFail();
    expect($command->command_content)->toContain('DATA DELETE FINGERTMP');
});

it('records a clock-in from a claimed device\'s attendance push', function () {
    $device = ZktecoDevice::factory()->forTenant($this->tenant->id)->create(['serial_number' => 'SN0005']);

    $record = new AttendanceRecord(
        pin: (string) $this->employee->id,
        timestamp: CarbonImmutable::now(),
        status: 0, // CheckIn
        verifyMode: 1,
        workCode: '',
        serialNumber: 'SN0005',
    );

    // No tenant bound right now — the listener must resolve it entirely from
    // the device, exactly like a real, unauthenticated device push would.
    app(TenantContext::class)->forget();

    app(RecordZktecoAttendance::class)->handle(new AttendanceReceived('SN0005', [$record]));

    $attendance = Attendance::withoutGlobalScope(TenantScope::class)->where('employee_id', $this->employee->id)->first();

    expect($attendance)->not->toBeNull()
        ->and($attendance->tenant_id)->toBe($this->tenant->id)
        ->and($attendance->clock_in_at)->not->toBeNull()
        ->and($attendance->source->value)->toBe('biometric');
});

it('records a clock-out from a subsequent push on the same device', function () {
    $device = ZktecoDevice::factory()->forTenant($this->tenant->id)->create(['serial_number' => 'SN0008']);
    app(TenantContext::class)->forget();

    $morning = CarbonImmutable::now()->setTime(8, 0);
    $evening = $morning->setTime(17, 0);

    app(RecordZktecoAttendance::class)->handle(new AttendanceReceived('SN0008', [
        new AttendanceRecord((string) $this->employee->id, $morning, 0, 1, '', 'SN0008'),
    ]));
    app(RecordZktecoAttendance::class)->handle(new AttendanceReceived('SN0008', [
        new AttendanceRecord((string) $this->employee->id, $evening, 1, 1, '', 'SN0008'),
    ]));

    $attendance = Attendance::withoutGlobalScope(TenantScope::class)->where('employee_id', $this->employee->id)->firstOrFail();

    expect($attendance->clock_in_at)->not->toBeNull()
        ->and($attendance->clock_out_at)->not->toBeNull();
});

it('ignores an attendance push from an unclaimed device', function () {
    $record = new AttendanceRecord((string) $this->employee->id, CarbonImmutable::now(), 0, 1, '', 'SN-UNKNOWN');

    app(RecordZktecoAttendance::class)->handle(new AttendanceReceived('SN-UNKNOWN', [$record]));

    expect(Attendance::withoutGlobalScope(TenantScope::class)->where('employee_id', $this->employee->id)->exists())->toBeFalse();
});

it('ignores a punch whose pin belongs to a different tenant than the device', function () {
    $device = ZktecoDevice::factory()->forTenant($this->tenant->id)->create(['serial_number' => 'SN0006']);
    $other = tenantWithRoles(['slug' => 'zk-rival-2']);
    $stranger = Employee::factory()->forTenant($other)->create();

    $record = new AttendanceRecord((string) $stranger->id, CarbonImmutable::now(), 0, 1, '', 'SN0006');
    app(RecordZktecoAttendance::class)->handle(new AttendanceReceived('SN0006', [$record]));

    expect(Attendance::withoutGlobalScope(TenantScope::class)->where('employee_id', $stranger->id)->exists())->toBeFalse();
});
