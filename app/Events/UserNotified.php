<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A new in-app notification was stored for a user: their notification bell
 * updates and a toast appears without a page refresh.
 */
class UserNotified implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly int $userId,
        public readonly string $title,
        public readonly string $detail,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('App.Models.User.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'user.notified';
    }

    /**
     * @return array{title: string, detail: string}
     */
    public function broadcastWith(): array
    {
        return ['title' => $this->title, 'detail' => $this->detail];
    }
}
