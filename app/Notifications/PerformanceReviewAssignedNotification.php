<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\PerformanceReview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PerformanceReviewAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly PerformanceReview $review) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $review = $this->review->loadMissing(['employee', 'cycle']);

        return (new MailMessage)
            ->subject("Performance review to complete: {$review->cycle->name}")
            ->line("You have been assigned a {$review->reviewer_type->label()} for {$review->employee->fullName()}.")
            ->line("Cycle: {$review->cycle->name} (closes {$review->cycle->ends_on->toFormattedDateString()}).")
            ->action('Open review', url('/performance-reviews/'.$review->uuid));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'performance_review_assigned',
            'review_uuid' => $this->review->uuid,
            'cycle' => $this->review->cycle->name,
            'employee' => $this->review->employee->fullName(),
            'reviewer_type' => $this->review->reviewer_type->value,
        ];
    }
}
