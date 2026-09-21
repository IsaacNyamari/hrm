<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\Attendance\ZktecoDevices;
use App\Models\Employee;
use App\Support\TenantContext;
use Athwari\LaravelZktecoAdms\Models\ZktecoDevice;
use Athwari\LaravelZktecoAdms\Models\ZktecoDeviceCommand;
use Athwari\LaravelZktecoAdms\Models\ZktecoUser;
use Livewire\Livewire;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'zk-dash-co']);
    app(TenantContext::class)->set($this->tenant);
    $this->hr = userForTenant($this->tenant, Role::HrManager); // attendance.manage
    $this->employee = Employee::factory()->forTenant($this->tenant)->create();
});

it('registers a device from the dashboard', function () {
    $this->actingAs($this->hr);

    Livewire::test(ZktecoDevices::class)
        ->call('startCreate')
        ->set('form.serial_number', 'sn-abc123')
        ->set('form.name', 'Main gate')
        ->call('claim')
        ->assertDispatched('toast-show')
        ->assertSet('showForm', false);

    $device = ZktecoDevice::query()->where('serial_number', 'SN-ABC123')->firstOrFail();
    expect($device->tenant_id)->toBe($this->tenant->id);
});

it('refuses to register a device already claimed by another company', function () {
    $other = tenantWithRoles(['slug' => 'zk-dash-rival']);
    ZktecoDevice::factory()->forTenant($other->id)->create(['serial_number' => 'SN-TAKEN']);

    $this->actingAs($this->hr);

    Livewire::test(ZktecoDevices::class)
        ->call('startCreate')
        ->set('form.serial_number', 'SN-TAKEN')
        ->call('claim')
        ->assertDispatched('toast-show')
        ->assertSet('showForm', true);
});

it('renames a device from the dashboard', function () {
    $device = ZktecoDevice::factory()->forTenant($this->tenant->id)->create(['serial_number' => 'SN-RENAME', 'name' => 'Old']);

    $this->actingAs($this->hr);

    Livewire::test(ZktecoDevices::class)
        ->call('startRename', 'SN-RENAME')
        ->assertSet('renameValue', 'Old')
        ->set('renameValue', 'Reception')
        ->call('rename')
        ->assertDispatched('toast-show');

    expect($device->fresh()->name)->toBe('Reception');
});

it('removes a device from the dashboard without deleting its attendance history', function () {
    $device = ZktecoDevice::factory()->forTenant($this->tenant->id)->create(['serial_number' => 'SN-REMOVE']);

    $this->actingAs($this->hr);

    Livewire::test(ZktecoDevices::class)->call('release', 'SN-REMOVE');

    expect(ZktecoDevice::query()->where('serial_number', 'SN-REMOVE')->exists())->toBeFalse()
        ->and(ZktecoDevice::withTrashed()->where('serial_number', 'SN-REMOVE')->exists())->toBeTrue();
});

it('queues enrol and remove commands for an employee from the dashboard', function () {
    $device = ZktecoDevice::factory()->forTenant($this->tenant->id)->create(['serial_number' => 'SN-EMP']);

    $this->actingAs($this->hr);

    Livewire::test(ZktecoDevices::class)
        ->call('manageEmployees', 'SN-EMP')
        ->call('syncEmployee', $this->employee->id)
        ->assertDispatched('toast-show');

    expect(ZktecoDeviceCommand::query()->where('device_id', $device->id)->where('command_content', 'like', '%PIN='.$this->employee->id.'%')->exists())->toBeTrue();

    Livewire::test(ZktecoDevices::class)
        ->call('manageEmployees', 'SN-EMP')
        ->call('unsyncEmployee', $this->employee->id)
        ->assertDispatched('toast-show');

    expect(ZktecoDeviceCommand::query()->where('device_id', $device->id)->where('command_content', 'DATA DELETE USERINFO PIN='.$this->employee->id)->exists())->toBeTrue();
});

it('generates a new device password for an employee from the dashboard', function () {
    ZktecoDevice::factory()->forTenant($this->tenant->id)->create(['serial_number' => 'SN-PWD']);

    $this->actingAs($this->hr);

    Livewire::test(ZktecoDevices::class)
        ->call('manageEmployees', 'SN-PWD')
        ->call('regeneratePassword', $this->employee->id)
        ->assertDispatched('toast-show');

    expect($this->employee->fresh()->device_password)->not->toBeNull();
});

