<?php

declare(strict_types=1);

namespace App\Enums;

enum VacancyStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case OnHold = 'on_hold';
    case Filled = 'filled';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    /**
     * Accepts new applications.
     */
    public function isAcceptingApplications(): bool
    {
        return $this === self::Open;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Filled, self::Closed, self::Cancelled], true);
    }
}
