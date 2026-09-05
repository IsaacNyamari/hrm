<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Models\Announcement;
use App\Notifications\Concerns\RespectsPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class AnnouncementPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RespectsPreferences;

    public function __construct(private readonly Announcement $announcement) {}

    public function preferenceCategory(): NotificationCategory
    {
        return NotificationCategory::Announcements;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Announcement: {$this->announcement->title}")
            ->line(Str::limit(strip_tags($this->announcement->body), 400))
            ->action('Read announcement', url('/portal'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'announcement_published',
            'announcement_uuid' => $this->announcement->uuid,
            'title' => $this->announcement->title,
        ];
    }
}
