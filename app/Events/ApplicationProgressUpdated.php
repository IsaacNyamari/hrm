<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Application;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Something about an application changed. Two audiences listen:
 *
 * - the candidate's tracking page, on a public channel named after the
 *   tracking code (the payload carries no personal data; the page re-reads
 *   the application with the code and email it already holds);
 * - the organization's recruitment board, on a private tenant channel.
 */
class ApplicationProgressUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly string $trackingCode,
        public readonly int $tenantId,
        public readonly string $stage,
        public readonly string $status,
    ) {}

    public static function for(Application $application): self
    {
        return new self($application->tracking_code, $application->tenant_id, $application->stage, $application->status->value);
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('application.'.$this->trackingCode),
            new PrivateChannel('tenant.'.$this->tenantId.'.recruitment'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'application.updated';
    }

    /**
     * @return array{stage: string, status: string}
     */
    public function broadcastWith(): array
    {
        return ['stage' => $this->stage, 'status' => $this->status];
    }
}
