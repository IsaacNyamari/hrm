<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonImmutable;

enum BillingInterval: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function advance(CarbonImmutable $from): CarbonImmutable
    {
        return match ($this) {
            self::Monthly => $from->addMonthNoOverflow(),
            self::Yearly => $from->addYearNoOverflow(),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Yearly => 'Yearly',
        };
    }
}
