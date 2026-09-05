<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Payment $payment) {}

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
            ->subject('Your subscription payment failed')
            ->line('We could not process your latest subscription payment.')
            ->line($this->payment->failure_reason ?? 'The payment was declined by the provider.')
            ->action('Retry payment', url('/billing'))
            ->line('Your workspace stays active during the grace period.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_failed',
            'payment_uuid' => $this->payment->uuid,
            'amount_cents' => $this->payment->amount_cents,
            'reason' => $this->payment->failure_reason,
        ];
    }
}
