<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells platform admins a quote request / contact message arrived on the website.
 */
class NewLeadNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Lead $lead) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $lead = $this->lead;

        return (new MailMessage)
            ->subject("New {$lead->kind->label()}: {$lead->name}".($lead->company ? " ({$lead->company})" : ''))
            ->line("{$lead->name} <{$lead->email}> sent a {$lead->kind->label()} from the website.")
            ->when($lead->company, fn (MailMessage $m) => $m->line("Organization: {$lead->company}"))
            ->when($lead->employee_count, fn (MailMessage $m) => $m->line("Employees: {$lead->employee_count}"))
            ->when($lead->message, fn (MailMessage $m) => $m->line('Message: '.$lead->message))
            ->action('Review lead', route('platform.leads'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'lead_received',
            'lead_uuid' => $this->lead->uuid,
            'kind' => $this->lead->kind->value,
            'name' => $this->lead->name,
            'company' => $this->lead->company,
        ];
    }
}
