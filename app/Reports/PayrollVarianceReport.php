<?php

declare(strict_types=1);

namespace App\Reports;

use App\Models\PayrollRun;
use App\Models\Payslip;
use Illuminate\Support\Collection;

/**
 * Compares a payroll run with the run before it, employee by employee, so an
 * unexpected jump, a new joiner or someone missing is visible before approval.
 */
class PayrollVarianceReport extends BaseReport
{
    private const FLAG_PERCENT = 10.0;

    public function key(): string
    {
        return 'payroll-variance';
    }

    public function name(): string
    {
        return 'Payroll variance vs previous run';
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
        return ['Employee', 'Number', 'Previous gross', 'Gross', 'Gross change', 'Previous net', 'Net', 'Net change', 'Flag'];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return iterable<int, array<int, string|int|float|null>>
     */
    public function rows(array $filters): iterable
    {
        [$current, $previous] = $this->runs($filters);

        if ($current === null) {
            return [];
        }

        $now = $this->slips($current);
        $before = $previous === null ? collect() : $this->slips($previous);
        $rows = [];

        foreach ($now as $employeeId => $slip) {
            $old = $before->get($employeeId);
            $flag = $old === null ? ($previous === null ? '' : 'New in this run') : $this->flag($old->gross_earnings_cents, $slip->gross_earnings_cents);

            $rows[] = $this->row($slip, $old, $flag);
        }

        foreach ($before->diffKeys($now) as $old) {
            $rows[] = [$this->employeeName($old), $old->employee_number, $this->money($old->gross_earnings_cents), $this->money(0), $this->money(-$old->gross_earnings_cents), $this->money($old->net_pay_cents), $this->money(0), $this->money(-$old->net_pay_cents), 'Missing from this run'];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string|int|float>
     */
    public function summary(array $filters): array
    {
        [$current, $previous] = $this->runs($filters);

        if ($current === null) {
            return [];
        }

        return [
            'Run' => $current->uuid,
            'Gross' => $this->money($current->gross_cents),
            'Previous gross' => $previous === null ? 'n/a' : $this->money($previous->gross_cents),
            'Net' => $this->money($current->net_cents),
            'Previous net' => $previous === null ? 'n/a' : $this->money($previous->net_cents),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: PayrollRun|null, 1: PayrollRun|null}
     */
    private function runs(array $filters): array
    {
        $uuid = $this->str($filters, 'run_uuid');
        $current = $uuid === null ? null : PayrollRun::query()->with('period')->where('uuid', $uuid)->first();

        if ($current === null) {
            return [null, null];
        }

        $previous = PayrollRun::query()
            ->with('period')
            ->whereHas('period', fn ($q) => $q->regular()->whereDate('start_date', '<', $current->period->start_date->toDateString()))
            ->whereNotIn('status', ['draft'])
            ->get()
            ->sortByDesc(fn (PayrollRun $run) => $run->period->start_date)
            ->first();

        return [$current, $previous];
    }

    /**
     * @return Collection<int, Payslip>
     */
    private function slips(PayrollRun $run): Collection
    {
        return Payslip::query()->where('payroll_run_id', $run->getKey())->with('employee:id,first_name,last_name')->get()->keyBy('employee_id');
    }

    private function employeeName(Payslip $slip): string
    {
        return $slip->employee?->fullName() ?? '#'.$slip->employee_id;
    }

    private function flag(int $old, int $new): string
    {
        if ($old === 0) {
            return $new === 0 ? '' : 'Changed';
        }

        $percent = abs($new - $old) / $old * 100;

        return $percent >= self::FLAG_PERCENT ? sprintf('%s%.1f%%', $new > $old ? '+' : '-', $percent) : '';
    }

    /**
     * @return array<int, string|int|float|null>
     */
    private function row(Payslip $slip, ?Payslip $old, string $flag): array
    {
        $oldGross = $old?->gross_earnings_cents ?? 0;
        $oldNet = $old?->net_pay_cents ?? 0;

        return [
            $this->employeeName($slip), $slip->employee_number,
            $old === null ? '' : $this->money($oldGross), $this->money($slip->gross_earnings_cents), $this->money($slip->gross_earnings_cents - $oldGross),
            $old === null ? '' : $this->money($oldNet), $this->money($slip->net_pay_cents), $this->money($slip->net_pay_cents - $oldNet),
            $flag,
        ];
    }
}
