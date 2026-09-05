<?php

declare(strict_types=1);

namespace App\Enums;

enum LoanStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Settled = 'settled';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
