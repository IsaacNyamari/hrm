<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserNotified;
use App\Models\User;
use App\Support\NotificationSummary;
use App\Support\RealTime;
use Illuminate\Notifications\Events\NotificationSent;

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

        $summary = NotificationSummary::for($event->notification, $event->notifiable);

        RealTime::send(new UserNotified(
            (int) $event->notifiable->getKey(),
            $summary->title,
            $summary->detail,
        ));
    }
}
