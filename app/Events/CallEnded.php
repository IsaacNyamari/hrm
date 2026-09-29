<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Call;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The call was ended for everyone (the last participant left, or the
 * initiator hung up for the whole room) — every remaining client should tear
 * down its peer connections and close the room. Invitees who were still
 * being rung never joined the call's presence channel, so they're notified
 * on their own private notification channel instead.
 */
class CallEnded implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  array<int, int>  $missedUserIds
     */
    public function __construct(public readonly Call $call, public readonly array $missedUserIds = []) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('call.'.$this->call->uuid),
            ...array_map(fn (int $userId): Channel => new PrivateChannel('App.Models.User.'.$userId), $this->missedUserIds),
        ];
    }

    public function broadcastAs(): string
    {
        return 'call.ended';
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return ['call_uuid' => $this->call->uuid];
    }
}
