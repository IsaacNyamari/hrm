<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PaymentFailed;
use App\Events\PaymentSucceeded;
use App\Models\User;
use App\Notifications\PaymentFailedNotification;
use App\Notifications\PaymentReceiptNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

class SendBillingNotifications
{
    public function handlePaymentFailed(PaymentFailed $event): void
    {
        Notification::send(
            $this->recipients($event->payment->tenant_id),
            new PaymentFailedNotification($event->payment),
        );
    }

    public function handlePaymentSucceeded(PaymentSucceeded $event): void
    {
        Notification::send(
            $this->recipients($event->payment->tenant_id),
            new PaymentReceiptNotification($event->payment),
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(int $tenantId): Collection
    {
        return User::query()->where('tenant_id', $tenantId)->get();
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(): array
    {
        return [
            PaymentFailed::class => 'handlePaymentFailed',
            PaymentSucceeded::class => 'handlePaymentSucceeded',
        ];
    }
}
