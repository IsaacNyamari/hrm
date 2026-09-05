<?php

declare(strict_types=1);

namespace App\Enums;

enum ReviewStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Acknowledged = 'acknowledged';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    /**
     * The reviewer may still edit / submit while in this state.
     */
    public function isEditable(): bool
    {
        return $this === self::Pending || $this === self::InProgress;
    }

    public function isSubmitted(): bool
    {
        return $this === self::Submitted || $this === self::Acknowledged;
    }
}