it('sets a card number for an employee and pushes it to their devices from the dashboard', function () {
    $device = ZktecoDevice::factory()->forTenant($this->tenant->id)->create(['serial_number' => 'SN-CARD']);

    $this->actingAs($this->hr);

    Livewire::test(ZktecoDevices::class)
        ->call('startEditCard', $this->employee->id)
        ->assertSet('cardValue', '')
        ->set('cardValue', '0012345678')
        ->call('saveCardNumber')
        ->assertDispatched('toast-show');

    expect($this->employee->fresh()->device_card_number)->toBe('0012345678');
    expect(ZktecoDeviceCommand::query()->where('device_id', $device->id)->where('command_content', 'like', '%Card=0012345678%')->exists())->toBeTrue();
});

it('clears an employee\'s card number when saved blank', function () {
    $this->employee->forceFill(['device_card_number' => '0099999999'])->save();
    ZktecoDevice::factory()->forTenant($this->tenant->id)->create(['serial_number' => 'SN-CARD-CLEAR']);

    $this->actingAs($this->hr);

    Livewire::test(ZktecoDevices::class)
        ->call('startEditCard', $this->employee->id)
        ->assertSet('cardValue', '0099999999')
        ->set('cardValue', '')
        ->call('saveCardNumber')
        ->assertDispatched('toast-show');

    expect($this->employee->fresh()->device_card_number)->toBeNull();
});

it('shows a message when an employee has no fingerprints on file yet', function () {
    ZktecoDevice::factory()->forTenant($this->tenant->id)->create(['serial_number' => 'SN-FP-NONE']);

    $this->actingAs($this->hr);

    Livewire::test(ZktecoDevices::class)
        ->call('manageFingerprints', $this->employee->id)
        ->assertSee('No fingerprints on file yet');
});

it('pushes a fingerprint to every other device from the dashboard', function () {
    $capturedOn = ZktecoDevice::factory()->forTenant($this->tenant->id)->create(['serial_number' => 'SN-FP-1']);
    $other = ZktecoDevice::factory()->forTenant($this->tenant->id)->create(['serial_number' => 'SN-FP-2']);

    ZktecoUser::query()->create([
        'pin' => (string) $this->employee->id,
        'fingerprints' => ['0' => ['template' => 'tmpl', 'size' => 4, 'valid' => true, 'device_id' => $capturedOn->id, 'captured_at' => now()->toIso8601String()]],
    ]);

    $this->actingAs($this->hr);

    Livewire::test(ZktecoDevices::class)
        ->call('manageFingerprints', $this->employee->id)
        ->assertSee('Finger #0')
        ->call('pushFingerprintEverywhere', $this->employee->id, 0)
        ->assertDispatched('toast-show');

    expect(ZktecoDeviceCommand::query()->where('device_id', $other->id)->where('command_content', 'like', '%TMP=tmpl%')->exists())->toBeTrue()
        ->and(ZktecoDeviceCommand::query()->where('device_id', $capturedOn->id)->where('command_content', 'like', 'DATA UPDATE FINGERTMP%')->exists())->toBeFalse();
});

it('deletes a fingerprint from every device from the dashboard', function () {
    $device = ZktecoDevice::factory()->forTenant($this->tenant->id)->create(['serial_number' => 'SN-FP-3']);

    ZktecoUser::query()->create([
        'pin' => (string) $this->employee->id,
        'fingerprints' => ['0' => ['template' => 'tmpl', 'size' => 4, 'valid' => true, 'device_id' => $device->id, 'captured_at' => now()->toIso8601String()]],
    ]);

    $this->actingAs($this->hr);

    Livewire::test(ZktecoDevices::class)
        ->call('manageFingerprints', $this->employee->id)
        ->call('deleteFingerprintEverywhere', $this->employee->id, 0)
        ->assertDispatched('toast-show');

    expect(ZktecoUser::query()->where('pin', (string) $this->employee->id)->first()->fingerprints)->toBe([]);
});

