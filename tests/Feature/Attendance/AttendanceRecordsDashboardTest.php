<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\Attendance\Records;
use App\Models\Attendance;
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
        ->call('save')
        ->assertDispatched('toast-show');

    expect($record->fresh()->status->value)->toBe('remote_work');
});

it('deletes an attendance record from the dashboard', function () {
    $record = Attendance::factory()->forEmployee($this->employee)->create();

    $this->actingAs($this->hr);

    Livewire::test(Records::class)->call('remove', $record->uuid);

    expect(Attendance::query()->find($record->id))->toBeNull();
});

it('forbids managing attendance from the dashboard without attendance.manage', function () {
    $staff = userForTenant($this->tenant, Role::Employee);

    $this->actingAs($staff);

    Livewire::test(Records::class)->assertForbidden();
});
