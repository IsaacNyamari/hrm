<?php

declare(strict_types=1);

namespace App\Services\Payroll\StatutoryReturns;

use App\Enums\StatutoryReturnType;
use App\Models\PayrollRun;
use App\Models\Payslip;

/**
 * Turns a payroll run into the per-employee rows each statutory portal wants,
 * and validates the identifiers those portals reconcile on (KRA PIN, National
 * ID, NSSF and SHA member numbers) so a bad upload is caught before it is made.
 */
class StatutoryReturnBuilder
{
    private const KRA_PIN = '/^[AP]\d{9}[A-Z]$/';

    private const NATIONAL_ID = '/^[A-Z0-9]{6,12}$/';

    private const MEMBER_NUMBER = '/^[A-Z0-9\-\/]{5,20}$/';

    /**
     * @return array{
     *     name: string,
     *     rows: list<array<string, mixed>>,
     *     issues: array<string, list<array{employee: string, employee_number: string, field: string, message: string}>>
     * }
     */
    public function build(PayrollRun $run): array
    {
        $run->loadMissing('period');

        $rows = Payslip::query()
            ->where('payroll_run_id', $run->getKey())
            ->with(['employee.statutoryDetail', 'lines:id,payslip_id,code,amount_cents'])
            ->get()
            ->map(fn (Payslip $slip): array => $this->row($slip))
            ->sortBy('employee_number')
            ->values()
            ->all();

        $issues = [];

        foreach (StatutoryReturnType::cases() as $type) {
            $issues[$type->value] = $this->issues($type, $rows);
        }

        return ['name' => $run->period->start_date->format('Y-m'), 'rows' => $rows, 'issues' => $issues];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Payslip $slip): array
    {
        $line = fn (string $code): int => (int) $slip->lines->where('code', $code)->sum('amount_cents');
        $employee = $slip->employee;
        $detail = $employee?->statutoryDetail;

        $normalise = static fn (?string $value): string => strtoupper(trim((string) $value));

        return [
            'employee' => $employee?->fullName() ?? '#'.$slip->employee_id,
            'first_names' => trim(($employee->first_name ?? '').' '.($employee->middle_name ?? '')),
            'last_name' => (string) ($employee->last_name ?? ''),
            'employee_number' => $slip->employee_number,
            'national_id' => $normalise($detail?->national_id),
            'kra_pin' => $normalise($detail?->tax_pin),
            'nssf_number' => $normalise($detail?->nssf_number),
            'sha_number' => $normalise($detail?->shif_number),
            'basic_cents' => $line('BASIC'),
            'gross_cents' => $slip->gross_earnings_cents,
            'taxable_cents' => $slip->taxable_income_cents,
            'paye_cents' => $line('PAYE'),
            'nssf_cents' => $line('NSSF'),
            'shif_cents' => $line('SHIF'),
            'housing_levy_cents' => $line('HOUSING_LEVY'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{employee: string, employee_number: string, field: string, message: string}>
     */
    private function issues(StatutoryReturnType $type, array $rows): array
    {
        $issues = [];

        foreach ($rows as $row) {
            $problem = function (string $field, string $message) use (&$issues, $row): void {
                $issues[] = ['employee' => $row['employee'], 'employee_number' => $row['employee_number'], 'field' => $field, 'message' => $message];
            };

            if ($type === StatutoryReturnType::Kra && $row['paye_cents'] + $row['housing_levy_cents'] > 0) {
                $this->check($row['kra_pin'], self::KRA_PIN, 'KRA PIN', 'kra_pin', $problem);
            }

            if ($type === StatutoryReturnType::Nssf && $row['nssf_cents'] > 0) {
                $this->check($row['nssf_number'], self::MEMBER_NUMBER, 'NSSF number', 'nssf_number', $problem);
                $this->check($row['national_id'], self::NATIONAL_ID, 'National ID', 'national_id', $problem);
                $this->check($row['kra_pin'], self::KRA_PIN, 'KRA PIN', 'kra_pin', $problem);
            }

            if ($type === StatutoryReturnType::Sha && $row['shif_cents'] > 0) {
                $this->checkSha($row, $problem);
            }
        }

        return $issues;
    }

    /**
     * SHA reconciles on either identifier, so one valid value is enough.
     *
     * @param  array<string, mixed>  $row
     */
    private function checkSha(array $row, callable $problem): void
    {
        $idOk = preg_match(self::NATIONAL_ID, $row['national_id']) === 1;
        $memberOk = preg_match(self::MEMBER_NUMBER, $row['sha_number']) === 1;

        if ($idOk || $memberOk) {
            return;
        }

        $problem('national_id', $row['national_id'] === '' && $row['sha_number'] === ''
            ? 'Add a National ID or SHA/SHIF member number.'
            : 'National ID / SHA number is not in a valid format.');
    }

    private function check(string $value, string $pattern, string $label, string $field, callable $problem): void
    {
        if ($value === '') {
            $problem($field, "{$label} is missing.");
        } elseif (preg_match($pattern, $value) !== 1) {
            $problem($field, "{$label} '{$value}' is not in a valid format.");
        }
    }
}
