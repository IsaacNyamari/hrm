<?php

declare(strict_types=1);

use App\Enums\LoanStatus;
use App\Enums\PayrollRunStatus;
use App\Enums\Role;
use App\Events\PayrollProcessed;
use App\Exceptions\PayrollException;
use App\Models\Employee;
use App\Models\Loan;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\SalaryComponent;
use App\Services\Payroll\PayrollService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->tenant = tenantWithRoles();
    // These tests cover the salary / statutory / loan arithmetic, so pay the
    // full contractual salary. Attendance-based pay is covered in PayrollAttendanceTest.
    $this->tenant->forceFill(['settings' => [...(array) $this->tenant->settings, 'payroll' => ['salary_basis' => 'fixed']]])->save();
    app(TenantContext::class)->set($this->tenant);
    $this->service = app(PayrollService::class);

    $this->employee = Employee::factory()->forTenant($this->tenant)->create([
        'salary_amount_cents' => 10_000_000, // KES 100,000
        'salary_currency' => 'KES',
    ]);

    $this->period = PayrollPeriod::factory()->forTenant($this->tenant)->forMonth(2026, 9)->create();
});

it('computes a payslip: basic + percentage allowance, statutory deductions and net', function () {
    Event::fake([PayrollProcessed::class]);

    $house = SalaryComponent::query()->where('code', 'HOUSE_ALLOWANCE')->firstOrFail(); // 15% of BASIC
    $this->employee->salaryComponents()->create([
        'tenant_id' => $this->tenant->id, 'salary_component_id' => $house->id,
        'effective_from' => '2026-01-01',
    ]);

    $run = $this->service->run($this->period);

    expect($run->status)->toBe(PayrollRunStatus::Processed)
        ->and($run->employee_count)->toBe(1);

    $payslip = Payslip::query()->where('payroll_run_id', $run->id)->firstOrFail();

    // gross = 100,000 + 15,000 = 115,000
    expect($payslip->gross_earnings_cents)->toBe(11_500_000);

    $lines = $payslip->lines->keyBy('code');
    expect($lines['BASIC']->amount_cents)->toBe(10_000_000)
        ->and($lines['HOUSE_ALLOWANCE']->amount_cents)->toBe(1_500_000)
        ->and($lines['NSSF']->amount_cents)->toBe(600_000)
        ->and($lines['SHIF']->amount_cents)->toBe((int) round(11_500_000 * 0.0275))
        ->and($lines['HOUSING_LEVY']->amount_cents)->toBe((int) round(11_500_000 * 0.015));

    // net = gross - all deductions
    $expectedNet = $payslip->gross_earnings_cents - $payslip->total_deductions_cents;
    expect($payslip->net_pay_cents)->toBe($expectedNet)
        ->and($payslip->total_deductions_cents)->toBe($payslip->statutory_deductions_cents + $payslip->other_deductions_cents);

    Event::assertDispatched(PayrollProcessed::class);
});

it('walks the run lifecycle and locks it', function () {
    $run = $this->service->run($this->period);
    $approver = userForTenant($this->tenant, Role::PayrollManager);

    $this->service->approve($run, $approver);
    expect($run->fresh()->status)->toBe(PayrollRunStatus::Approved)
        ->and($this->period->fresh()->status->value)->toBe('approved');

    $this->service->markPaid($run->fresh());
    expect($run->fresh()->status)->toBe(PayrollRunStatus::Paid);

    $this->service->lock($run->fresh());
    expect($run->fresh()->status)->toBe(PayrollRunStatus::Locked);
});

it('refuses to re-run a locked payroll', function () {
    $run = $this->service->run($this->period);
    $approver = userForTenant($this->tenant, Role::PayrollManager);
    $this->service->approve($run, $approver);
    $this->service->lock($run->fresh());

    $this->service->run($this->period->fresh());
})->throws(PayrollException::class);

it('recomputes cleanly when re-run while still processed', function () {
    $this->service->run($this->period);
    $first = Payslip::query()->where('payroll_run_id', $this->period->fresh()->runs()->first()->id)->count();

    // add another employee then re-run
    Employee::factory()->forTenant($this->tenant)->create(['salary_amount_cents' => 5_000_000]);
    $run = $this->service->run($this->period->fresh());

    expect($first)->toBe(1)
        ->and($run->employee_count)->toBe(2)
        ->and(Payslip::query()->where('payroll_run_id', $run->id)->count())->toBe(2);
});

it('deducts a loan installment and reduces the outstanding balance', function () {
    $loan = Loan::factory()->forEmployee($this->employee)->create([
        'principal_cents' => 6_000_000, 'installments' => 6, 'installment_cents' => 1_000_000,
        'outstanding_cents' => 6_000_000, 'status' => LoanStatus::Active, 'start_date' => '2026-08-01',
    ]);

    $run = $this->service->run($this->period);
    $payslip = Payslip::query()->where('payroll_run_id', $run->id)->firstOrFail();

    expect($payslip->lines->firstWhere('code', 'LOAN-'.$loan->id)?->amount_cents)->toBe(1_000_000)
        ->and($loan->fresh()->outstanding_cents)->toBe(5_000_000)
        ->and($loan->repayments()->count())->toBe(1);
});

it('caps a loan installment to what is actually left of pay, so the books never show more repaid than was paid out', function () {
    // An installment far bigger than one paycheck can cover after statutory
    // deductions — without capping, this would still fully reduce
    // outstanding_cents while net pay is silently floored to 0 below.
    $loan = Loan::factory()->forEmployee($this->employee)->create([
        'principal_cents' => 20_000_000, 'installments' => 2, 'installment_cents' => 15_000_000,
        'outstanding_cents' => 20_000_000, 'status' => LoanStatus::Active, 'start_date' => '2026-08-01',
    ]);

    $run = $this->service->run($this->period);
    $payslip = Payslip::query()->where('payroll_run_id', $run->id)->firstOrFail();

    $availableForLoan = $payslip->gross_earnings_cents - $payslip->statutory_deductions_cents;
    $loanLine = $payslip->lines->firstWhere('code', 'LOAN-'.$loan->id);

    expect($loanLine->amount_cents)->toBe($availableForLoan)
        ->and($loanLine->amount_cents)->toBeLessThan(15_000_000)
        ->and($payslip->net_pay_cents)->toBe(0)
        ->and($loan->fresh()->outstanding_cents)->toBe(20_000_000 - $availableForLoan);
});

it('settles a loan on the final installment', function () {
    $loan = Loan::factory()->forEmployee($this->employee)->create([
        'principal_cents' => 800_000, 'installments' => 1, 'installment_cents' => 800_000,
        'outstanding_cents' => 800_000, 'status' => LoanStatus::Active, 'start_date' => '2026-08-01',
    ]);

    $this->service->run($this->period);

    expect($loan->fresh()->status)->toBe(LoanStatus::Settled)
        ->and($loan->fresh()->outstanding_cents)->toBe(0);
});

it('reverses loan repayments when a still-mutable run is re-run', function () {
    $loan = Loan::factory()->forEmployee($this->employee)->create([
        'principal_cents' => 6_000_000, 'installments' => 6, 'installment_cents' => 1_000_000,
        'outstanding_cents' => 6_000_000, 'status' => LoanStatus::Active, 'start_date' => '2026-08-01',
    ]);

    $this->service->run($this->period);
    expect($loan->fresh()->outstanding_cents)->toBe(5_000_000);

    $this->service->run($this->period->fresh());
    // still just one installment deducted, not two
    expect($loan->fresh()->outstanding_cents)->toBe(5_000_000)
        ->and($loan->repayments()->count())->toBe(1);
});
