<?php

declare(strict_types=1);

use App\Enums\EmploymentStatus;
use App\Enums\Role;
use App\Enums\WebhookEvent;
use App\Jobs\DeliverWebhook;
use App\Livewire\Audit\Log as AuditLogPage;
use App\Livewire\Platform\AuditTrail;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Reports\HeadcountMovementReport;
use App\Reports\LeaveLiabilityReport;
use App\Reports\P9Report;
use App\Reports\PayrollVarianceReport;
use App\Services\Payroll\PayrollService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'aud-co']);
    $this->tenant->forceFill(['settings' => [...(array) $this->tenant->settings, 'payroll' => ['salary_basis' => 'fixed', 'require_separate_approver' => false]]])->save();
    app(TenantContext::class)->set($this->tenant);
    $this->admin = userForTenant($this->tenant, Role::TenantOwner);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create(['salary_amount_cents' => 10_000_000, 'first_name' => 'Amina', 'last_name' => 'Otieno']);
});

function approvedRun(object $test, int $year, int $month): PayrollRun
{
    $service = app(PayrollService::class);
    $run = $service->run(PayrollPeriod::factory()->forTenant($test->tenant)->forMonth($year, $month)->create());
    $service->approve($run, $test->admin);

    return $run->fresh();
}

it('builds a P9 card from approved payslips with yearly totals', function () {
    approvedRun($this, 2026, 8);
    approvedRun($this, 2026, 9);

    $rows = collect((new P9Report)->rows(['employee_number' => $this->employee->employee_number, 'year' => 2026]))->all();

    expect($rows)->toHaveCount(3)
        ->and($rows[0][0])->toBe('Aug 2026')
        ->and($rows[2][0])->toBe('Total')
        ->and($rows[0][1])->toBe('100000.00') // gross
        ->and($rows[0][6])->toBe('19308.35') // PAYE
        ->and($rows[2][6])->toBe('38616.70');
});

it('flags employees who are new, missing or moved more than 10 percent versus the previous run', function () {
    approvedRun($this, 2026, 8);
    $gone = $this->employee;
    $newcomer = Employee::factory()->forTenant($this->tenant)->create(['salary_amount_cents' => 5_000_000]);
    $gone->update(['status' => EmploymentStatus::Terminated, 'exit_date' => '2026-08-31']);
    $run = app(PayrollService::class)->run(PayrollPeriod::factory()->forTenant($this->tenant)->forMonth(2026, 9)->create());

    $flags = collect((new PayrollVarianceReport)->rows(['run_uuid' => $run->uuid]))->pluck(8, 1);

    expect($flags[$newcomer->employee_number])->toBe('New in this run')
        ->and($flags[$gone->employee_number])->toBe('Missing from this run');
});

it('values unused paid leave at monthly basic over 22 working days', function () {
    $type = LeaveType::factory()->forTenant($this->tenant)->create(['is_paid' => true]);
    LeaveBalance::query()->create([
        'tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id, 'leave_type_id' => $type->id,
        'year' => 2026, 'allocated_days' => 21, 'used_days' => 1,
    ]);

    $report = new LeaveLiabilityReport;
    $summary = $report->summary(['year' => 2026]);

    expect($summary['Remaining paid leave days'])->toBe(20.0)
        ->and($summary['Estimated liability'])->toBe('90909.00'); // 20 x (100,000 / 22), rounded to the cent
});

it('lists joiners and leavers in a range', function () {
    $this->employee->update(['hire_date' => '2026-03-01']);
    $leaver = Employee::factory()->forTenant($this->tenant)->create(['status' => EmploymentStatus::Resigned, 'exit_date' => '2026-06-30', 'hire_date' => '2020-01-01']);

    $summary = (new HeadcountMovementReport)->summary(['from' => '2026-01-01', 'to' => '2026-12-31']);
    $rows = collect((new HeadcountMovementReport)->rows(['from' => '2026-01-01', 'to' => '2026-12-31']));

    expect($summary['Joiners'])->toBe(1)
        ->and($summary['Leavers'])->toBe(1)
        ->and($rows->pluck(0)->all())->toBe(['Joiner', 'Leaver'])
        ->and($rows->pluck(2)->all())->toContain($leaver->employee_number);
});

