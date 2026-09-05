<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly LeaveRequest $leaveRequest) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->leaveRequest;

        return (new MailMessage)
            ->subject('Leave request awaiting your approval')
            ->line("{$r->employee->fullName()} requested {$r->days} day(s) of {$r->leaveType->name}.")
            ->line("{$r->start_date->toFormattedDateString()} – {$r->end_date->toFormattedDateString()}")
            ->when($r->reason !== null, fn (MailMessage $m) => $m->line("Reason: {$r->reason}"))
            ->action('Review request', url('/leave-requests/'.$r->uuid));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'leave_requested',
            'leave_request_uuid' => $this->leaveRequest->uuid,
            'employee' => $this->leaveRequest->employee->fullName(),
            'days' => $this->leaveRequest->days,
        ];
    }
}
