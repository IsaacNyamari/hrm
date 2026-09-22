<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * The short title / detail line shown for an in-app notification — used by the
 * live browser toast, the mobile inbox and the mobile push notification so all
 * three read the same.
 */
final readonly class NotificationSummary
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $title,
        public string $detail,
        public array $data,
    ) {}

    public static function for(Notification $notification, object $notifiable): self
    {
        return self::fromData(method_exists($notification, 'toArray') ? (array) $notification->toArray($notifiable) : []);
    }

    /**
     * @param  array<string, mixed>  $data  A notification's stored (database) payload.
     */
    public static function fromData(array $data, string $fallbackType = 'New notification'): self
    {
        $detail = collect(['applicant', 'employee', 'vacancy', 'title', 'subject', 'reference'])
            ->map(fn (string $key) => Arr::get($data, $key))
            ->filter(fn ($value) => is_string($value) && $value !== '')
            ->take(2)
            ->implode(' · ');

        return new self(Str::headline((string) ($data['type'] ?? $fallbackType)), $detail, $data);
    }
}
