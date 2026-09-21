<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\Zkteco\ZktecoDeviceService;
use Athwari\LaravelZktecoAdms\Events\UserQueryReceived;
use Athwari\LaravelZktecoAdms\Models\ZktecoDevice;
use Athwari\LaravelZktecoAdms\Models\ZktecoUser;

/**
 * Persists the reply to a "List enrolled users" (DATA QUERY USERINFO)
 * command. The device answers this asynchronously, on its own polling
 * schedule, in a completely separate HTTP request from the one that queued
 * it — this listener is the callback that catches that reply and stores it,
 * so the dashboard has something to render once it arrives.
 */
class StoreZktecoUserQueryResults
{
    public function __construct(private readonly ZktecoDeviceService $service) {}

    public function handle(UserQueryReceived $event): void
    {
        $device = ZktecoDevice::query()->where('serial_number', $event->serialNumber)->first();

        if ($device === null) {
            return;
        }

        foreach ($event->users as $record) {
            ZktecoUser::query()->updateOrCreate(
                ['pin' => $record->pin],
                [
                    'name' => $record->name !== '' ? $record->name : null,
                    'card_number' => $record->card !== '' ? $record->card : null,
                    'privilege' => $record->privilege,
                    'device_id' => $device->id,
                ],
            );

            $this->service->adoptCardNumberFromDevice($device, $record->pin, $record->card);
        }
    }
}
