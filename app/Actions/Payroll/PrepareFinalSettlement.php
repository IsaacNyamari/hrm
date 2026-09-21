<?php

declare(strict_types=1);

namespace App\Actions\Payroll;

use App\Enums\PayrollFrequency;
use App\Enums\PayrollRunStatus;
use App\Exceptions\PayrollException;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Payroll\PayrollService;
use App\Support\Auditor;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Pays an exited employee everything they are owed: pay accrued since their
 * last payroll up to the exit date, untaken annual leave, less statutory
 * deductions and any outstanding loan balance.
 *
 * The settlement is an off-cycle payroll period tied to the employee, so it
 * goes through the normal Run → Approve → Mark paid flow and payslip.
 */
class PrepareFinalSettlement
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly PayrollService $payroll,
        private readonly Auditor $auditor,
    ) {}

    public function handle(Employee $employee, ?User $by = null): PayrollRun
    {
        if ($employee->status->isCurrentlyEmployed() || $employee->exit_date === null) {
            throw new PayrollException('Set the employee as terminated, resigned or retired with an exit date first.');
        }

        return $this->context->run($employee->tenant, fn (): PayrollRun => DB::transaction(function () use ($employee, $by): PayrollRun {
            $exit = $employee->exit_date;

            $period = PayrollPeriod::query()->where('employee_id', $employee->getKey())->first()
                ?? PayrollPeriod::query()->create([
                    'employee_id' => $employee->getKey(),
                    'name' => 'Final pay — '.$employee->fullName(),
                    'frequency' => PayrollFrequency::Custom,
                    'start_date' => $this->unpaidFrom($employee, $exit),
                    'end_date' => $exit,
                    'pay_date' => CarbonImmutable::today()->max($exit),
                ]);

            $run = $this->payroll->run($period, [], $by);

            $this->dropFromOpenRegularRuns($employee, $period);

            $this->auditor->log('payroll.final_settlement_prepared', $employee, [
                'period' => $period->uuid,
                'net_cents' => $run->net_cents,
            ]);

            return $run;
        }));
    }

    /**
     * First day not yet covered by a finalised regular payroll: the start of
     * the pay cycle the exit falls in, or the day after the last one paid.
     */
    private function unpaidFrom(Employee $employee, CarbonImmutable $exit): CarbonImmutable
    {
        $cycle = PayrollPeriod::query()->regular()
            ->whereDate('start_date', '<=', $exit->toDateString())
            ->whereDate('end_date', '>=', $exit->toDateString())
            ->first();

        $from = $cycle?->start_date ?? $exit->startOfMonth();

        $lastPaidEnd = PayrollPeriod::query()->regular()
            ->whereHas('runs', fn ($runs) => $runs
                ->whereNotIn('status', [PayrollRunStatus::Draft->value, PayrollRunStatus::Processed->value])
                ->whereHas('payslips', fn ($slips) => $slips->where('employee_id', $employee->getKey())))
            ->max('end_date');

        if ($lastPaidEnd !== null) {
            $from = $from->max(CarbonImmutable::parse($lastPaidEnd)->addDay());
        }

        return $from;
    }

    /**
     * Re-run any still-open regular period that already paid this employee for
     * the same days, so they are not paid twice (exited staff drop out of it).
     */
    private function dropFromOpenRegularRuns(Employee $employee, PayrollPeriod $settlement): void
    {
        PayrollPeriod::query()->regular()
            ->whereDate('start_date', '<=', $settlement->end_date->toDateString())
            ->whereDate('end_date', '>=', $settlement->start_date->toDateString())
            ->whereHas('runs', fn ($runs) => $runs
                ->whereIn('status', [PayrollRunStatus::Draft->value, PayrollRunStatus::Processed->value])
                ->whereHas('payslips', fn ($slips) => $slips->where('employee_id', $employee->getKey())))
            ->get()
            ->each(fn (PayrollPeriod $period) => $this->payroll->run($period));
    }
}
