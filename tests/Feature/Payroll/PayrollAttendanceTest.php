<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Enums\CalculationMethod;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\SalaryComponent;
use App\Services\Leave\WorkingDayCalculator;
use App\Services\Payroll\PayrollService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

beforeEach(function () {
    $this->tenant = tenantWithRoles();
    app(TenantContext::class)->set($this->tenant);

    $this->employee = Employee::factory()->forTenant($this->tenant)->create([
        'salary_amount_cents' => 10_000_000, // KES 100,000 / month
        'salary_currency' => 'KES',
    ]);

    $this->period = PayrollPeriod::factory()->forTenant($this->tenant)->forMonth(2026, 9)->create();

    // Working dates exactly as the app's calendar sees them (weekends + holidays excluded).
    $calendar = app(WorkingDayCalculator::class);
    $weekend = $calendar->weekendDays();
    $holidays = $calendar->holidayDates($this->period->start_date, $this->period->end_date);
    $this->workingDates = collect(CarbonPeriod::create($this->period->start_date, $this->period->end_date))
        ->map(fn ($day) => CarbonImmutable::parse($day))
        ->reject(fn (CarbonImmutable $day) => $calendar->isNonWorking($day, $weekend, $holidays))
        ->map(fn (CarbonImmutable $day) => $day->toDateString())
        ->values();
    $this->restDate = collect(CarbonPeriod::create($this->period->start_date, $this->period->end_date))
        ->map(fn ($day) => CarbonImmutable::parse($day))
        ->first(fn (CarbonImmutable $day) => in_array($day->isoWeekday(), $weekend, true))
        ->toDateString();
});

function setPayRules(object $test, array $rules): void
{
    $test->tenant->forceFill(['settings' => [...(array) $test->tenant->settings, 'payroll' => $rules]])->save();
}

function attend(object $test, string $date, AttendanceStatus $status = AttendanceStatus::Present, int $workedMinutes = 480, int $overtimeMinutes = 0): void
{
    Attendance::factory()->forTenant($test->tenant)->create([
        'employee_id' => $test->employee->getKey(),
        'work_date' => $date,
        'status' => $status,
        'worked_minutes' => $workedMinutes,
        'overtime_minutes' => $overtimeMinutes,
    ]);
}

function payslipFor(object $test): Payslip
{
    $run = app(PayrollService::class)->run($test->period);

    return Payslip::query()->where('payroll_run_id', $run->id)->where('employee_id', $test->employee->id)->with('lines')->sole();
}

function assignOvertimeComponent(object $test, int $amountCents): void
{
    // Every organization is provisioned with an OVERTIME component; make it a fixed amount.
    $component = SalaryComponent::query()->where('code', 'OVERTIME')->firstOrFail();
    $component->update(['calculation_method' => CalculationMethod::Fixed, 'amount_cents' => $amountCents, 'formula' => null]);

    EmployeeSalaryComponent::query()->create([
        'employee_id' => $test->employee->id, 'salary_component_id' => $component->id, 'effective_from' => '2026-01-01',
    ]);
}

function lineAmount(Payslip $payslip, string $code): ?int
{
    return $payslip->lines->firstWhere('code', $code)?->amount_cents;
}

it('prorates basic salary by the days the employee was at work', function () {
    $working = $this->workingDates->count();
    $this->workingDates->take(11)->each(fn (string $date) => attend($this, $date));

    $payslip = payslipFor($this);

    expect(lineAmount($payslip, 'BASIC'))->toBe((int) round(10_000_000 * 11 / $working))
        ->and($payslip->lines->firstWhere('code', 'BASIC')->name)->toBe("Basic Salary (11 of {$working} days)")
        ->and($payslip->snapshot['attendance']['payable_days'])->toEqual(11)
        ->and($payslip->snapshot['salary_basis'])->toBe('attendance');
});

it('pays the full basic when present on every working day', function () {
    $this->workingDates->each(fn (string $date) => attend($this, $date));

    expect(lineAmount(payslipFor($this), 'BASIC'))->toBe(10_000_000);
});

it('counts approved paid leave as a paid day but not unpaid leave', function () {
    $working = $this->workingDates->count();
    $this->workingDates->take(10)->each(fn (string $date) => attend($this, $date));

    $paid = LeaveType::factory()->forTenant($this->tenant)->create();
    $unpaid = LeaveType::factory()->forTenant($this->tenant)->unpaid()->create();

    LeaveRequest::factory()->forEmployee($this->employee)->approved()->create([
        'leave_type_id' => $paid->id, 'start_date' => $this->workingDates[10], 'end_date' => $this->workingDates[11], 'days' => 2,
    ]);
    LeaveRequest::factory()->forEmployee($this->employee)->approved()->create([
        'leave_type_id' => $unpaid->id, 'start_date' => $this->workingDates[12], 'end_date' => $this->workingDates[12], 'days' => 1,
    ]);

    $payslip = payslipFor($this);

    expect(lineAmount($payslip, 'BASIC'))->toBe((int) round(10_000_000 * 12 / $working))
        ->and($payslip->snapshot['attendance']['paid_leave_days'])->toEqual(2);
});

