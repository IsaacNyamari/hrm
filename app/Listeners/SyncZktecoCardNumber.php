<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Services\Zkteco\ZktecoDeviceService;
use Athwari\LaravelZktecoAdms\Events\UserSynced;

/**
 * Whenever a device reports a user record back to us (e.g. after HR assigns
 * an access card directly at the terminal's own User Mgt menu), mirror the
 * card number it holds onto the matching employee — so the dashboard always
 * reflects what's actually on the device without anyone re-typing it here.
 */
class SyncZktecoCardNumber
{
    public function __construct(private readonly ZktecoDeviceService $service) {}

    public function handle(UserSynced $event): void
    {
        $this->service->adoptCardNumberFromDevice($event->device, $event->user->pin, $event->user->card_number);
    }
}
