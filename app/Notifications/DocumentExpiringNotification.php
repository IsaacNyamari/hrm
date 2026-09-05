<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\EmployeeDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly EmployeeDocument $document,
        private readonly int $daysLeft,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Document expiring in {$this->daysLeft} day(s): {$this->document->title}")
            ->line("The document \"{$this->document->title}\" for {$this->document->employee->fullName()} expires on {$this->document->expiry_date?->toFormattedDateString()}.")
            ->line('Please arrange a renewal.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'document_expiring',
            'document_uuid' => $this->document->uuid,
            'title' => $this->document->title,
            'days_left' => $this->daysLeft,
            'expiry_date' => $this->document->expiry_date?->toDateString(),
        ];
    }
}
