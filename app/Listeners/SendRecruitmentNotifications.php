<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\Permission;
use App\Events\ApplicationReceived;
use App\Events\InterviewScheduled;
use App\Models\User;
use App\Notifications\ApplicationReceivedNotification;
use App\Notifications\InterviewScheduledNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

class SendRecruitmentNotifications
{
    public function handleApplication(ApplicationReceived $event): void
    {
        $application = $event->application->loadMissing('vacancy');
        $recipients = $this->withinTeam($application->tenant_id, fn () => User::query()
            ->where('tenant_id', $application->tenant_id)
            ->permission(Permission::RecruitmentView->value)
            ->get());

        // Prefer the assigned recruiter / hiring manager when there is one.
        $assignedId = $application->assigned_to;
        if ($assignedId !== null) {
            $recipients = $recipients->push(User::query()->find($assignedId))->filter()->unique('id');
        }

        Notification::send($recipients, new ApplicationReceivedNotification($application));
    }

    public function handleInterview(InterviewScheduled $event): void
    {
        $interviewer = $event->interview->interviewer;

        if ($interviewer !== null) {
            $interviewer->notify(new InterviewScheduledNotification($event->interview));
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withinTeam(int $tenantId, callable $callback): mixed
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($tenantId);

        try {
            return $callback();
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(): array
    {
        return [
            ApplicationReceived::class => 'handleApplication',
            InterviewScheduled::class => 'handleInterview',
        ];
    }
}
