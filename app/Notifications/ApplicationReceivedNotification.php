<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Application $application) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $application = $this->application->loadMissing(['applicant', 'vacancy']);

        return (new MailMessage)
            ->subject("New application: {$application->vacancy->title}")
            ->line("{$application->applicant->fullName()} applied for {$application->vacancy->title}.")
            ->action('Review application', url('/recruitment/applications/'.$application->uuid));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'application_received',
            'application_uuid' => $this->application->uuid,
            'vacancy' => $this->application->vacancy->title,
            'applicant' => $this->application->applicant->fullName(),
        ];
    }
}
