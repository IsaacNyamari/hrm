<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Notifications\Concerns\RespectsPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeviceOfflineNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RespectsPreferences;

    public function __construct(
        private readonly string $deviceName,
        private readonly string $serialNumber,
        private readonly ?string $lastSeen,
    ) {}

    public function preferenceCategory(): NotificationCategory
    {
        return NotificationCategory::System;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Biometric device offline: {$this->deviceName}")
            ->line("The biometric device \"{$this->deviceName}\" ({$this->serialNumber}) has not contacted the system since {$this->lastSeen}.")
            ->line('Punches made on it are not reaching attendance. Check its power, network and server settings.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'device_offline',
            'title' => "Biometric device offline: {$this->deviceName}",
            'serial_number' => $this->serialNumber,
            'last_seen' => $this->lastSeen,
        ];
    }
}
