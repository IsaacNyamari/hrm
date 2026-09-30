<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\PayrollRun;
use App\Models\Payslip;

class PayrollRegisterReport extends BaseReport
{
    public function key(): string
    {
        return 'payroll-register';
    }

    public function name(): string
    {
        return 'Payroll register (statutory)';
    }

    public function category(): string
    {
        return 'Payroll';
    }

    /**
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return ['payroll.view'];
    }

    /**
     * @return array<int, array{key: string, label: string, type: string, required?: bool}>
     */
    public function filters(): array
    {
        return [
            ['key' => 'run_uuid', 'label' => 'Payroll run', 'type' => 'text', 'required' => true],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function columns(): array
    {
        return ['Employee', 'Number', 'Gross', 'PAYE', 'NSSF', 'SHIF', 'Housing levy', 'Other deductions', 'Net', 'Employer NSSF', 'Employer housing levy', 'NITA levy', 'Total employer cost'];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return iterable<int, array<int, string|int|float|null>>
     */
    public function rows(array $filters): iterable
    {
        $runUuid = $this->str($filters, 'run_uuid');

        if ($runUuid === null) {
            return [];
        }

        $run = PayrollRun::query()->where('uuid', $runUuid)->first();

        if ($run === null) {
            return [];
        }

        return Payslip::query()
            ->where('payroll_run_id', $run->getKey())
            ->with(['employee:id,first_name,last_name', 'lines:id,payslip_id,code,amount_cents,is_statutory'])
            ->get()
            ->map(function (Payslip $slip): array {
                $line = fn (string $code): int => (int) $slip->lines->firstWhere('code', $code)?->amount_cents;
                $namedStatutory = ['PAYE', 'NSSF', 'SHIF', 'HOUSING_LEVY'];
                $otherStatutory = (int) $slip->lines
                    ->where('is_statutory', true)
                    ->whereNotIn('code', $namedStatutory)
                    ->sum('amount_cents');
                $employer = collect(data_get($slip->snapshot, 'employer_contributions', []))->pluck('amount_cents', 'code');
                $name = data_get($slip, 'employee') !== null ? $slip->employee->fullName() : '#'.$slip->employee_id;

                return [
                    $name,
                    $slip->employee_number,
                    $this->money($slip->gross_earnings_cents),
                    $this->money($line('PAYE')),
                    $this->money($line('NSSF')),
                    $this->money($line('SHIF')),
                    $this->money($line('HOUSING_LEVY')),
                    $this->money($slip->other_deductions_cents + $otherStatutory),
                    $this->money($slip->net_pay_cents),
                    $this->money((int) $employer->get('NSSF')),
                    $this->money((int) $employer->get('HOUSING_LEVY')),
                    $this->money((int) $employer->get('NITA')),
                    $this->money((int) $employer->sum()),
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string|int|float>
     */
    public function summary(array $filters): array
    {
        $runUuid = $this->str($filters, 'run_uuid');
        $run = $runUuid !== null ? PayrollRun::query()->where('uuid', $runUuid)->first() : null;

        if ($run === null) {
            return [];
        }

        return [
            'Run' => $run->uuid,
            'Employees' => $run->employee_count,
            'Gross' => $this->money($run->gross_cents),
            'Net' => $this->money($run->net_cents),
        ];
    }
}
