<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserNotified;
use App\Models\User;
use App\Support\RealTime;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Every notification stored in a user's in-app inbox is also pushed to their
 * browser in real time.
 */
class BroadcastDatabaseNotifications
{
    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database' || ! $event->notifiable instanceof User) {
            return;
        }

        $data = method_exists($event->notification, 'toArray') ? (array) $event->notification->toArray($event->notifiable) : [];

        $detail = collect(['applicant', 'employee', 'vacancy', 'title', 'subject', 'reference'])
            ->map(fn (string $key) => Arr::get($data, $key))
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->take(2)
            ->implode(' · ');

        RealTime::send(new UserNotified(
            (int) $event->notifiable->getKey(),
            Str::headline((string) ($data['type'] ?? 'New notification')),
            $detail,
        ));
    }
}
