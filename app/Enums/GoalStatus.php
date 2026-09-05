<?php

declare(strict_types=1);

namespace App\Enums;

enum GoalStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function isOpen(): bool
    {
        return $this === self::Draft || $this === self::Active;
    }
}
