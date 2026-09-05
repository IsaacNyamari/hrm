<?php

declare(strict_types=1);

namespace App\Enums;

enum LeaveStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function isOpen(): bool
    {
        return $this === self::Pending;
    }

    public function consumesBalance(): bool
    {
        return $this === self::Pending || $this === self::Approved;
    }
}
