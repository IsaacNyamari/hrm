<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Interview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InterviewScheduledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Interview $interview) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $interview = $this->interview->loadMissing('application.applicant');

        return (new MailMessage)
            ->subject('Interview scheduled')
            ->line("You are scheduled to interview {$interview->application->applicant->fullName()}.")
            ->line("When: {$interview->scheduled_at->toDayDateTimeString()} ({$interview->mode->label()})")
            ->action('Open application', url('/recruitment/applications/'.$interview->application->uuid));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'interview_scheduled',
            'interview_uuid' => $this->interview->uuid,
            'applicant' => $this->interview->application->applicant->fullName(),
            'scheduled_at' => $this->interview->scheduled_at->toIso8601String(),
        ];
    }
}
