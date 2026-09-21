<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\RegistrationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationAccessNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The plain token only lives in this (queued) message; the database holds its hash.
     */
    public function __construct(
        private readonly RegistrationInvitation $invitation,
        private readonly string $token,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name');

        return (new MailMessage)
            ->subject("Your access to {$appName} is ready")
            ->greeting($this->invitation->name ? "Hello {$this->invitation->name}," : 'Hello,')
            ->line("Thanks for your interest in {$appName}. Your request has been approved — use the button below to create your organization's workspace.")
            ->action('Create my workspace', route('join.open', ['token' => $this->token]))
            ->line('For your security this link works **once**: it expires as soon as it is opened, so open it when you are ready to finish signing up.')
            ->line("If it is never opened it expires {$this->invitation->expires_at->diffForHumans()}.")
            ->line('If you did not request access you can ignore this email.');
    }
}