it('counts a half day as half a paid day', function () {
    $working = $this->workingDates->count();
    $this->workingDates->take(4)->each(fn (string $date) => attend($this, $date));
    attend($this, $this->workingDates[4], AttendanceStatus::HalfDay, 240);

    expect(lineAmount(payslipFor($this), 'BASIC'))->toBe((int) round(10_000_000 * 4.5 / $working));
});

it('pays overtime at 1.5× on working days and 2× on rest days', function () {
    $working = $this->workingDates->count();

    // Every working day attended; one of them with 3 hours of overtime.
    $this->workingDates->each(fn (string $date, int $i) => attend($this, $date, workedMinutes: $i === 0 ? 660 : 480, overtimeMinutes: $i === 0 ? 180 : 0));
    // 4 hours worked on a rest day.
    attend($this, $this->restDate, workedMinutes: 240);

    $payslip = payslipFor($this);
    $hourly = 10_000_000 / ($working * 8);

    expect(lineAmount($payslip, 'OVERTIME'))->toBe((int) round($hourly * (3 * 1.5 + 4 * 2.0)))
        ->and($payslip->lines->firstWhere('code', 'OVERTIME')->name)->toBe('Overtime (3 h × 1.5, 4 h rest day × 2)')
        ->and($payslip->lines->firstWhere('code', 'OVERTIME')->is_taxable)->toBeTrue()
        ->and($payslip->gross_earnings_cents)->toBe(10_000_000 + lineAmount($payslip, 'OVERTIME'));
});

it('uses custom overtime multipliers from the pay rules', function () {
    setPayRules($this, ['overtime_multiplier' => 2, 'rest_day_multiplier' => 3]);
    $working = $this->workingDates->count();
    attend($this, $this->workingDates[0], workedMinutes: 540, overtimeMinutes: 60);

    expect(lineAmount(payslipFor($this), 'OVERTIME'))->toBe((int) round(10_000_000 / ($working * 8) * 2));
});

it('pays the full basic on the fixed basis but still adds overtime', function () {
    setPayRules($this, ['salary_basis' => 'fixed']);
    attend($this, $this->workingDates[0], workedMinutes: 600, overtimeMinutes: 120);

    $payslip = payslipFor($this);

    expect(lineAmount($payslip, 'BASIC'))->toBe(10_000_000)
        ->and(lineAmount($payslip, 'OVERTIME'))->toBeGreaterThan(0)
        ->and($payslip->snapshot['salary_basis'])->toBe('fixed');
});

it('does not add overtime when it is switched off', function () {
    setPayRules($this, ['overtime_enabled' => false]);
    attend($this, $this->workingDates[0], workedMinutes: 600, overtimeMinutes: 120);

    expect(lineAmount(payslipFor($this), 'OVERTIME'))->toBeNull();
});

it('replaces an assigned OVERTIME component with attendance overtime, so it is never paid twice', function () {
    assignOvertimeComponent($this, 123_400);
    attend($this, $this->workingDates[0], workedMinutes: 600, overtimeMinutes: 120);

    $payslip = payslipFor($this);

    expect($payslip->lines->where('code', 'OVERTIME'))->toHaveCount(1)
        ->and(lineAmount($payslip, 'OVERTIME'))->not->toBe(123_400)
        ->and($payslip->lines->firstWhere('code', 'OVERTIME')->name)->toStartWith('Overtime (2 h');
});

it('uses the assigned OVERTIME component as configured when automatic overtime is off', function () {
    setPayRules($this, ['overtime_enabled' => false]);
    assignOvertimeComponent($this, 123_400);
    attend($this, $this->workingDates[0], workedMinutes: 600, overtimeMinutes: 120);

    expect(lineAmount(payslipFor($this), 'OVERTIME'))->toBe(123_400);
});
it('pays hourly staff their rate for regular hours worked plus overtime', function () {
    $this->employee->update(['pay_frequency' => 'hourly', 'salary_amount_cents' => 50_000]); // KES 500 / hour
    $this->workingDates->take(5)->each(fn (string $date, int $i) => attend($this, $date, workedMinutes: $i === 0 ? 540 : 480, overtimeMinutes: $i === 0 ? 60 : 0));

    $payslip = payslipFor($this);

    expect(lineAmount($payslip, 'BASIC'))->toBe(40 * 50_000)
        ->and(lineAmount($payslip, 'OVERTIME'))->toBe((int) round(50_000 * 1.5))
        ->and($payslip->snapshot['salary_basis'])->toBe('hourly');
});
