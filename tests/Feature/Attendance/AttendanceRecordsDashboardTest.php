<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\Attendance\Records;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Services\Attendance\AttendanceCorrectionService;
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

describe('second-person approval', function () {
    beforeEach(function () {
        $this->tenant->forceFill(['settings' => [...(array) $this->tenant->settings, 'attendance' => ['require_edit_approval' => true]]])->save();
        $this->approver = userForTenant($this->tenant, Role::HrManager);
    });

    it('queues an edit instead of applying it, and applies it once someone else approves', function () {
        $record = Attendance::factory()->forEmployee($this->employee)->create(['status' => 'present']);

        $this->actingAs($this->hr);
        Livewire::test(Records::class)
            ->call('startEdit', $record->uuid)
            ->set('form.status', 'remote_work')
            ->set('form.reason', 'Worked from home, approved by manager')
            ->call('save');

        $correction = AttendanceCorrection::query()->firstOrFail();
        expect($record->fresh()->status->value)->toBe('present')
            ->and($correction->status)->toBe('pending');

        // the requester cannot approve their own change
        Livewire::test(Records::class)->call('approveCorrection', $correction->uuid);
        expect($record->fresh()->status->value)->toBe('present')
            ->and($correction->fresh()->status)->toBe('pending');

        $this->actingAs($this->approver);
        Livewire::test(Records::class)->call('approveCorrection', $correction->uuid);

        expect($record->fresh()->status->value)->toBe('remote_work')
            ->and($correction->fresh()->status)->toBe('approved')
            ->and($correction->fresh()->decided_by)->toBe($this->approver->id)
            ->and(AuditLog::query()->where('event', 'attendance.correction_approved')->exists())->toBeTrue();
    });

    it('queues a deletion and keeps the record until it is approved; rejection leaves it untouched', function () {
        $record = Attendance::factory()->forEmployee($this->employee)->create();

        $this->actingAs($this->hr);
        Livewire::test(Records::class)
            ->call('askRemove', $record->uuid)
            ->set('deleteReason', 'Duplicate punch from the device')
            ->call('remove');

        $correction = AttendanceCorrection::query()->firstOrFail();
        expect(Attendance::query()->find($record->id))->not->toBeNull();

        $this->actingAs($this->approver);
        Livewire::test(Records::class)->call('rejectCorrection', $correction->uuid);

        expect($correction->fresh()->status)->toBe('rejected')
            ->and(Attendance::query()->find($record->id))->not->toBeNull();
    });

    it('applies an approved deletion', function () {
        $record = Attendance::factory()->forEmployee($this->employee)->create();

        $this->actingAs($this->hr);
        Livewire::test(Records::class)->call('askRemove', $record->uuid)->set('deleteReason', 'Entered on the wrong day')->call('remove');

        $this->actingAs($this->approver);
        Livewire::test(Records::class)->call('approveCorrection', AttendanceCorrection::query()->firstOrFail()->uuid);

        expect(Attendance::query()->find($record->id))->toBeNull();
    });

    it('lets a manager switch the approval requirement off and audits it', function () {
        $this->actingAs($this->hr);
        Livewire::test(Records::class)->call('toggleApproval');

        expect(AuditLog::query()->where('event', 'attendance.approval_setting_changed')->exists())->toBeTrue()
            ->and(app(AttendanceCorrectionService::class)->requiresApproval($this->tenant->fresh()))->toBeFalse();
    });
});
