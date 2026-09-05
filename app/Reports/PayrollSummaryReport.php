<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\PayrollRun;

class PayrollSummaryReport extends BaseReport
{
    public function key(): string
    {
        return 'payroll-summary';
    }

    public function name(): string
    {
        return 'Payroll run summary';
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
            ['key' => 'status', 'label' => 'Run status', 'type' => 'text'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function columns(): array
    {
        return ['Period', 'Status', 'Employees', 'Gross', 'Statutory', 'Deductions', 'Net'];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return iterable<int, array<int, string|int|float|null>>
     */
    public function rows(array $filters): iterable
    {
        $status = $this->str($filters, 'status');

        return PayrollRun::query()
            ->with('period:id,name,start_date')
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->get()
            ->map(fn (PayrollRun $run): array => [
                $run->period->name ?? '#'.$run->payroll_period_id,
                $run->status->value,
                $run->employee_count,
                $this->money($run->gross_cents),
                $this->money($run->statutory_cents),
                $this->money($run->deductions_cents),
                $this->money($run->net_cents),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string|int|float>
     */
    public function summary(array $filters): array
    {
        $runs = PayrollRun::query()->whereIn('status', ['approved', 'paid', 'locked'])->get();

        return [
            'Finalised runs' => $runs->count(),
            'Net paid' => $this->money((int) $runs->sum('net_cents')),
        ];
    }
}
