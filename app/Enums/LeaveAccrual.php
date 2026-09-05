<?php

declare(strict_types=1);

namespace App\Enums;

enum LeaveAccrual: string
{
    case None = 'none';       // full annual entitlement granted up front
    case Monthly = 'monthly'; // 1/12 of the annual entitlement each month
    case Annual = 'annual';   // granted once at the start of the leave year

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
