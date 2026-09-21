<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Livewire\Payroll\PayRulesSettings;
use App\Livewire\Payroll\Runs;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Services\Payroll\PayrollService;
use App\Support\Payroll\PayRules;
use App\Support\TenantContext;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = tenantWithRoles();
    app(TenantContext::class)->set($this->tenant);
    $this->period = PayrollPeriod::factory()->forTenant($this->tenant)->forMonth(2026, 9)->create();
});

it('defaults to attendance-based pay with 1.5× / 2× overtime', function () {
    $rules = PayRules::for($this->tenant);

    expect($rules->salaryBasis)->toBe('attendance')
        ->and($rules->overtimeEnabled)->toBeTrue()
        ->and($rules->overtimeMultiplier)->toBe(1.5)
        ->and($rules->restDayMultiplier)->toBe(2.0);
});

it('lets a payroll manager change the pay rules and audits it', function () {
    $this->actingAs(userForTenant($this->tenant, Role::PayrollManager));

    Livewire::test(PayRulesSettings::class)
        ->set('salary_basis', 'fixed')
        ->set('overtime_multiplier', 1.75)
        ->set('rest_day_multiplier', 2.5)
        ->set('standard_daily_hours', 9)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('saved', true);

    $rules = PayRules::for($this->tenant->fresh());

    expect($rules->salaryBasis)->toBe('fixed')
        ->and($rules->overtimeMultiplier)->toBe(1.75)
        ->and($rules->restDayMultiplier)->toBe(2.5)
        ->and($rules->standardDailyHours)->toBe(9.0)
        ->and(AuditLog::query()->where('event', 'payroll.pay_rules_updated')->exists())->toBeTrue();
});

it('rejects out-of-range multipliers', function () {
    $this->actingAs(userForTenant($this->tenant, Role::PayrollManager));

    Livewire::test(PayRulesSettings::class)
        ->set('overtime_multiplier', 0.5)
        ->call('save')
        ->assertHasErrors(['overtime_multiplier' => 'min']);
});

it('forbids saving pay rules without payroll.manage', function () {
    $this->actingAs(userForTenant($this->tenant, Role::Employee));

    Livewire::test(PayRulesSettings::class)->assertForbidden();
});

it('warns about employees paid for zero days before approval', function () {
    $this->actingAs(userForTenant($this->tenant, Role::PayrollManager));
    $absent = Employee::factory()->forTenant($this->tenant)->create(['first_name' => 'Nora', 'last_name' => 'Absent', 'salary_amount_cents' => 5_000_000]);
    $present = Employee::factory()->forTenant($this->tenant)->create(['salary_amount_cents' => 5_000_000]);
    Attendance::factory()->forTenant($this->tenant)->create([
        'employee_id' => $present->id, 'work_date' => '2026-09-01', 'status' => AttendanceStatus::Present, 'worked_minutes' => 480, 'overtime_minutes' => 0,
    ]);

    app(PayrollService::class)->run($this->period);

    Livewire::test(Runs::class)
        ->assertSee('Nora Absent')
        ->assertSee('paid for 0 days');
});

it('shows the attendance summary on the payslip', function () {
    $employee = Employee::factory()->forTenant($this->tenant)->create(['salary_amount_cents' => 5_000_000]);
    Attendance::factory()->forTenant($this->tenant)->create([
        'employee_id' => $employee->id, 'work_date' => '2026-09-01', 'status' => AttendanceStatus::Present, 'worked_minutes' => 600, 'overtime_minutes' => 120,
    ]);

    $run = app(PayrollService::class)->run($this->period);
    $payslip = Payslip::query()->where('payroll_run_id', $run->id)->where('employee_id', $employee->id)->with(['lines', 'employee'])->sole();

    $html = view('payroll.payslip', ['payslip' => $payslip, 'tenant' => $this->tenant])->render();

    expect($html)->toContain('attendance-based pay')
        ->toContain('1 of')
        ->toContain('Overtime: 2 h');
});
