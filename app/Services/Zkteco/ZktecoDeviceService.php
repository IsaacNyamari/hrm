<?php

declare(strict_types=1);

namespace App\Services\Zkteco;

use App\Exceptions\ZktecoException;
use App\Models\Employee;
use Athwari\LaravelZktecoAdms\LaravelZktecoAdms;
use Athwari\LaravelZktecoAdms\Models\ZktecoDevice;
use Athwari\LaravelZktecoAdms\Models\ZktecoUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Bridges the tenancy-agnostic ZKTeco ADMS package to this application's
 * multi-tenant model. The package only ever sees a bare `tenant_id` column
 * on its devices table; every ownership/authorization decision happens here.
 *
 * A device's PIN is the employee's global database id (not the tenant-scoped
 * employee_number) because zkteco_users.pin is unique across the *whole*
 * install, and two different companies' employee numbers can otherwise
 * collide (e.g. both start at EMP-000001).
 */
class ZktecoDeviceService
{
    public function __construct(private readonly LaravelZktecoAdms $adms) {}

    /**
     * @return Collection<int, ZktecoDevice>
     */
    public function devicesForTenant(int $tenantId): Collection
    {
        return ZktecoDevice::query()->forTenant($tenantId)->orderBy('name')->orderBy('serial_number')->get();
    }

    public function findForTenant(int $tenantId, string $uuidOrSerial): ?ZktecoDevice
    {
        return ZktecoDevice::query()
            ->forTenant($tenantId)
            ->where('serial_number', $uuidOrSerial)
            ->first();
    }

    /**
     * Claim a device for a tenant — either pre-registering a serial number
     * before the physical unit ever phones home, or taking ownership of one
     * that already auto-registered itself with no tenant attached yet.
     *
     * @throws ZktecoException if the serial is already claimed by another tenant
     */
    public function claim(int $tenantId, string $serialNumber, ?string $name = null): ZktecoDevice
    {
        $serialNumber = mb_strtoupper(trim($serialNumber));
        $existing = ZktecoDevice::query()->where('serial_number', $serialNumber)->first();

        if ($existing !== null && $existing->tenant_id !== null && $existing->tenant_id !== $tenantId) {
            throw ZktecoException::alreadyClaimed();
        }

        return ZktecoDevice::query()->updateOrCreate(
            ['serial_number' => $serialNumber],
            ['tenant_id' => $tenantId] + array_filter(['name' => $name], static fn ($v): bool => $v !== null),
        );
    }

    public function rename(ZktecoDevice $device, string $name): void
    {
        $device->update(['name' => $name]);
    }

    /**
     * Release a device from this tenant. History (attendance logs, users,
     * commands) is left in place; the device can be re-claimed later.
     */
    public function release(ZktecoDevice $device): void
    {
        $device->delete();
    }

    /**
     * Queue a command that pushes the employee's PIN + name (+ keypad
     * password, generating one the first time) to the device, so it is ready
     * for the employee to verify — by keypad or, once separately enrolled, by
     * fingerprint — against that PIN.
     */
    public function syncEmployee(ZktecoDevice $device, Employee $employee): int
    {
        $password = $employee->device_password ?? $this->generateDevicePassword($employee);

        return $this->adms->sendUserAddCommand(
            $device->serial_number,
            (string) $employee->getKey(),
            $employee->fullName(),
            card: $employee->device_card_number ?? '',
            password: $password,
        );
    }

    /**
     * Set (or clear) an employee's access-card number and push the change to
     * every device they're currently registered on.
     */
    public function setCardNumber(Employee $employee, ?string $cardNumber): void
    {
        $employee->forceFill(['device_card_number' => $cardNumber])->save();

        foreach ($this->devicesForTenant($employee->tenant_id) as $device) {
            $this->adms->sendUserAddCommand(
                $device->serial_number,
                (string) $employee->getKey(),
                $employee->fullName(),
                card: $cardNumber ?? '',
                password: $employee->device_password ?? '',
            );
        }
    }

    /**
     * Generate (or replace) an employee's device keypad password and push the
     * change to every device they're currently registered on.
     */
    public function regenerateDevicePassword(Employee $employee): string
    {
        $password = $this->generateDevicePassword($employee);

        foreach ($this->devicesForTenant($employee->tenant_id) as $device) {
            $this->adms->sendUserAddCommand(
                $device->serial_number,
                (string) $employee->getKey(),
                $employee->fullName(),
                card: $employee->device_card_number ?? '',
                password: $password,
            );
        }

        return $password;
    }

