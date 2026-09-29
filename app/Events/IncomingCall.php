<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Call;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Rings a single invitee's own notification channel — the only channel we
 * can be sure they're already listening on, since they haven't opened the
 * call itself yet (and may not even be on the Calls page).
 */
class IncomingCall implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly int $calleeUserId,
        public readonly string $callUuid,
        public readonly string $callerName,
        public readonly string $callerInitials,
        public readonly int $participantCount,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('App.Models.User.'.$this->calleeUserId);
    }

    public function broadcastAs(): string
    {
        return 'call.incoming';
    }

    /**
     * @return array{call_uuid: string, caller_name: string, caller_initials: string, participant_count: int}
     */
    public function broadcastWith(): array
    {
        return [
            'call_uuid' => $this->callUuid,
            'caller_name' => $this->callerName,
            'caller_initials' => $this->callerInitials,
            'participant_count' => $this->participantCount,
        ];
    }
}
