<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonImmutable;

enum BillingInterval: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case Lifetime = 'lifetime';

    /**
     * A lifetime plan is paid once; its period end is a far-future sentinel so
     * the renewal and expiry logic never trips over it.
     */
    public const LIFETIME_YEARS = 100;

    public function advance(CarbonImmutable $from): CarbonImmutable
    {
        return match ($this) {
            self::Monthly => $from->addMonthNoOverflow(),
            self::Yearly => $from->addYearNoOverflow(),
            self::Lifetime => $from->addYears(self::LIFETIME_YEARS),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Yearly => 'Yearly',
            self::Lifetime => 'Lifetime',
        };
    }

    public function isLifetime(): bool
    {
        return $this === self::Lifetime;
    }

    /**
     * Suffix shown after a price, e.g. "/ month".
     */
    public function perLabel(): string
    {
        return match ($this) {
            self::Monthly => '/ '.__('month'),
            self::Yearly => '/ '.__('year'),
            self::Lifetime => __('one-time'),
        };
    }
}
