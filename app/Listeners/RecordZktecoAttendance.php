<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\AttendanceSource;
use App\Exceptions\AttendanceException;
use App\Models\Employee;
use App\Models\Tenant;
use App\Services\Attendance\AttendanceService;
use App\Support\Attendance\PunchData;
use App\Support\TenantContext;
use Athwari\LaravelZktecoAdms\DTOs\AttendanceRecord;
use Athwari\LaravelZktecoAdms\Enums\AttendanceStatus as ZktecoAttendanceStatus;
use Athwari\LaravelZktecoAdms\Events\AttendanceReceived;
use Athwari\LaravelZktecoAdms\Models\ZktecoDevice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Bridges a ZKTeco device's ATTLOG push into this application's own
 * tenant-scoped attendance records. The ADMS package has no concept of
 * tenants — it hands us the raw device serial + parsed punches, and this
 * listener is entirely responsible for attributing them safely.
 */
class RecordZktecoAttendance
{
    public function __construct(
        private readonly AttendanceService $service,
        private readonly TenantContext $context,
    ) {}

    public function handle(AttendanceReceived $event): void
    {
        $device = ZktecoDevice::query()->where('serial_number', $event->serialNumber)->first();

        if ($device === null || $device->tenant_id === null) {
            Log::warning('Zkteco attendance push from an unclaimed device.', ['serial_number' => $event->serialNumber]);

            return;
        }

        $tenant = Tenant::query()->find($device->tenant_id);

        if ($tenant === null) {
            Log::warning('Zkteco device references a tenant that no longer exists.', [
                'serial_number' => $event->serialNumber, 'tenant_id' => $device->tenant_id,
            ]);

            return;
        }

        $this->context->run($tenant, function () use ($event, $device): void {
            foreach ($event->records as $record) {
                $this->applyRecord($record, $device);
            }
        });
    }

    private function applyRecord(AttendanceRecord $record, ZktecoDevice $device): void
    {
        // The PIN is the employee's global id (see ZktecoDeviceService) —
        // still re-check tenant ownership as defence in depth.
        $employee = Employee::query()->where('id', (int) $record->pin)->where('tenant_id', $device->tenant_id)->first();

        if ($employee === null) {
            Log::warning('Zkteco punch for an unknown employee PIN.', [
                'serial_number' => $device->serial_number, 'pin' => $record->pin,
            ]);

            return;
        }

        $punch = new PunchData(
            source: AttendanceSource::Biometric,
            at: CarbonImmutable::createFromInterface($record->timestamp),
            location: $device->name ?? $device->serial_number,
        );

        try {
            match ($record->statusEnum()) {
                ZktecoAttendanceStatus::CheckIn, ZktecoAttendanceStatus::OvertimeIn => $this->service->clockIn($employee, $punch),
                ZktecoAttendanceStatus::CheckOut, ZktecoAttendanceStatus::OvertimeOut => $this->service->clockOut($employee, $punch),
                ZktecoAttendanceStatus::BreakOut => $this->service->startBreak($employee, $punch),
                ZktecoAttendanceStatus::BreakIn => $this->service->endBreak($employee, $punch),
                null => Log::warning('Unrecognised Zkteco attendance status.', ['status' => $record->status]),
            };
        } catch (AttendanceException $e) {
            // A punch that doesn't fit the current state (e.g. a duplicate
            // check-in) is a normal occurrence with biometric devices —
            // log and move on rather than losing the whole ATTLOG batch.
            Log::info('Zkteco punch rejected: '.$e->getMessage(), [
                'employee_id' => $employee->getKey(), 'serial_number' => $device->serial_number,
            ]);
        }
    }
}
