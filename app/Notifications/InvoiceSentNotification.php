<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Actions\Billing\RenderInvoicePdf;
use App\Enums\NotificationCategory;
use App\Models\Invoice;
use App\Notifications\Concerns\RespectsPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceSentNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RespectsPreferences;

    public function __construct(private readonly Invoice $invoice) {}

    public function preferenceCategory(): NotificationCategory
    {
        return NotificationCategory::Billing;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $renderer = app(RenderInvoicePdf::class);
        $total = number_format($this->invoice->total_cents / 100, 2);

        $message = (new MailMessage)
            ->subject("Invoice {$this->invoice->number}")
            ->line("Your invoice {$this->invoice->number} for {$this->invoice->currency} {$total} is attached.")
            ->action('View billing', url('/billing'))
            ->attachData($renderer->output($this->invoice), $renderer->filename($this->invoice), ['mime' => 'application/pdf']);

        if ($this->invoice->due_at !== null) {
            $message->line('Payment is due by '.$this->invoice->due_at->format('j M Y').'.');
        }

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'invoice_sent',
            'invoice_uuid' => $this->invoice->uuid,
            'reference' => $this->invoice->number,
            'total_cents' => $this->invoice->total_cents,
        ];
    }
}
