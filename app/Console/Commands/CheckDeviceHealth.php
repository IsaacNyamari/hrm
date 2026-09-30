<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Permission;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\DeviceOfflineNotification;
use App\Support\TenantContext;
use Athwari\LaravelZktecoAdms\Models\ZktecoDevice;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

class CheckDeviceHealth extends Command
{
    protected $signature = 'attendance:check-devices';

    protected $description = 'Alert attendance managers when a biometric device stops reporting';

    public function handle(TenantContext $context): int
    {
        $threshold = CarbonImmutable::now()->subHours((int) config('attendance.device_offline_hours', 6));
        $alerted = 0;

        Tenant::query()->chunkById(100, function ($tenants) use ($context, $threshold, &$alerted): void {
            foreach ($tenants as $tenant) {
                foreach (ZktecoDevice::query()->where('tenant_id', $tenant->getKey())->get() as $device) {
                    $key = "zkteco:offline-alerted:{$device->getKey()}";
                    $seen = $device->last_activity_at;

                    if ($seen !== null && $seen->greaterThan($threshold)) {
                        Cache::forget($key); // back online: alert again next time it drops

                        continue;
                    }

                    if (Cache::has($key)) {
                        continue;
                    }

                    $recipients = $context->run($tenant, fn () => $this->recipients($tenant->getKey()));

                    Notification::send($recipients, new DeviceOfflineNotification(
                        (string) ($device->name ?: $device->serial_number),
                        (string) $device->serial_number,
                        $seen?->toDayDateTimeString() ?? 'it was registered',
                    ));

                    Cache::forever($key, true);
                    $alerted += $recipients->count();
                }
            }
        });

        $this->info("Sent {$alerted} device-offline alert(s).");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(int $tenantId): Collection
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($tenantId);

        try {
            return User::query()->where('tenant_id', $tenantId)->permission(Permission::AttendanceManage->value)->get();
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }
}
