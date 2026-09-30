<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\Attendance\Records;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Support\TenantContext;
use Livewire\Livewire;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'att-dash-co']);
    app(TenantContext::class)->set($this->tenant);
    $this->hr = userForTenant($this->tenant, Role::HrManager); // attendance.manage
    $this->employee = Employee::factory()->forTenant($this->tenant)->create();
});

it('creates an attendance record from the dashboard', function () {
    $this->actingAs($this->hr);

    Livewire::test(Records::class)
        ->call('startCreate')
        ->set('form.employee_id', (string) $this->employee->id)
        ->set('form.work_date', now()->toDateString())
        ->set('form.clock_in_at', now()->setTime(8, 0)->format('Y-m-d\TH:i'))
        ->set('form.clock_out_at', now()->setTime(17, 0)->format('Y-m-d\TH:i'))
        ->set('form.break_minutes', '60')
        ->set('form.reason', 'Forgot to clock in; confirmed by supervisor')
        ->call('save')
        ->assertDispatched('toast-show')
        ->assertSet('showForm', false);

    $record = Attendance::query()->where('employee_id', $this->employee->id)->firstOrFail();
    expect($record->worked_minutes)->toBe(480);
});

it('edits an attendance record from the dashboard', function () {
    $record = Attendance::factory()->forEmployee($this->employee)->create(['status' => 'present']);

    $this->actingAs($this->hr);

    Livewire::test(Records::class)
        ->call('startEdit', $record->uuid)
        ->set('form.status', 'remote_work')
        ->set('form.reason', 'Worked from home, approved by manager')
        ->call('save')
        ->assertDispatched('toast-show');

    $entry = AuditLog::query()->where('event', 'attendance.manual_edited')->firstOrFail();

    expect($record->fresh()->status->value)->toBe('remote_work')
        ->and($entry->properties['reason'])->toBe('Worked from home, approved by manager')
        ->and($entry->properties['before']['status'])->toBe('present')
        ->and($entry->properties['after']['status'])->toBe('remote_work');
});

it('deletes an attendance record from the dashboard', function () {
    $record = Attendance::factory()->forEmployee($this->employee)->create();

    $this->actingAs($this->hr);

    Livewire::test(Records::class)->call('askRemove', $record->uuid)->set('deleteReason', 'x')->call('remove')->assertHasErrors('deleteReason');
    expect(Attendance::query()->find($record->id))->not->toBeNull();

    Livewire::test(Records::class)
        ->call('askRemove', $record->uuid)
        ->set('deleteReason', 'Duplicate punch from the device')
        ->call('remove');

    expect(Attendance::query()->find($record->id))->toBeNull()
        ->and(AuditLog::query()->where('event', 'attendance.manual_deleted')->exists())->toBeTrue();
});

it('refuses a manual attendance change without a reason', function () {
    $this->actingAs($this->hr);

    Livewire::test(Records::class)
        ->call('startCreate')
        ->set('form.employee_id', (string) $this->employee->id)
        ->call('save')
        ->assertHasErrors('form.reason');
});

it('forbids managing attendance from the dashboard without attendance.manage', function () {
    $staff = userForTenant($this->tenant, Role::Employee);

    $this->actingAs($staff);

    Livewire::test(Records::class)->assertForbidden();
});