it('opens a live users list after querying a device, and fills in as replies arrive', function () {
    ZktecoDevice::factory()->forTenant($this->tenant->id)->create(['serial_number' => 'SN-USERS']);

    $this->actingAs($this->hr);

    $test = Livewire::test(ZktecoDevices::class)
        ->call('queryUsers', 'SN-USERS')
        ->assertSet('viewingUsersSerial', 'SN-USERS')
        ->assertSee('Waiting for the device to reply');

    $body = "PIN={$this->employee->id}\tName=Device Name\tPrivilege=0\tCard=0055554444\tPasswd=";
    $this->call('POST', '/iclock/cdata?SN=SN-USERS&table=USERINFO', content: $body)->assertOk();

    $test->set('viewingUsersSerial', 'SN-USERS')->assertSee($this->employee->fullName());
});

it('fills in the enrolled-users list from a real device\'s OPERLOG reply to a query, not just a USERINFO push', function () {
    ZktecoDevice::factory()->forTenant($this->tenant->id)->create(['serial_number' => 'SN-USERS-OPLOG']);

    $this->actingAs($this->hr);

    $test = Livewire::test(ZktecoDevices::class)
        ->call('queryUsers', 'SN-USERS-OPLOG')
        ->assertSee('Waiting for the device to reply');

    // The K40 Pro (and most ADMS/push-protocol terminals) answer a
    // DATA QUERY USERINFO command by re-announcing each user through the
    // same OPERLOG channel used for local terminal edits — not via a
    // separate `table=USERINFO` push.
    $body = "USER PIN={$this->employee->id}\tName=On Device\tPri=0\tPasswd=\tCard=0088887777\tGrp=1\tTZ=0000000000000000";
    $this->call('POST', '/iclock/cdata?SN=SN-USERS-OPLOG&table=OPERLOG', content: $body)->assertOk();

    $test->set('viewingUsersSerial', 'SN-USERS-OPLOG')->assertSee($this->employee->fullName());
});

it('hides device users that don\'t match an employee in this system', function () {
    ZktecoDevice::factory()->forTenant($this->tenant->id)->create(['serial_number' => 'SN-USERS-STRAY']);

    $this->actingAs($this->hr);

    $test = Livewire::test(ZktecoDevices::class)->call('queryUsers', 'SN-USERS-STRAY');

    // PIN 999999 isn't any employee's id — e.g. a leftover installer/admin
    // account on the terminal, or a PIN from before it was claimed.
    $body = "USER PIN=999999\tName=Installer\tPri=14\tPasswd=\tCard=\tGrp=1\tTZ=0000000000000000";
    $this->call('POST', '/iclock/cdata?SN=SN-USERS-STRAY&table=OPERLOG', content: $body)->assertOk();

    $test->set('viewingUsersSerial', 'SN-USERS-STRAY')
        ->assertDontSee('Installer')
        ->assertSee('Waiting for the device to reply');
});

it('sends a check and a reboot command from the dashboard', function () {
    ZktecoDevice::factory()->forTenant($this->tenant->id)->create(['serial_number' => 'SN-CMD']);

    $this->actingAs($this->hr);

    Livewire::test(ZktecoDevices::class)
        ->call('sendCheck', 'SN-CMD')
        ->assertDispatched('toast-show');

    Livewire::test(ZktecoDevices::class)
        ->call('sendReboot', 'SN-CMD')
        ->assertDispatched('toast-show');

    $commands = ZktecoDeviceCommand::query()->pluck('command_content');
    expect($commands)->toContain('CHECK')->and($commands)->toContain('REBOOT');
});

it('scopes devices to the current tenant only', function () {
    $other = tenantWithRoles(['slug' => 'zk-dash-rival-2']);
    ZktecoDevice::factory()->forTenant($other->id)->create(['name' => 'Rival device']);
    ZktecoDevice::factory()->forTenant($this->tenant->id)->create(['name' => 'Mine']);

    $this->actingAs($this->hr);

    Livewire::test(ZktecoDevices::class)
        ->assertSee('Mine')
        ->assertDontSee('Rival device');
});

it('forbids managing biometric devices from the dashboard without attendance.manage', function () {
    $staff = userForTenant($this->tenant, Role::Employee);

    $this->actingAs($staff);

    Livewire::test(ZktecoDevices::class)->assertForbidden();
});
