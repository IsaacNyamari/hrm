<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\ReviewerType;
use App\Events\PerformanceReviewAssigned;
use App\Events\PerformanceReviewSubmitted;
use App\Notifications\PerformanceReviewAssignedNotification;
use App\Notifications\PerformanceReviewSubmittedNotification;
use Illuminate\Support\Facades\Notification;

class SendPerformanceNotifications
{
    public function handleAssigned(PerformanceReviewAssigned $event): void
    {
        $reviewer = $event->review->reviewer;

        // A self review is "assigned" to the employee themselves; no e-mail nag.
        if ($reviewer === null || $event->review->reviewer_type === ReviewerType::SelfReview) {
            return;
        }

        $reviewer->notify(new PerformanceReviewAssignedNotification($event->review));
    }

    public function handleSubmitted(PerformanceReviewSubmitted $event): void
    {
        // Only the manager/peer/upward result is pushed to the subject employee.
        if ($event->review->reviewer_type === ReviewerType::SelfReview) {
            return;
        }

        $user = $event->review->employee->user;

        if ($user === null) {
            return;
        }

        Notification::send($user, new PerformanceReviewSubmittedNotification($event->review));
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(): array
    {
        return [
            PerformanceReviewAssigned::class => 'handleAssigned',
            PerformanceReviewSubmitted::class => 'handleSubmitted',
        ];
    }
}
