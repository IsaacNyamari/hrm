<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\HrRequest;
use App\Notifications\Concerns\RespectsPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HrRequestSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RespectsPreferences;

    public function __construct(private readonly HrRequest $request) {}

    public function preferenceCategory(): NotificationCategory
    {
        return NotificationCategory::HrRequests;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New HR request: {$this->request->reference}")
            ->line("{$this->request->employee->fullName()} submitted a {$this->request->type->label()} request.")
            ->line("Subject: {$this->request->subject}")
            ->action('Open request', url('/hr-requests/'.$this->request->uuid));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'hr_request_submitted',
            'hr_request_uuid' => $this->request->uuid,
            'reference' => $this->request->reference,
            'subject' => $this->request->subject,
        ];
    }
}
