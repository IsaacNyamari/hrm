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

class ExpenseDecidedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RespectsPreferences;

    public function __construct(
        private readonly Expense $expense,
        private readonly bool $approved,
    ) {}

    public function preferenceCategory(): NotificationCategory
    {
        return NotificationCategory::Expenses;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verb = $this->approved ? 'approved' : 'rejected';

        return (new MailMessage)
            ->subject("Your expense claim was {$verb}: {$this->expense->reference}")
            ->line("\"{$this->expense->title}\" was {$verb}.")
            ->when($this->expense->decision_note !== null, fn (MailMessage $m) => $m->line('Note: '.$this->expense->decision_note))
            ->action('View claim', url('/expenses/'.$this->expense->uuid));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'expense_decided',
            'expense_uuid' => $this->expense->uuid,
            'reference' => $this->expense->reference,
            'approved' => $this->approved,
            'status' => $this->expense->status->value,
        ];
    }
}
