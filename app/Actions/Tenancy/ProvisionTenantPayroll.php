<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Enums\CalculationMethod;
use App\Enums\SalaryComponentType;
use App\Enums\TaxRuleType;
use App\Models\SalaryComponent;
use App\Models\TaxRule;
use App\Models\Tenant;
use App\Support\TenantContext;

/**
 * Seeds a tenant's default salary components and the Kenyan statutory tax rules.
 * Rates/bands are DATA (editable, effective-dated) — nothing is hard-coded into
 * the payroll engine. Idempotent.
 */
class ProvisionTenantPayroll
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Tenant $tenant): void
    {
        $this->context->run($tenant, function () use ($tenant): void {
            $this->components($tenant);
            $this->taxRules($tenant);
        });
    }

    private function components(Tenant $tenant): void
    {
        $defaults = [
            ['BASIC', 'Basic Salary', SalaryComponentType::Earning, CalculationMethod::Fixed, null, null, null, null, true, true, false, true, 0],
            ['HOUSE_ALLOWANCE', 'House Allowance', SalaryComponentType::Earning, CalculationMethod::Percentage, null, 0.15, 'BASIC', null, true, false, false, true, 10],
            ['TRANSPORT_ALLOWANCE', 'Transport Allowance', SalaryComponentType::Earning, CalculationMethod::Fixed, 0, null, null, null, true, false, false, true, 20],
            ['OVERTIME', 'Overtime', SalaryComponentType::Earning, CalculationMethod::Formula, null, null, null, 'overtime_hours * overtime_rate', true, false, false, false, 30],
            ['BONUS', 'Bonus', SalaryComponentType::Earning, CalculationMethod::Fixed, 0, null, null, null, true, false, false, false, 40],
            ['PAYE', 'PAYE', SalaryComponentType::Deduction, CalculationMethod::Fixed, null, null, null, null, false, false, true, true, 100],
            ['NSSF', 'NSSF', SalaryComponentType::Deduction, CalculationMethod::Fixed, null, null, null, null, false, false, true, true, 110],
            ['SHIF', 'SHIF', SalaryComponentType::Deduction, CalculationMethod::Fixed, null, null, null, null, false, false, true, true, 120],
            ['HOUSING_LEVY', 'Housing Levy', SalaryComponentType::Deduction, CalculationMethod::Fixed, null, null, null, null, false, false, true, true, 130],
        ];

        foreach ($defaults as [$code, $name, $type, $method, $amount, $rate, $base, $formula, $taxable, $pensionable, $statutory, $recurring, $sort]) {
            SalaryComponent::query()->updateOrCreate(
                ['tenant_id' => $tenant->getKey(), 'code' => $code],
                [
                    'name' => $name,
                    'type' => $type,
                    'calculation_method' => $method,
                    'amount_cents' => $amount,
                    'rate' => $rate,
                    'base_code' => $base,
                    'formula' => $formula,
                    'is_taxable' => $taxable,
                    'is_pensionable' => $pensionable,
                    'is_statutory' => $statutory,
                    'is_recurring' => $recurring,
                    'sort_order' => $sort,
                    'is_active' => true,
                ],
            );
        }
    }

    private function taxRules(Tenant $tenant): void
    {
        $effectiveFrom = '2024-07-01';

        $rules = [
            [TaxRuleType::Paye, 'PAYE (Kenya)', [
                'bands' => [
                    ['upto_cents' => 2_400_000, 'rate' => 0.10],
                    ['upto_cents' => 3_233_300, 'rate' => 0.25],
                    ['upto_cents' => 50_000_000, 'rate' => 0.30],
                    ['upto_cents' => 80_000_000, 'rate' => 0.325],
                    ['upto_cents' => null, 'rate' => 0.35],
                ],
                'personal_relief_cents' => 240_000,
            ]],
            [TaxRuleType::Shif, 'SHIF (Kenya)', ['rate' => 0.0275, 'minimum_cents' => 30_000]],
            [TaxRuleType::HousingLevy, 'Housing Levy (Kenya)', ['rate' => 0.015, 'employer_rate' => 0.015]],
            [TaxRuleType::Nita, 'NITA Levy (Kenya)', ['amount_cents' => 5_000]],
        ];

        foreach ($rules as [$type, $name, $config]) {
            TaxRule::query()->updateOrCreate(
                ['tenant_id' => $tenant->getKey(), 'type' => $type->value, 'effective_from' => $effectiveFrom],
                ['name' => $name, 'country' => 'KE', 'effective_to' => null, 'config' => $config, 'is_active' => true],
            );
        }

        // NSSF Act 2013 phased upper earnings limit: KES 72,000 from Feb 2025, KES 108,000 from Feb 2026.
        foreach ([['2024-07-01', '2026-01-31', 7_200_000], ['2026-02-01', null, 10_800_000]] as [$from, $to, $ceilingCents]) {
            TaxRule::query()->updateOrCreate(
                ['tenant_id' => $tenant->getKey(), 'type' => TaxRuleType::Nssf->value, 'effective_from' => $from],
                ['name' => 'NSSF (Kenya)', 'country' => 'KE', 'effective_to' => $to, 'config' => ['rate' => 0.06, 'employer_rate' => 0.06, 'ceiling_cents' => $ceilingCents], 'is_active' => true],
            );
        }
    }
}
