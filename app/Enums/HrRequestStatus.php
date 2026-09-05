<?php

declare(strict_types=1);

namespace App\Enums;

enum HrRequestStatus: string
{
    case Open = 'open';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    public function isTerminal(): bool
    {
        return $this === self::Resolved || $this === self::Closed;
    }
}
