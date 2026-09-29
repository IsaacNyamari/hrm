<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Call;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The call was ended for everyone (the last participant left, or the
 * initiator hung up for the whole room) — every remaining client should tear
 * down its peer connections and close the room.
 */
class CallEnded implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public readonly Call $call) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('call.'.$this->call->uuid);
    }

    public function broadcastAs(): string
    {
        return 'call.ended';
    }
}
