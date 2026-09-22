<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AttendanceLogged;
use App\Jobs\SendExpoPushNotification;
use App\Models\DeviceToken;
use App\Models\User;
use App\Support\NotificationSummary;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Mirrors what a user sees in the web app onto their phone as a push
 * notification: every in-app (database) notification — leave requests and
 * decisions, tasks, announcements, … — and attendance recorded for them by
 * someone or something else (a biometric device, an HR correction).
 *
 * Delivery runs on the queue; users with no registered device are skipped.
 */
class SendPushNotifications
{
    /** Punches the employee made on this device or in the browser need no push. */
    private const SILENT_SOURCES = ['mobile', 'web'];

    public function handleNotificationSent(NotificationSent $event): void
    {
        if ($event->channel !== 'database' || ! $event->notifiable instanceof User) {
            return;
        }

        $user = $event->notifiable;

        if (! $this->hasDevices((int) $user->getKey())) {
            return;
        }

        $summary = NotificationSummary::for($event->notification, $user);

        $data = ['type' => (string) ($summary->data['type'] ?? 'notification'), 'notification_id' => $event->notification->id]
            + Arr::where($summary->data, fn ($value, $key): bool => is_string($key) && Str::endsWith($key, '_uuid') && is_string($value));

        SendExpoPushNotification::dispatch(
            (int) $user->getKey(),
            $summary->title,
            $summary->detail !== '' ? $summary->detail : 'Open HRM to see the details.',
            $data,
        );
    }

    public function handleAttendanceLogged(AttendanceLogged $event): void
    {
        if ($event->employeeUserId === null || in_array($event->source, self::SILENT_SOURCES, true)) {
            return;
        }

        if (! $this->hasDevices($event->employeeUserId)) {
            return;
        }

        SendExpoPushNotification::dispatch(
            $event->employeeUserId,
            'Attendance recorded',
            $event->message(),
            ['type' => 'attendance_logged', 'action' => $event->action],
        );
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(): array
    {
        return [
            NotificationSent::class => 'handleNotificationSent',
            AttendanceLogged::class => 'handleAttendanceLogged',
        ];
    }

    private function hasDevices(int $userId): bool
    {
        return DeviceToken::withoutTenantScope()->where('user_id', $userId)->exists();
    }
}
