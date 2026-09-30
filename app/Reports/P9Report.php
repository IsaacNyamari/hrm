<?php

declare(strict_types=1);

namespace App\Reports;

use App\Enums\PayrollRunStatus;
use App\Models\Employee;
use App\Models\Payslip;
use Illuminate\Support\Collection;

/**
 * Annual tax deduction card for one employee: what each approved payslip of the
 * year charged, month by month, with the year's totals. Only payslips from
 * approved, paid or locked runs count.
 */
class P9Report extends BaseReport
{
    public function key(): string
    {
        return 'p9';
    }

    public function name(): string
    {
        return 'P9 tax deduction card';
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
            ['key' => 'employee_number', 'label' => 'Employee number', 'type' => 'text', 'required' => true],
            ['key' => 'year', 'label' => 'Year', 'type' => 'number'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function columns(): array
    {
        return ['Month', 'Gross pay', 'NSSF', 'SHIF', 'Housing levy', 'Taxable pay', 'PAYE'];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return iterable<int, array<int, string|int|float|null>>
     */
    public function rows(array $filters): iterable
    {
        $slips = $this->payslips($filters);

        if ($slips->isEmpty()) {
            return [];
        }

        $totals = ['gross' => 0, 'nssf' => 0, 'shif' => 0, 'levy' => 0, 'taxable' => 0, 'paye' => 0];
        $rows = [];

        foreach ($slips as $slip) {
            $line = fn (string $code): int => (int) $slip->lines->where('code', $code)->sum('amount_cents');
            $figures = [
                'gross' => $slip->gross_earnings_cents,
                'nssf' => $line('NSSF'),
                'shif' => $line('SHIF'),
                'levy' => $line('HOUSING_LEVY'),
                'taxable' => $slip->taxable_income_cents,
                'paye' => $line('PAYE'),
            ];

            foreach ($figures as $key => $cents) {
                $totals[$key] += $cents;
            }

            $rows[] = [$slip->run->period->start_date->format('M Y'), ...array_map($this->money(...), array_values($figures))];
        }

        $rows[] = ['Total', ...array_map($this->money(...), array_values($totals))];

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string|int|float>
     */
    public function summary(array $filters): array
    {
        $employee = $this->employee($filters);

        return $employee === null ? [] : [
            'Employee' => $employee->fullName(),
            'Number' => $employee->employee_number,
            'KRA PIN' => (string) ($employee->statutoryDetail?->tax_pin ?? ''),
            'Year' => $this->int($filters, 'year') ?? now()->year,
        ];
    }

    private function employee(array $filters): ?Employee
    {
        $number = $this->str($filters, 'employee_number');

        return $number === null ? null : Employee::query()->with('statutoryDetail')->where('employee_number', $number)->first();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Payslip>
     */
    private function payslips(array $filters): Collection
    {
        $employee = $this->employee($filters);
        $year = $this->int($filters, 'year') ?? now()->year;

        if ($employee === null) {
            return collect();
        }

        return Payslip::query()
            ->where('employee_id', $employee->getKey())
            ->whereHas('run', fn ($q) => $q->whereIn('status', [PayrollRunStatus::Approved, PayrollRunStatus::Paid, PayrollRunStatus::Locked]))
            ->whereHas('run.period', fn ($q) => $q->whereYear('start_date', $year))
            ->with(['run.period', 'lines:id,payslip_id,code,amount_cents'])
            ->get()
            ->sortBy(fn (Payslip $slip) => $slip->run->period->start_date)
            ->values();
    }
}
