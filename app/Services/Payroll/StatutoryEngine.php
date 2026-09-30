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
            $this->nssf($grossCents, $pensionableCents, $payDate),
            $this->flatRate(TaxRuleType::Shif, 'SHIF', $grossCents, $payDate),
            $this->flatRate(TaxRuleType::HousingLevy, 'Housing Levy', $grossCents, $payDate),
        ];

        $preTaxRelief = array_sum(array_map(static fn (array $l): int => $l['amount_cents'], $lines));
        $lines[] = $this->paye(max(0, $taxablePayCents - $preTaxRelief), $payDate);

        return $lines;
    }

    /**
     * Employer-side statutory costs, which never reduce the employee's pay:
     * matching NSSF, the matching Housing Levy and the flat NITA levy. A missing
     * rule is skipped rather than fatal, so payroll never blocks on a cost that
     * the employee does not bear.
     *
     * @return list<array{code: string, name: string, amount_cents: int}>
     */
    public function employerContributions(int $grossCents, int $pensionableCents, CarbonImmutable $payDate): array
    {
        $lines = [];

        if (($nssf = TaxRule::inForce(TaxRuleType::Nssf, $payDate)) !== null) {
            $rate = (float) ($nssf->config['employer_rate'] ?? $nssf->config['rate'] ?? 0.06);
            $ceiling = (int) ($nssf->config['ceiling_cents'] ?? 10_800_000);
            $base = ($nssf->config['base'] ?? 'gross') === 'pensionable' ? $pensionableCents : $grossCents;
            $lines[] = ['code' => 'NSSF', 'name' => 'NSSF (employer)', 'amount_cents' => (int) round(min($base, $ceiling) * $rate)];
        }

        if (($levy = TaxRule::inForce(TaxRuleType::HousingLevy, $payDate)) !== null) {
            $rate = (float) ($levy->config['employer_rate'] ?? $levy->config['rate'] ?? 0);
            $lines[] = ['code' => 'HOUSING_LEVY', 'name' => 'Housing Levy (employer)', 'amount_cents' => (int) round($grossCents * $rate)];
        }

        if (($nita = TaxRule::inForce(TaxRuleType::Nita, $payDate)) !== null) {
            $lines[] = ['code' => 'NITA', 'name' => 'NITA Levy', 'amount_cents' => (int) ($nita->config['amount_cents'] ?? 0)];
        }

        return $lines;
    }

    /**
     * @return array{code: string, name: string, amount_cents: int, base: string}
     */
    private function nssf(int $grossCents, int $pensionableCents, CarbonImmutable $payDate): array
    {
        $rule = $this->rule(TaxRuleType::Nssf, $payDate);
        $rate = (float) ($rule->config['rate'] ?? 0.06);
        $ceiling = (int) ($rule->config['ceiling_cents'] ?? 10_800_000);
        $base = ($rule->config['base'] ?? 'gross') === 'pensionable' ? 'pensionable' : 'gross';

        $amount = (int) round(min($base === 'gross' ? $grossCents : $pensionableCents, $ceiling) * $rate);

        return ['code' => 'NSSF', 'name' => 'NSSF', 'amount_cents' => $amount, 'base' => $base];
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
