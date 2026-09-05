<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Invitation $invitation) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenant = $this->invitation->tenant;
        $url = route('invitations.show', ['invitation' => $this->invitation->token]);

        return (new MailMessage)
            ->subject("You've been invited to join {$tenant->name}")
            ->greeting('Hello,')
            ->line("{$tenant->name} has invited you to join their HR workspace as \"{$this->invitation->role->value}\".")
            ->action('Accept invitation', $url)
            ->line("This invitation expires {$this->invitation->expires_at->diffForHumans()}.")
            ->line('If you were not expecting this invitation you can ignore this email.');
    }
}
