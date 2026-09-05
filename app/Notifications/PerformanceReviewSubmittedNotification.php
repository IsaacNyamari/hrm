<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\PerformanceReview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PerformanceReviewSubmittedNotification extends Notification implements ShouldQueue
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
        $review = $this->review->loadMissing('cycle');

        return (new MailMessage)
            ->subject("Your performance review is ready: {$review->cycle->name}")
            ->line("Your {$review->reviewer_type->label()} for the {$review->cycle->name} cycle has been submitted.")
            ->line('Please read it and acknowledge it in the portal.')
            ->action('View review', url('/performance-reviews/'.$review->uuid));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'performance_review_submitted',
            'review_uuid' => $this->review->uuid,
            'cycle' => $this->review->cycle->name,
            'reviewer_type' => $this->review->reviewer_type->value,
            'overall_rating' => $this->review->overall_rating,
        ];
    }
}
