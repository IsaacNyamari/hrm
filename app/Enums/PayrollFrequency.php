<?php

declare(strict_types=1);

namespace App\Enums;

enum PayrollFrequency: string
{
    case Monthly = 'monthly';
    case SemiMonthly = 'semi_monthly';
    case BiWeekly = 'bi_weekly';
    case Weekly = 'weekly';
    case Custom = 'custom';

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    /**
     * Fraction of a monthly salary paid in one period of this frequency.
     */
    public function monthlyDivisor(): float
    {
        return match ($this) {
            self::Monthly, self::Custom => 1.0,
            self::SemiMonthly => 2.0,
            self::BiWeekly => 52 / 12 / 2,
            self::Weekly => 52 / 12,
        };
    }
}
