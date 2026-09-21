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

class TaskCompletedNotification extends Notification implements ShouldQueue
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
            ->subject('A task was marked complete')
            ->line("{$t->employee->fullName()} completed \"{$t->title}\".")
            ->when($t->remarks !== null, fn (MailMessage $m) => $m->line("Remarks: {$t->remarks}"))
            ->action('View task', url('/tasks'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_completed',
            'task_uuid' => $this->task->uuid,
            'title' => $this->task->title,
            'employee' => $this->task->employee->fullName(),
        ];
    }
}
