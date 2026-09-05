<?php

declare(strict_types=1);

namespace App\Enums;

enum PayrollRunStatus: string
{
    case Draft = 'draft';
    case Processed = 'processed';
    case Approved = 'approved';
    case Paid = 'paid';
    case Locked = 'locked';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Whether the run's figures may still be recomputed / edited.
     */
    public function isMutable(): bool
    {
        return $this === self::Draft || $this === self::Processed;
    }

    public function isFinal(): bool
    {
        return $this === self::Paid || $this === self::Locked;
    }
}
