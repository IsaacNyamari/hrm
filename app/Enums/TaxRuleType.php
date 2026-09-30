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
    case Nita = 'nita';

    /**
     * Rule types payroll cannot run without. NITA and Pension are optional.
     *
     * @return list<self>
     */
    public static function required(): array
    {
        return [self::Paye, self::Nssf, self::Shif, self::HousingLevy];
    }

    /**
     * Validation for the `config` of a rule of this type. Money is in cents and
     * rates are fractions (0.06 = 6%), exactly as the statutory engine reads them.
     *
     * @return array<string, list<string>>
     */
    public function configRules(): array
    {
        return match ($this) {
            self::Paye => [
                'config.bands' => ['required', 'array', 'min:2'],
                'config.bands.*.upto_cents' => ['nullable', 'integer', 'min:1'],
                'config.bands.*.rate' => ['required', 'numeric', 'min:0', 'max:1'],
                'config.personal_relief_cents' => ['required', 'integer', 'min:0'],
            ],
            self::Nssf => [
                'config.rate' => ['required', 'numeric', 'min:0', 'max:0.5'],
                'config.employer_rate' => ['nullable', 'numeric', 'min:0', 'max:0.5'],
                'config.ceiling_cents' => ['required', 'integer', 'min:1'],
                'config.base' => ['nullable', 'in:gross,pensionable'],
            ],
            self::Shif => [
                'config.rate' => ['required', 'numeric', 'min:0', 'max:0.5'],
                'config.minimum_cents' => ['nullable', 'integer', 'min:0'],
            ],
            self::HousingLevy => [
                'config.rate' => ['required', 'numeric', 'min:0', 'max:0.5'],
                'config.employer_rate' => ['nullable', 'numeric', 'min:0', 'max:0.5'],
            ],
            self::Nita => [
                'config.amount_cents' => ['required', 'integer', 'min:0'],
            ],
            self::Pension => [
                'config.rate' => ['required', 'numeric', 'min:0', 'max:1'],
            ],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Paye => 'PAYE',
            self::Nssf => 'NSSF',
            self::Shif => 'SHIF',
            self::HousingLevy => 'Housing Levy',
            self::Pension => 'Pension',
            self::Nita => 'NITA Levy',
        };
    }
}
