<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TaskAssigned;
use App\Events\TaskCompleted;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskCompletedNotification;
use Illuminate\Support\Facades\Notification;

class SendTaskNotifications
{
    public function handleAssigned(TaskAssigned $event): void
    {
        $userId = $event->task->employee->user_id;

        if ($userId !== null) {
            Notification::send(User::query()->whereKey($userId)->get(), new TaskAssignedNotification($event->task));
        }
    }

    public function handleCompleted(TaskCompleted $event): void
    {
        $assignerId = $event->task->assigned_by;

        if ($assignerId !== null) {
            Notification::send(User::query()->whereKey($assignerId)->get(), new TaskCompletedNotification($event->task));
        }
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(): array
    {
        return [
            TaskAssigned::class => 'handleAssigned',
            TaskCompleted::class => 'handleCompleted',
        ];
    }
}
