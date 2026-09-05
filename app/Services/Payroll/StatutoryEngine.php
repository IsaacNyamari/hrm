<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Enums\TaxRuleType;
use App\Exceptions\PayrollException;
use App\Models\TaxRule;
use Carbon\CarbonImmutable;

/**
 * Computes Kenyan statutory deductions entirely from `tax_rules` config in force
 * on the pay date. No rate is hard-coded here — a legislative change is a row
 * edit. Order matters: NSSF, SHIF and the Housing Levy are computed first and
 * are deductible before PAYE.
 */
class StatutoryEngine
{
    /**
     * @return list<array{code: string, name: string, amount_cents: int, base: string}>
     */
    public function compute(int $grossCents, int $pensionableCents, int $taxablePayCents, CarbonImmutable $payDate): array
    {
        $lines = [
            $this->nssf($pensionableCents, $payDate),
            $this->flatRate(TaxRuleType::Shif, 'SHIF', $grossCents, $payDate),
            $this->flatRate(TaxRuleType::HousingLevy, 'Housing Levy', $grossCents, $payDate),
        ];

        $preTaxRelief = array_sum(array_map(static fn (array $l): int => $l['amount_cents'], $lines));
        $lines[] = $this->paye(max(0, $taxablePayCents - $preTaxRelief), $payDate);

        return $lines;
    }

    /**
     * @return array{code: string, name: string, amount_cents: int, base: string}
     */
    private function nssf(int $pensionableCents, CarbonImmutable $payDate): array
    {
        $rule = $this->rule(TaxRuleType::Nssf, $payDate);
        $rate = (float) ($rule->config['rate'] ?? 0.06);
        $ceiling = (int) ($rule->config['ceiling_cents'] ?? 7_200_000);

        $amount = (int) round(min($pensionableCents, $ceiling) * $rate);

        return ['code' => 'NSSF', 'name' => 'NSSF', 'amount_cents' => $amount, 'base' => 'pensionable'];
    }

    /**
     * @return array{code: string, name: string, amount_cents: int, base: string}
     */
    private function flatRate(TaxRuleType $type, string $label, int $grossCents, CarbonImmutable $payDate): array
    {
        $rule = $this->rule($type, $payDate);
        $rate = (float) ($rule->config['rate'] ?? 0);
        $minimum = (int) ($rule->config['minimum_cents'] ?? 0);

        $amount = max($minimum, (int) round($grossCents * $rate));

        return ['code' => strtoupper($type->value), 'name' => $label, 'amount_cents' => $amount, 'base' => 'gross'];
    }

    /**
     * @return array{code: string, name: string, amount_cents: int, base: string}
     */
    private function paye(int $taxableCents, CarbonImmutable $payDate): array
    {
        $rule = $this->rule(TaxRuleType::Paye, $payDate);

        /** @var list<array{upto_cents: int|null, rate: float}> $bands */
        $bands = $rule->config['bands'] ?? [];
        $relief = (int) ($rule->config['personal_relief_cents'] ?? 0);

        $tax = 0.0;
        $previous = 0;

        foreach ($bands as $band) {
            $cap = $band['upto_cents'] ?? PHP_INT_MAX;
            $slice = max(0, min($taxableCents, $cap) - $previous);
            $tax += $slice * (float) $band['rate'];
            $previous = $cap;

            if ($taxableCents <= $cap) {
                break;
            }
        }

        $due = max(0, (int) round($tax) - $relief);

        return ['code' => 'PAYE', 'name' => 'PAYE', 'amount_cents' => $due, 'base' => 'taxable'];
    }

    private function rule(TaxRuleType $type, CarbonImmutable $payDate): TaxRule
    {
        $rule = TaxRule::inForce($type, $payDate);

        if ($rule === null) {
            throw PayrollException::noStatutoryRule($type->label());
        }

        return $rule;
    }
}
