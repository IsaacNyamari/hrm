<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Expense;
use App\Notifications\Concerns\RespectsPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExpenseSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RespectsPreferences;

    public function __construct(private readonly Expense $expense) {}

    public function preferenceCategory(): NotificationCategory
    {
        return NotificationCategory::Expenses;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expense = $this->expense->loadMissing('employee');

        return (new MailMessage)
            ->subject("Expense claim to review: {$expense->reference}")
            ->line("{$expense->employee->fullName()} submitted \"{$expense->title}\".")
            ->line('Amount: '.number_format($expense->total_cents / 100, 2).' '.$expense->currency)
            ->action('Review claim', url('/expenses/'.$expense->uuid));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'expense_submitted',
            'expense_uuid' => $this->expense->uuid,
            'reference' => $this->expense->reference,
            'total_cents' => $this->expense->total_cents,
        ];
    }
}
