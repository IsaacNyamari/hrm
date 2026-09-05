<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Payment;
use App\Notifications\Concerns\RespectsPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RespectsPreferences;

    public function __construct(private readonly Payment $payment) {}

    public function preferenceCategory(): NotificationCategory
    {
        return NotificationCategory::Billing;
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
