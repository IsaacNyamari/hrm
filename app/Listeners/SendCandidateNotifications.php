<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ApplicationReceived;
use App\Events\ApplicationRejected;
use App\Events\ApplicationStageChanged;
use App\Events\InterviewScheduled;
use App\Events\OfferSent;
use App\Models\Application;
use App\Models\Interview;
use App\Notifications\CandidateUpdateNotification;
use Illuminate\Support\Facades\Notification;

/**
 * Keeps job applicants informed by email as their application moves along.
 * Applicants have no account, so mail goes straight to the address they
 * applied with.
 */
class SendCandidateNotifications
{
    public function received(ApplicationReceived $event): void
    {
        $this->send($event->application, CandidateUpdateNotification::RECEIVED);
    }

    public function interview(InterviewScheduled $event): void
    {
        $this->send($event->interview->loadMissing('application')->application, CandidateUpdateNotification::INTERVIEW, $event->interview);
    }

    public function offer(OfferSent $event): void
    {
        $this->send($event->offer->loadMissing('application')->application, CandidateUpdateNotification::OFFER);
    }

    public function stageChanged(ApplicationStageChanged $event): void
    {
        $this->send($event->application, CandidateUpdateNotification::STAGE, stage: $event->toStage);
    }

    public function rejected(ApplicationRejected $event): void
    {
        $this->send($event->application, CandidateUpdateNotification::UNSUCCESSFUL);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(): array
    {
        return [
            ApplicationReceived::class => 'received',
            InterviewScheduled::class => 'interview',
            OfferSent::class => 'offer',
            ApplicationRejected::class => 'rejected',
            ApplicationStageChanged::class => 'stageChanged',
        ];
    }

    private function send(Application $application, string $kind, ?Interview $interview = null, ?string $stage = null): void
    {
        $email = $application->applicant()->withoutGlobalScopes()->value('email');

        if ($email) {
            Notification::route('mail', $email)->notify(new CandidateUpdateNotification($application, $kind, $interview, $stage));
        }
    }
}