it('sends payroll.approved, payroll.paid and employee.terminated webhooks', function () {
    Queue::fake();
    WebhookEndpoint::factory()->forTenant($this->tenant)->create(['events' => ['payroll.approved', 'payroll.paid', 'employee.terminated']]);

    $run = approvedRun($this, 2026, 9);
    app(PayrollService::class)->markPaid($run, 'BANK-001', $this->admin);
    $this->employee->update(['status' => EmploymentStatus::Resigned, 'exit_date' => '2026-09-30']);

    expect(WebhookEvent::subscribable())->toContain('payroll.approved', 'employee.terminated');
    Queue::assertPushed(DeliverWebhook::class, 3);
});

it('filters the audit trail and exports it as CSV, recording the export', function () {
    AuditLog::factory()->forTenant($this->tenant)->create(['event' => 'payroll.approved', 'actor_label' => 'Grace <grace@aud.test>']);
    AuditLog::factory()->forTenant($this->tenant)->create(['event' => 'leave.approved', 'actor_label' => 'Brian <brian@aud.test>']);

    $csv = $this->actingAs($this->admin)
        ->get('http://aud-co.hrm.test/audit-logs/export?event=payroll.')
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('payroll.approved', 'Grace')->not->toContain('leave.approved')
        ->and(AuditLog::query()->where('event', 'audit.exported')->exists())->toBeTrue();

    Livewire::actingAs($this->admin)->test(AuditLogPage::class)
        ->set('actor', 'brian')
        ->assertSee('leave.approved')
        ->assertDontSee('payroll.approved');
});

it('shows operator activity across organizations on the platform audit page', function () {
    AuditLog::factory()->create(['tenant_id' => $this->tenant->id, 'event' => 'platform.tenant.suspended', 'actor_label' => 'Root <root@platform.test>']);
    AuditLog::factory()->forTenant($this->tenant)->create(['event' => 'employee.updated']);

    Livewire::actingAs(User::factory()->platformAdmin(Role::SuperAdmin)->create())
        ->test(AuditTrail::class)
        ->assertSee('platform.tenant.suspended')
        ->assertDontSee('employee.updated');

    Livewire::actingAs(User::factory()->platformAdmin(Role::SupportAdmin)->create())
        ->test(AuditTrail::class);
});

it('lets a super admin delete platform audit entries but not a support admin', function () {
    $entry = AuditLog::factory()->create(['tenant_id' => $this->tenant->id, 'event' => 'platform.tenant.suspended']);
    AuditLog::factory()->create(['tenant_id' => $this->tenant->id, 'event' => 'platform.plan.updated']);
    $tenantEntry = AuditLog::factory()->forTenant($this->tenant)->create(['event' => 'employee.updated']);

    Livewire::actingAs(User::factory()->platformAdmin(Role::SupportAdmin)->create())
        ->test(AuditTrail::class)
        ->call('deleteEntry', $entry->uuid)
        ->assertForbidden();

    $admin = User::factory()->platformAdmin(Role::SuperAdmin)->create();

    Livewire::actingAs($admin)->test(AuditTrail::class)->call('deleteEntry', $entry->uuid);
    expect(AuditLog::query()->whereKey($entry->id)->exists())->toBeFalse();

    Livewire::actingAs($admin)->test(AuditTrail::class)->call('clearAll');
    expect(AuditLog::query()->where('event', 'platform.plan.updated')->exists())->toBeFalse()
        ->and(AuditLog::query()->where('event', 'platform.audit.cleared')->exists())->toBeTrue()
        ->and(AuditLog::query()->whereKey($tenantEntry->id)->exists())->toBeTrue();
});
