<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrialEndingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Subscription $subscription,
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
        $when = $this->daysLeft <= 0 ? 'today' : "in {$this->daysLeft} day(s)";

        return (new MailMessage)
            ->subject("Your trial ends {$when}")
            ->line("The free trial for {$this->subscription->tenant->name} ends {$when}.")
            ->action('Choose a plan', url('/billing'))
            ->line('Add a payment method to keep your team working without interruption.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'trial_ending',
            'subscription_uuid' => $this->subscription->uuid,
            'days_left' => $this->daysLeft,
        ];
    }
}
