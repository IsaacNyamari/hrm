<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Task;
use App\Notifications\Concerns\RespectsPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RespectsPreferences;

    public function __construct(private readonly Task $task) {}

    public function preferenceCategory(): NotificationCategory
    {
        return NotificationCategory::Tasks;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $t = $this->task;

        return (new MailMessage)
            ->subject('You have been assigned a new task')
            ->line($t->title)
            ->when($t->description !== null, fn (MailMessage $m) => $m->line($t->description))
            ->when($t->due_date !== null, fn (MailMessage $m) => $m->line("Due: {$t->due_date->toFormattedDateString()}"))
            ->action('View task', url('/tasks'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_assigned',
            'task_uuid' => $this->task->uuid,
            'title' => $this->task->title,
            'due_date' => $this->task->due_date?->toDateString(),
        ];
    }
}
