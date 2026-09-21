<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Enums\CalculationMethod;
use App\Enums\LoanStatus;
use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollRunStatus;
use App\Enums\PayslipStatus;
use App\Enums\SalaryComponentType;
use App\Events\PayrollApproved;
use App\Events\PayrollProcessed;
use App\Exceptions\PayrollException;
use App\Models\Employee;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Support\Payroll\PayrollContext;
use App\Support\Payroll\PayRules;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly FormulaEvaluator $formula,
        private readonly StatutoryEngine $statutory,
        private readonly AttendancePayCalculator $attendancePay,
    ) {}

    /**
     * Calculate (or recalculate) payroll for a period. Idempotent while the run
     * is Draft/Processed; refuses once Approved/Paid/Locked.
     *
     * @param  array<int, string>  $employeeUuids  restrict to these employees (empty = all active)
     */
    public function run(PayrollPeriod $period, array $employeeUuids = [], ?User $processedBy = null): PayrollRun
    {
        return $this->context->run($period->tenant, fn (): PayrollRun => DB::transaction(function () use ($period, $employeeUuids, $processedBy): PayrollRun {
            $run = $period->runs()->latest('id')->first();

            if ($run !== null && ! $run->status->isMutable()) {
                throw PayrollException::runLocked();
            }

            $run ??= PayrollRun::query()->create([
                'payroll_period_id' => $period->getKey(),
                'status' => PayrollRunStatus::Draft,
            ]);

            $this->rollBack($run);

            $employees = Employee::query()
                ->active()
                ->when($employeeUuids !== [], fn ($q) => $q->whereIn('uuid', $employeeUuids))
                ->with(['salaryComponents.component', 'loans', 'bankAccounts', 'statutoryDetail', 'department:id,name', 'designation:id,name'])
                ->get();

            $components = SalaryComponent::query()->get()->keyBy('code');

            $totals = ['gross' => 0, 'taxable' => 0, 'statutory' => 0, 'deductions' => 0, 'net' => 0];

            foreach ($employees as $employee) {
                $payslip = $this->buildPayslip($run, $period, $employee, $components);

                $totals['gross'] += $payslip->gross_earnings_cents;
                $totals['taxable'] += $payslip->taxable_income_cents;
                $totals['statutory'] += $payslip->statutory_deductions_cents;
                $totals['deductions'] += $payslip->total_deductions_cents;
                $totals['net'] += $payslip->net_pay_cents;
            }

            $run->forceFill([
                'status' => PayrollRunStatus::Processed,
                'employee_count' => $employees->count(),
                'gross_cents' => $totals['gross'],
                'taxable_cents' => $totals['taxable'],
                'statutory_cents' => $totals['statutory'],
                'deductions_cents' => $totals['deductions'],
                'net_cents' => $totals['net'],
                'processed_by' => $processedBy?->getKey(),
                'processed_at' => CarbonImmutable::now(),
            ])->save();

            $period->forceFill(['status' => PayrollPeriodStatus::Processed])->save();

            PayrollProcessed::dispatch($run);

            return $run;
        }));
    }

    public function approve(PayrollRun $run, User $approver): PayrollRun
    {
        return $this->context->run($run->tenant, function () use ($run, $approver): PayrollRun {
            if ($run->status !== PayrollRunStatus::Processed) {
                throw PayrollException::notProcessed();
            }

            $run->forceFill([
                'status' => PayrollRunStatus::Approved,
                'approved_by' => $approver->getKey(),
                'approved_at' => CarbonImmutable::now(),
            ])->save();

            $run->payslips()->update(['status' => PayslipStatus::Approved]);
            $run->period->forceFill(['status' => PayrollPeriodStatus::Approved])->save();

            PayrollApproved::dispatch($run);

            return $run;
        });
    }

    public function markPaid(PayrollRun $run): PayrollRun
    {
        return $this->context->run($run->tenant, function () use ($run): PayrollRun {
            if ($run->status !== PayrollRunStatus::Approved) {
                throw new PayrollException('Approve the payroll run before marking it paid.');
            }

            $run->forceFill(['status' => PayrollRunStatus::Paid, 'paid_at' => CarbonImmutable::now()])->save();
            $run->payslips()->update(['status' => PayslipStatus::Paid]);
            $run->period->forceFill(['status' => PayrollPeriodStatus::Paid])->save();

            return $run;
        });
    }

    public function lock(PayrollRun $run): PayrollRun
    {
        return $this->context->run($run->tenant, function () use ($run): PayrollRun {
            if (! in_array($run->status, [PayrollRunStatus::Approved, PayrollRunStatus::Paid], true)) {
                throw new PayrollException('Only an approved or paid run can be locked.');
            }

            $run->forceFill(['status' => PayrollRunStatus::Locked, 'locked_at' => CarbonImmutable::now()])->save();
            $run->period->forceFill(['status' => PayrollPeriodStatus::Locked])->save();

            return $run;
        });
    }

    /* -------------------------------------------------------------------- */

    /**
     * @param  Collection<string, SalaryComponent>  $components
     */
    private function buildPayslip(PayrollRun $run, PayrollPeriod $period, Employee $employee, Collection $components): Payslip
    {
        $rules = PayRules::for($period->tenant);
        $ctx = $this->context($employee, $period, $rules);
        $taxableCodes = [];
        $pensionableCodes = [];

        $lines = [];
        $sort = 0;

        // 1. Basic — prorated by attendance (or hours, for hourly staff) per the pay rules
        [$basicCents, $basicLabel] = $this->earnedBasic($ctx, $rules);
        $ctx->addEarning('BASIC', $basicCents);
        $taxableCodes['BASIC'] = true;
        $pensionableCodes['BASIC'] = true;
        $lines[] = ['type' => SalaryComponentType::Earning, 'code' => 'BASIC', 'name' => $basicLabel, 'amount_cents' => $basicCents, 'is_taxable' => true, 'is_statutory' => false, 'sort_order' => $sort++];

        // 2. Other recurring earnings, in sort order
        $assigned = $employee->salaryComponents
            ->filter(fn ($esc) => $esc->component !== null && $this->effective($esc->effective_from, $esc->effective_to, $ctx->payDate))
            ->sortBy(fn ($esc) => $esc->component->sort_order);

        foreach ($assigned as $esc) {
            $component = $esc->component;

            if ($component->code === 'BASIC' || $component->type !== SalaryComponentType::Earning) {
                continue;
            }

            // With automatic overtime on, OVERTIME is calculated from attendance below.
            if ($component->code === 'OVERTIME' && $rules->overtimeEnabled) {
                continue;
            }

            $cents = max(0, $this->amountFor($component, $esc->amount_cents, $esc->rate, $esc->formula, $ctx));
            $ctx->addEarning($component->code, $cents);
            $taxableCodes[$component->code] = $component->is_taxable;
            $pensionableCodes[$component->code] = $component->is_pensionable;

            $lines[] = ['type' => SalaryComponentType::Earning, 'code' => $component->code, 'name' => $component->name, 'amount_cents' => $cents, 'is_taxable' => $component->is_taxable, 'is_statutory' => false, 'sort_order' => $sort++];
        }

        // 2b. Overtime from attendance (replaces any assigned OVERTIME component).
        if ($rules->overtimeEnabled) {
            [$overtimeCents, $overtimeLabel] = $this->overtimePay($ctx, $rules);

            if ($overtimeCents > 0) {
                $ctx->addEarning('OVERTIME', $overtimeCents);
                $taxableCodes['OVERTIME'] = true;
                $pensionableCodes['OVERTIME'] = true;
                $lines[] = ['type' => SalaryComponentType::Earning, 'code' => 'OVERTIME', 'name' => $overtimeLabel, 'amount_cents' => $overtimeCents, 'is_taxable' => true, 'is_statutory' => false, 'sort_order' => $sort++];
            }
        }

        $gross = $ctx->grossCents();
        $taxable = array_sum(array_map(fn (string $code) => $taxableCodes[$code] ?? false ? $ctx->earnings[$code] : 0, array_keys($ctx->earnings)));
        $pensionable = array_sum(array_map(fn (string $code) => $pensionableCodes[$code] ?? false ? $ctx->earnings[$code] : 0, array_keys($ctx->earnings)));

        // 3. Statutory
        $statutoryLines = $this->statutory->compute($gross, $pensionable, $taxable, $ctx->payDate);
        $statutoryTotal = 0;
        foreach ($statutoryLines as $line) {
            $statutoryTotal += $line['amount_cents'];
            $lines[] = ['type' => SalaryComponentType::Deduction, 'code' => $line['code'], 'name' => $line['name'], 'amount_cents' => $line['amount_cents'], 'is_taxable' => false, 'is_statutory' => true, 'sort_order' => $sort++];
        }

        // 4. Other recurring deductions
        $otherDeductions = 0;
        foreach ($assigned as $esc) {
            $component = $esc->component;
            if ($component->type !== SalaryComponentType::Deduction || $component->is_statutory) {
                continue;
            }
            $cents = max(0, $this->amountFor($component, $esc->amount_cents, $esc->rate, $esc->formula, $ctx));
            $otherDeductions += $cents;
            $lines[] = ['type' => SalaryComponentType::Deduction, 'code' => $component->code, 'name' => $component->name, 'amount_cents' => $cents, 'is_taxable' => false, 'is_statutory' => false, 'sort_order' => $sort++];
        }

        // 5. Loans / advances
        $loanRepayments = [];
        foreach ($employee->loans as $loan) {
            if (! $loan->isRepaying() || $loan->start_date->greaterThan($ctx->payDate)) {
                continue;
            }
            $due = $loan->dueThisRun();
            if ($due <= 0) {
                continue;
            }
            $otherDeductions += $due;
            $loanRepayments[] = [$loan, $due];
            $lines[] = ['type' => SalaryComponentType::Deduction, 'code' => 'LOAN-'.$loan->getKey(), 'name' => $loan->type->label().' repayment', 'amount_cents' => $due, 'is_taxable' => false, 'is_statutory' => false, 'sort_order' => $sort++];
        }

        $totalDeductions = $statutoryTotal + $otherDeductions;
        $net = max(0, $gross - $totalDeductions);

        $payslip = Payslip::query()->create([
            'payroll_run_id' => $run->getKey(),
            'employee_id' => $employee->getKey(),
            'employee_number' => $employee->employee_number,
            'currency' => $employee->salary_currency ?? $period->tenant->currency,
            'gross_earnings_cents' => $gross,
            'taxable_income_cents' => $taxable,
            'statutory_deductions_cents' => $statutoryTotal,
            'other_deductions_cents' => $otherDeductions,
            'total_deductions_cents' => $totalDeductions,
            'net_pay_cents' => $net,
            'status' => PayslipStatus::Draft,
            'snapshot' => [
                'department' => $employee->department?->name,
                'designation' => $employee->designation?->name,
                'kra_pin' => $employee->statutoryDetail?->tax_pin,
                'bank' => $employee->bankAccounts->firstWhere('is_primary', true)?->maskedAccountNumber()
                    ?? $employee->bankAccounts->first()?->maskedAccountNumber(),
                'salary_basis' => $this->isHourly($employee) ? 'hourly' : $rules->salaryBasis,
                'attendance' => $ctx->attendance,
            ],
        ]);

        foreach ($lines as $line) {
            $payslip->lines()->create($line);
        }

        foreach ($loanRepayments as [$loan, $due]) {
            $this->applyLoanRepayment($loan, $payslip->getKey(), $due, $ctx->payDate);
        }

        return $payslip;
    }

    private function context(Employee $employee, PayrollPeriod $period, PayRules $rules): PayrollContext
    {
        $attendance = $this->attendancePay->summarize($employee, $period);
        $workingDays = (int) $attendance['working_days'];
        $basic = $this->basicSalaryCents($employee, $period->pay_date);

        // Hourly staff: the salary amount *is* the hourly rate.
        $hourlyRate = $this->isHourly($employee)
            ? (float) $basic
            : ($workingDays > 0 ? $basic / ($workingDays * $rules->standardDailyHours) : 0.0);

        return new PayrollContext(
            employee: $employee,
            period: $period,
            payDate: $period->pay_date,
            basicSalaryCents: $basic,
            workingDays: $workingDays,
            daysWorked: $attendance['present_days'],
            overtimeHours: $attendance['overtime_hours'],
            hourlyRate: $hourlyRate,
            attendance: $attendance,
            overtimeMultiplier: $rules->overtimeMultiplier,
        );
    }

    /**
     * Basic pay actually earned this period, with its payslip label.
     *
     * @return array{0: int, 1: string}
     */
    private function earnedBasic(PayrollContext $ctx, PayRules $rules): array
    {
        $attendance = $ctx->attendance;

        if ($this->isHourly($ctx->employee)) {
            $hours = (float) ($attendance['regular_hours'] ?? 0);

            return [(int) round($ctx->hourlyRate * $hours), sprintf('Basic Pay (%s h × %s)', $this->number($hours), number_format($ctx->hourlyRate / 100, 2))];
        }

        if (! $rules->isAttendanceBased() || $ctx->workingDays <= 0) {
            return [$ctx->basicSalaryCents, 'Basic Salary'];
        }

        $payable = (float) ($attendance['payable_days'] ?? 0);

        if ($payable >= $ctx->workingDays) {
            return [$ctx->basicSalaryCents, 'Basic Salary'];
        }

        return [
            (int) round($ctx->basicSalaryCents * $payable / $ctx->workingDays),
            sprintf('Basic Salary (%s of %d days)', $this->number($payable), $ctx->workingDays),
        ];
    }

    /**
     * Overtime from attendance: working-day overtime at the overtime rate, rest
     * day / public holiday hours at the rest-day rate.
     *
     * @return array{0: int, 1: string}
     */
    private function overtimePay(PayrollContext $ctx, PayRules $rules): array
    {
        $normal = (float) ($ctx->attendance['overtime_hours'] ?? 0);
        $restDay = (float) ($ctx->attendance['rest_day_hours'] ?? 0);

        $cents = (int) round($ctx->hourlyRate * ($normal * $rules->overtimeMultiplier + $restDay * $rules->restDayMultiplier));

        $parts = [];
        if ($normal > 0) {
            $parts[] = $this->number($normal).' h × '.$this->number($rules->overtimeMultiplier);
        }
        if ($restDay > 0) {
            $parts[] = $this->number($restDay).' h rest day × '.$this->number($rules->restDayMultiplier);
        }

        return [$cents, 'Overtime ('.implode(', ', $parts).')'];
    }

    private function isHourly(Employee $employee): bool
    {
        return $employee->pay_frequency === 'hourly';
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function basicSalaryCents(Employee $employee, CarbonImmutable $payDate): int
    {
        $assigned = $employee->salaryComponents->first(
            fn ($esc) => $esc->component?->code === 'BASIC'
                && $this->effective($esc->effective_from, $esc->effective_to, $payDate),
        );

        if ($assigned !== null) {
            return (int) ($assigned->amount_cents ?? $assigned->component->amount_cents ?? 0);
        }

        return (int) ($employee->salary_amount_cents ?? 0);
    }

    private function amountFor(SalaryComponent $component, ?int $amountOverride, ?float $rateOverride, ?string $formulaOverride, PayrollContext $ctx): int
    {
        return match ($component->calculation_method) {
            CalculationMethod::Fixed => (int) ($amountOverride ?? $component->amount_cents ?? 0),
            CalculationMethod::Percentage => (int) round(
                ($rateOverride ?? $component->rate ?? 0.0)
                * ($ctx->earnings[strtoupper((string) ($component->base_code ?? 'BASIC'))] ?? $ctx->basicSalaryCents),
            ),
            CalculationMethod::Formula => (int) round(
                $this->formula->evaluate((string) ($formulaOverride ?? $component->formula ?? '0'), $ctx->variables()),
            ),
        };
    }

    private function applyLoanRepayment(Loan $loan, int $payslipId, int $amount, CarbonImmutable $on): void
    {
        $loan->repayments()->create([
            'payslip_id' => $payslipId,
            'amount_cents' => $amount,
            'paid_on' => $on->toDateString(),
        ]);

        $remaining = $loan->outstanding_cents - $amount;
        $loan->forceFill([
            'outstanding_cents' => max(0, $remaining),
            'status' => $remaining <= 0 ? LoanStatus::Settled : $loan->status,
        ])->save();
    }

    /**
     * Undo a previous (still-mutable) run: reverse its loan repayments and
     * delete its payslips.
     */
    private function rollBack(PayrollRun $run): void
    {
        $payslipIds = $run->payslips()->pluck('id');

        if ($payslipIds->isEmpty()) {
            return;
        }

        LoanRepayment::query()->whereIn('payslip_id', $payslipIds)->get()->each(function (LoanRepayment $repayment): void {
            $loan = $repayment->loan;
            $loan->forceFill([
                'outstanding_cents' => $loan->outstanding_cents + $repayment->amount_cents,
                'status' => $loan->status === LoanStatus::Settled ? LoanStatus::Active : $loan->status,
            ])->save();
            $repayment->delete();
        });

        $run->payslips()->each(function (Payslip $payslip): void {
            $payslip->lines()->delete();
            $payslip->delete();
        });
    }

    private function effective(CarbonImmutable $from, ?CarbonImmutable $to, CarbonImmutable $on): bool
    {
        return $from->lessThanOrEqualTo($on) && ($to === null || $to->greaterThanOrEqualTo($on));
    }
}
