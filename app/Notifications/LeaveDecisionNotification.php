<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\LeaveStatus;
use App\Enums\NotificationCategory;
use App\Models\LeaveRequest;
use App\Notifications\Concerns\RespectsPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveDecisionNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RespectsPreferences;

    public function __construct(private readonly LeaveRequest $leaveRequest) {}

    public function preferenceCategory(): NotificationCategory
    {
        return NotificationCategory::Leave;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved = $this->leaveRequest->status === LeaveStatus::Approved;
        $r = $this->leaveRequest;

        return (new MailMessage)
            ->subject($approved ? 'Your leave was approved' : 'Your leave was not approved')
            ->line("Your {$r->leaveType->name} request for {$r->start_date->toFormattedDateString()} – {$r->end_date->toFormattedDateString()} was ".($approved ? 'approved.' : 'rejected.'))
            ->when($r->decision_note !== null, fn (MailMessage $m) => $m->line("Note: {$r->decision_note}"))
            ->action('View request', url('/leave-requests/'.$r->uuid));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'leave_decision',
            'leave_request_uuid' => $this->leaveRequest->uuid,
            'status' => $this->leaveRequest->status->value,
        ];
    }
}
