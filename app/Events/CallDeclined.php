<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Call;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A callee declined before ever joining the call's presence channel, so the
 * caller (already waiting there) would otherwise never find out.
 */
class CallDeclined implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly Call $call,
        public readonly string $declinedByName,
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('call.'.$this->call->uuid);
    }

    public function broadcastAs(): string
    {
        return 'call.declined';
    }

    /**
     * @return array{name: string}
     */
    public function broadcastWith(): array
    {
        return ['name' => $this->declinedByName];
    }
}
