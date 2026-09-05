<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Statutory deduction schemes. Rates/bands live in `tax_rules.config` with
 * effective dates — never hard-coded — so a change is a data edit.
 */
enum TaxRuleType: string
{
    case Paye = 'paye';
    case Nssf = 'nssf';
    case Shif = 'shif';
    case HousingLevy = 'housing_levy';
    case Pension = 'pension';

    public function label(): string
    {
        return match ($this) {
            self::Paye => 'PAYE',
            self::Nssf => 'NSSF',
            self::Shif => 'SHIF',
            self::HousingLevy => 'Housing Levy',
            self::Pension => 'Pension',
        };
    }
}
