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

class PaymentReceiptNotification extends Notification implements ShouldQueue
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
        $amount = number_format($this->payment->amount_cents / 100, 2);

        return (new MailMessage)
            ->subject('Payment received — thank you')
            ->line("We received your payment of {$this->payment->currency} {$amount}.")
            ->action('View billing', url('/billing'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_succeeded',
            'payment_uuid' => $this->payment->uuid,
            'amount_cents' => $this->payment->amount_cents,
        ];
    }
}
