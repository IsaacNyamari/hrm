<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a hired candidate's staff account is created: a link to choose a
 * password and open their self-service dashboard.
 */
class WelcomeNewHireNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $url,
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
        $name = $notifiable instanceof User ? strtok($notifiable->name, ' ') : null;

        return (new MailMessage)
            ->subject("Welcome to {$this->tenant->name} — set up your account")
            ->greeting($name ? "Welcome, {$name}!" : 'Welcome!')
            ->line("Congratulations on joining **{$this->tenant->name}**. Your staff account has been created.")
            ->line('Use the button below to choose your password. You can then sign in to see your tasks, payslips, leave balances, attendance and company announcements.')
            ->action('Set up my account', $this->url)
            ->line('This link can be used once and expires in 7 days. After that, use "Forgot password" on the login page.')
            ->line('Your username is this email address.');
    }
}
