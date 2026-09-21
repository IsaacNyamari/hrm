<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Livewire\Payroll\EarningsToDate;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Services\Leave\WorkingDayCalculator;
use App\Services\Payroll\PayrollService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = tenantWithRoles();
    app(TenantContext::class)->set($this->tenant);
    $this->travelTo('2026-09-21');

    $this->employee = Employee::factory()->forTenant($this->tenant)->create(['first_name' => 'Amina', 'last_name' => 'Otieno', 'salary_amount_cents' => 10_000_000]);
    $this->period = PayrollPeriod::factory()->forTenant($this->tenant)->forMonth(2026, 9)->create();

    $calendar = app(WorkingDayCalculator::class);
    $weekend = $calendar->weekendDays();
    $holidays = $calendar->holidayDates($this->period->start_date, $this->period->end_date);
    $this->workingDates = collect(CarbonPeriod::create($this->period->start_date, $this->period->end_date))
        ->map(fn ($day) => CarbonImmutable::parse($day))
        ->reject(fn (CarbonImmutable $day) => $calendar->isNonWorking($day, $weekend, $holidays))
        ->map(fn (CarbonImmutable $day) => $day->toDateString())
        ->values();
});

it('accrues basic pay for each day attended so far in the cycle', function () {
    $this->workingDates->take(5)->each(fn (string $date) => Attendance::factory()->forTenant($this->tenant)->create([
        'employee_id' => $this->employee->id, 'work_date' => $date, 'status' => AttendanceStatus::Present, 'worked_minutes' => 480, 'overtime_minutes' => 0,
    ]));

    $estimate = app(PayrollService::class)->estimate($this->employee, $this->period, CarbonImmutable::today());

    expect($estimate['basic_cents'])->toBe((int) round(10_000_000 * 5 / $this->workingDates->count()))
        ->and($estimate['full_basic_cents'])->toBe(10_000_000);
});

it('grows as more days are worked', function () {
    $service = app(PayrollService::class);
    $attend = fn (string $date) => Attendance::factory()->forTenant($this->tenant)->create([
        'employee_id' => $this->employee->id, 'work_date' => $date, 'status' => AttendanceStatus::Present, 'worked_minutes' => 480, 'overtime_minutes' => 0,
    ]);

    $attend($this->workingDates[0]);
    $before = $service->estimate($this->employee, $this->period, CarbonImmutable::today())['gross_cents'];
    $attend($this->workingDates[1]);
    $after = $service->estimate($this->employee, $this->period, CarbonImmutable::today())['gross_cents'];

    expect($after)->toBeGreaterThan($before);
});

it('accrues by days elapsed on a fixed salary basis', function () {
    $this->tenant->forceFill(['settings' => [...(array) $this->tenant->settings, 'payroll' => ['salary_basis' => 'fixed']]])->save();
    $elapsed = $this->workingDates->filter(fn (string $date) => $date <= '2026-09-21')->count();

    $estimate = app(PayrollService::class)->estimate($this->employee, $this->period->fresh(), CarbonImmutable::today());

    expect($estimate['basic_cents'])->toBe((int) round(10_000_000 * $elapsed / $this->workingDates->count()));
});

it('shows each employee and the running total on the payroll page', function () {
    $this->workingDates->take(5)->each(fn (string $date) => Attendance::factory()->forTenant($this->tenant)->create([
        'employee_id' => $this->employee->id, 'work_date' => $date, 'status' => AttendanceStatus::Present, 'worked_minutes' => 480, 'overtime_minutes' => 0,
    ]));
    $this->actingAs(userForTenant($this->tenant, Role::PayrollManager));

    $expected = number_format(round(10_000_000 * 5 / $this->workingDates->count()) / 100, 2);

    Livewire::test(EarningsToDate::class)
        ->assertSee('Earned so far')
        ->assertSee('Amina Otieno')
        ->assertSee($expected);
});

it('is hidden from employees without payroll access', function () {
    $this->actingAs(userForTenant($this->tenant, Role::Employee));

    Livewire::test(EarningsToDate::class)->assertForbidden();
});

it('renders the accrual and final settlement panels on the payroll page', function () {
    $this->actingAs(userForTenant($this->tenant, Role::PayrollManager))
        ->get(route('payroll'))
        ->assertOk()
        ->assertSee('Earned so far')
        ->assertSee('Final settlements')
        ->assertSee('Amina Otieno');
});
