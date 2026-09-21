<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Application;
use App\Models\Interview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emails a job applicant (who has no account) about their application. Every
 * message carries the tracking code and a link to the public tracking page.
 */
class CandidateUpdateNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const RECEIVED = 'received';

    public const INTERVIEW = 'interview';

    public const OFFER = 'offer';

    public const UNSUCCESSFUL = 'unsuccessful';

    public function __construct(
        public readonly Application $application,
        public readonly string $kind,
        public readonly ?Interview $interview = null,
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
        $application = $this->application;
        $vacancy = $application->vacancy()->withoutGlobalScopes()->first();
        $organization = $vacancy?->tenant()->first()?->name ?? config('app.name');
        $role = $vacancy?->title ?? 'the role';
        $name = $application->applicant()->withoutGlobalScopes()->value('first_name');

        $mail = (new MailMessage)->greeting($name ? "Hello {$name}," : 'Hello,');

        $mail = match ($this->kind) {
            self::RECEIVED => $mail
                ->subject("Application received: {$role} at {$organization}")
                ->line("Thank you for applying for **{$role}** at {$organization}. We have received your application.")
                ->line("Your tracking code is **{$application->tracking_code}**. Keep it safe — with it and this email address you can follow your application at any time, no account needed."),
            self::INTERVIEW => $this->interviewLines($mail
                ->subject("Interview invitation: {$role} at {$organization}")
                ->line("Good news — {$organization} would like to interview you for **{$role}**.")),
            self::OFFER => $mail
                ->subject("Job offer: {$role} at {$organization}")
                ->line("Congratulations! {$organization} has made you an offer for **{$role}**.")
                ->line('Open your application to see the details and accept or decline the offer.'),
            self::UNSUCCESSFUL => $mail
                ->subject("Your application for {$role} at {$organization}")
                ->line("Thank you for your interest in **{$role}** at {$organization} and for the time you put into your application.")
                ->line('After careful consideration, the team has decided not to move forward with your application this time. We encourage you to apply for other openings.'),
            default => $mail->subject("Update on your application for {$role}"),
        };

        return $mail
            ->action('Track my application', route('applications.track', ['code' => $application->tracking_code]))
            ->line("Tracking code: {$application->tracking_code}");
    }

    private function interviewLines(MailMessage $mail): MailMessage
    {
        $interview = $this->interview;

        if ($interview === null) {
            return $mail;
        }

        $mail->line('**When:** '.$interview->scheduled_at->format('l j F Y, H:i').' ('.$interview->duration_minutes.' minutes)')
            ->line('**How:** '.$interview->mode->label());

        if ($interview->location) {
            $mail->line('**Where:** '.$interview->location);
        }

        if ($interview->meeting_url) {
            $mail->line('**Meeting link:** '.$interview->meeting_url);
        }

        return $mail;
    }
}
