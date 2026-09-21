<?php

declare(strict_types=1);

use App\Actions\Payroll\PrepareFinalSettlement;
use App\Enums\AttendanceStatus;
use App\Enums\EmploymentStatus;
use App\Enums\LoanStatus;
use App\Enums\PayrollRunStatus;
use App\Enums\Role;
use App\Exceptions\PayrollException;
use App\Livewire\Employees\Index as EmployeesIndex;
use App\Livewire\Payroll\FinalSettlements;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Loan;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Services\Leave\WorkingDayCalculator;
use App\Services\Payroll\PayrollService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = tenantWithRoles();
    app(TenantContext::class)->set($this->tenant);

    $this->employee = Employee::factory()->forTenant($this->tenant)->create([
        'salary_amount_cents' => 10_000_000, // KES 100,000 / month
        'salary_currency' => 'KES',
    ]);

    $calendar = app(WorkingDayCalculator::class);
    $start = CarbonImmutable::parse('2026-09-01');
    $end = CarbonImmutable::parse('2026-09-30');
    $weekend = $calendar->weekendDays();
    $holidays = $calendar->holidayDates($start, $end);
    $this->workingDates = collect(CarbonPeriod::create($start, $end))
        ->map(fn ($day) => CarbonImmutable::parse($day))
        ->reject(fn (CarbonImmutable $day) => $calendar->isNonWorking($day, $weekend, $holidays))
        ->map(fn (CarbonImmutable $day) => $day->toDateString())
        ->values();

    // Worked the first ten working days of September, then left.
    $this->workingDates->take(10)->each(fn (string $date) => Attendance::factory()->forTenant($this->tenant)->create([
        'employee_id' => $this->employee->id, 'work_date' => $date, 'status' => AttendanceStatus::Present, 'worked_minutes' => 480, 'overtime_minutes' => 0,
    ]));
});

function terminate(object $test, ?string $exitDate = null): void
{
    $test->employee->forceFill([
        'status' => EmploymentStatus::Terminated,
        'exit_date' => $exitDate ?? $test->workingDates[9],
    ])->save();
}

function settlementPayslip(PayrollRun $run): Payslip
{
    return Payslip::query()->where('payroll_run_id', $run->id)->with('lines')->sole();
}

it('pays a leaver for the days worked up to their exit date', function () {
    terminate($this);

    $run = app(PrepareFinalSettlement::class)->handle($this->employee);
    $payslip = settlementPayslip($run);
    $period = $run->period;

    expect($period->isFinalSettlement())->toBeTrue()
        ->and($period->employee_id)->toBe($this->employee->id)
        ->and($period->start_date->toDateString())->toBe('2026-09-01')
        ->and($period->end_date->toDateString())->toBe($this->workingDates[9])
        ->and($run->status)->toBe(PayrollRunStatus::Processed)
        ->and($payslip->lines->firstWhere('code', 'BASIC')->amount_cents)
        ->toBe((int) round(10_000_000 * 10 / $this->workingDates->count()))
        ->and($payslip->snapshot['final_settlement']['exit_date'])->toBe($this->workingDates[9]);
});

it('pays out untaken annual leave', function () {
    terminate($this);
    $annual = LeaveType::query()->where('code', 'ANNUAL')->first()
        ?? LeaveType::factory()->forTenant($this->tenant)->create(['code' => 'ANNUAL']);
    LeaveBalance::query()->create([
        'employee_id' => $this->employee->id, 'leave_type_id' => $annual->id, 'year' => 2026,
        'allocated_days' => 21, 'used_days' => 6,
    ]);

    $payslip = settlementPayslip(app(PrepareFinalSettlement::class)->handle($this->employee));

    expect($payslip->lines->firstWhere('code', 'LEAVE_PAY')->amount_cents)
        ->toBe((int) round(15 * 10_000_000 / $this->workingDates->count()))
        ->and($payslip->snapshot['final_settlement']['leave_days_encashed'])->toEqual(15);
});

it('recovers the whole outstanding loan balance', function () {
    terminate($this);
    $loan = Loan::factory()->forEmployee($this->employee)->create([
        'principal_cents' => 3_000_000, 'installments' => 6, 'installment_cents' => 500_000, 'outstanding_cents' => 1_500_000,
        'start_date' => '2026-01-01',
    ]);

    $payslip = settlementPayslip(app(PrepareFinalSettlement::class)->handle($this->employee));

    expect($payslip->lines->firstWhere('code', 'LOAN-'.$loan->id)->amount_cents)->toBe(1_500_000)
        ->and($loan->fresh()->status)->toBe(LoanStatus::Settled);
});

it('does not pay again for days a finalised regular run already covered', function () {
    $september = PayrollPeriod::factory()->forTenant($this->tenant)->forMonth(2026, 9)->create();
    $service = app(PayrollService::class);
    $run = $service->run($september);
    $service->approve($run, userForTenant($this->tenant, Role::PayrollManager));
    terminate($this, $this->workingDates[15]);

    $payslip = settlementPayslip(app(PrepareFinalSettlement::class)->handle($this->employee));

    expect($payslip->lines->firstWhere('code', 'BASIC')->amount_cents)->toBe(0);
});

it('takes a leaver out of an open regular run for the same days', function () {
    $september = PayrollPeriod::factory()->forTenant($this->tenant)->forMonth(2026, 9)->create();
    $regular = app(PayrollService::class)->run($september);
    expect($regular->payslips()->where('employee_id', $this->employee->id)->exists())->toBeTrue();

    terminate($this);
    app(PrepareFinalSettlement::class)->handle($this->employee);

    expect($regular->fresh()->payslips()->where('employee_id', $this->employee->id)->exists())->toBeFalse();
});

it('refuses to settle someone who is still employed', function () {
    app(PrepareFinalSettlement::class)->handle($this->employee);
})->throws(PayrollException::class);

it('lets a payroll manager prepare final pay from the payroll page', function () {
    terminate($this);
    $this->actingAs(userForTenant($this->tenant, Role::PayrollManager));

    Livewire::test(FinalSettlements::class)
        ->assertSee($this->employee->fullName())
        ->assertSee('Not prepared')
        ->call('prepare', $this->employee->uuid)
        ->assertDispatched('payroll-updated')
        ->assertSee('Processed');

    expect(PayrollPeriod::query()->where('employee_id', $this->employee->id)->exists())->toBeTrue();
});

it('forbids preparing final pay without payroll.manage', function () {
    terminate($this);
    $this->actingAs(userForTenant($this->tenant, Role::FinanceManager)); // can view and approve payroll, not manage it

    Livewire::test(FinalSettlements::class)
        ->assertDontSee('Prepare final pay')
        ->call('prepare', $this->employee->uuid)
        ->assertForbidden();

    expect(PayrollPeriod::query()->where('employee_id', $this->employee->id)->exists())->toBeFalse();
});

it('defaults the exit date to today when an employee is terminated', function () {
    $this->travelTo('2026-09-21');
    $this->actingAs(userForTenant($this->tenant, Role::HrManager));

    Livewire::test(EmployeesIndex::class)
        ->call('startEdit', $this->employee->uuid)
        ->set('form.status', EmploymentStatus::Terminated->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($this->employee->fresh()->exit_date->toDateString())->toBe('2026-09-21');
});