    private function generateDevicePassword(Employee $employee): string
    {
        $password = (string) random_int(100000, 999999);
        $employee->forceFill(['device_password' => $password])->save();

        return $password;
    }

    /**
     * Queue a command that removes the employee from the device.
     */
    public function removeEmployee(ZktecoDevice $device, Employee $employee): int
    {
        return $this->adms->sendUserDeleteCommand($device->serial_number, (string) $employee->getKey());
    }

    /**
     * Push a previously captured fingerprint template to a device for an
     * employee, without them ever touching that terminal. The template must
     * already be in the format the target device's algorithm expects — see
     * App\Services\Zkteco\ZktecoDeviceService class docs / project notes on
     * fingerprint capture hardware for why that constraint exists.
     */
    public function pushFingerprintTemplate(ZktecoDevice $device, Employee $employee, int $fingerIndex, string $base64Template): int
    {
        return $this->adms->sendFingerprintCommand($device->serial_number, (string) $employee->getKey(), $fingerIndex, $base64Template);
    }

    /**
     * The fingerprint templates on file for an employee, captured whenever
     * they enrolled directly at a device (the terminal reports the template
     * back to us automatically). Keyed by finger index (0-9).
     *
     * @return array<string, array{template: string, size: int, valid: bool, device_id: int, captured_at: string}>
     */
    public function fingerprintsFor(Employee $employee): array
    {
        $user = ZktecoUser::query()->where('pin', (string) $employee->getKey())->first();

        return $user?->fingerprints ?? [];
    }

    /**
     * Push every fingerprint template on file for an employee to a device
     * they haven't enrolled at directly — "enrol once, recognised everywhere".
     *
     * @return int number of templates pushed
     */
    public function pushAllFingerprintsTo(ZktecoDevice $device, Employee $employee): int
    {
        $pushed = 0;

        foreach ($this->fingerprintsFor($employee) as $fingerIndex => $data) {
            $this->pushFingerprintTemplate($device, $employee, (int) $fingerIndex, $data['template']);
            $pushed++;
        }

        return $pushed;
    }

    /**
     * Remove a fingerprint template — from the device (queued) and from our
     * own record of it. Pass null to clear every finger for this employee.
     */
    public function deleteFingerprint(ZktecoDevice $device, Employee $employee, ?int $fingerIndex = null): int
    {
        $commandId = $this->adms->sendFingerprintDeleteCommand($device->serial_number, (string) $employee->getKey(), $fingerIndex);

        $user = ZktecoUser::query()->where('pin', (string) $employee->getKey())->first();

        if ($user !== null) {
            $fingerprints = $user->fingerprints ?? [];

            if ($fingerIndex === null) {
                $fingerprints = [];
            } else {
                unset($fingerprints[(string) $fingerIndex]);
            }

            $user->update(['fingerprints' => $fingerprints]);
        }

        return $commandId;
    }

    /**
     * Mirror a card number the device itself reports for a PIN (e.g. after HR
     * assigns a card directly at the terminal, or in a users-list query
     * response) onto the matching employee — never the other way around.
     */
    public function adoptCardNumberFromDevice(ZktecoDevice $device, string $pin, string $cardAsReportedByDevice): void
    {
        if ($device->tenant_id === null) {
            return;
        }

        $employee = Employee::query()
            ->where('id', (int) $pin)
            ->where('tenant_id', $device->tenant_id)
            ->first();

        if ($employee === null) {
            Log::info('Zkteco device reported a user for an unknown employee PIN.', [
                'serial_number' => $device->serial_number, 'pin' => $pin,
            ]);

            return;
        }

        $cardNumber = $cardAsReportedByDevice !== '' ? $cardAsReportedByDevice : null;

        if ($employee->device_card_number !== $cardNumber) {
            $employee->forceFill(['device_card_number' => $cardNumber])->save();
        }
    }

    public function sendCheckCommand(ZktecoDevice $device): int
    {
        return $this->adms->sendCheckCommand($device->serial_number);
    }

    public function sendRebootCommand(ZktecoDevice $device): int
    {
        return $this->adms->sendRebootCommand($device->serial_number);
    }

    public function sendQueryUsersCommand(ZktecoDevice $device): int
    {
        return $this->adms->sendQueryUsersCommand($device->serial_number);
    }

    public function pendingCommandCount(ZktecoDevice $device): int
    {
        return $this->adms->pendingCount($device->serial_number);
    }
}
