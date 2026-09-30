<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Notifications\DeviceOfflineNotification;
use App\Support\TenantContext;
use Athwari\LaravelZktecoAdms\Models\ZktecoDevice;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->tenant = tenantWithRoles(['slug' => 'dev-co']);
    app(TenantContext::class)->set($this->tenant);
    $this->manager = userForTenant($this->tenant, Role::HrManager); // attendance.manage
    $this->staff = userForTenant($this->tenant, Role::Employee);
});

function makeDevice(int $tenantId, string $serial, ?DateTimeInterface $lastActivity): ZktecoDevice
{
    return ZktecoDevice::query()->create(['tenant_id' => $tenantId, 'serial_number' => $serial, 'name' => 'Gate', 'last_activity_at' => $lastActivity]);
}

it('alerts attendance managers once when a device has been silent too long', function () {
    Notification::fake();
    makeDevice($this->tenant->id, 'SN-OFFLINE', now()->subDays(6));

    $this->artisan('attendance:check-devices')->assertSuccessful();
    $this->artisan('attendance:check-devices')->assertSuccessful();

    Notification::assertSentToTimes($this->manager, DeviceOfflineNotification::class, 1);
    Notification::assertNotSentTo($this->staff, DeviceOfflineNotification::class);
});

it('stays quiet for a device that reported recently and re-arms after it recovers', function () {
    Notification::fake();
    $device = makeDevice($this->tenant->id, 'SN-HEALTHY', now()->subMinutes(10));

    $this->artisan('attendance:check-devices')->assertSuccessful();
    Notification::assertNothingSent();

    $device->forceFill(['last_activity_at' => now()->subHours(8)])->save();
    $this->artisan('attendance:check-devices')->assertSuccessful();
    Notification::assertSentToTimes($this->manager, DeviceOfflineNotification::class, 1);

    $device->forceFill(['last_activity_at' => now()->subMinute()])->save();
    $this->artisan('attendance:check-devices')->assertSuccessful();
    $device->forceFill(['last_activity_at' => now()->subHours(8)])->save();
    $this->artisan('attendance:check-devices')->assertSuccessful();

    Notification::assertSentToTimes($this->manager, DeviceOfflineNotification::class, 2);
});
