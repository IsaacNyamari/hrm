<?php

declare(strict_types=1);

namespace App\Enums;

enum ApplicationStatus: string
{
    case Active = 'active';
    case Hired = 'hired';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function isOpen(): bool
    {
        return $this === self::Active;
    }
}
