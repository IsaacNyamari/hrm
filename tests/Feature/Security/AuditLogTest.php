<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Events\LeaveApproved;
use App\Livewire\Audit\Log as AuditLogComponent;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Support\TenantContext;
use Livewire\Livewire;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'audit-co', 'name' => 'Audit Co']);
    app(TenantContext::class)->set($this->tenant);
    $this->hr = userForTenant($this->tenant, Role::HrAdministrator);
});

it('records an audit entry when an auditable model changes', function () {
    $employee = Employee::factory()->forTenant($this->tenant)->create();

    $employee->update(['job_title' => 'Head of Widgets']);

    $log = AuditLog::query()
        ->where('event', 'employee.updated')
        ->where('auditable_id', $employee->id)
        ->latest('id')
        ->firstOrFail();

    expect($log->properties['changes'])->toHaveKey('job_title', 'Head of Widgets')
        ->and($log->tenant_id)->toBe($this->tenant->id)
        ->and(AuditLog::query()->where('event', 'employee.created')->where('auditable_id', $employee->id)->exists())->toBeTrue();
});

it('never writes password-like columns to the trail', function () {
    $employee = Employee::factory()->forTenant($this->tenant)->create(['notes' => 'x']);
    $employee->update(['notes' => 'y']);

    $log = AuditLog::query()->where('event', 'employee.updated')->latest('id')->firstOrFail();
    expect($log->properties['changes'])->not->toHaveKey('password')
        ->and($log->properties['changes'])->not->toHaveKey('remember_token');
});

it('is append-only — entries cannot be updated or deleted', function () {
    $log = AuditLog::factory()->forTenant($this->tenant)->create();

    expect(fn () => $log->update(['event' => 'tampered']))->toThrow(RuntimeException::class);
    expect(fn () => $log->delete())->toThrow(RuntimeException::class);
});

it('lists the audit trail for holders of audit.view, scoped to the tenant', function () {
    AuditLog::factory()->forTenant($this->tenant)->count(3)->create();
    $other = tenantWithRoles(['slug' => 'rival-audit-co']);
    AuditLog::factory()->forTenant($other)->count(4)->create();

    $response = $this->actingAs($this->hr)
        ->getJson('http://audit-co.hrm.test/audit-logs')
        ->assertOk();

    expect($response->json('total'))->toBe(3);
});

it('forbids the audit trail without the permission', function () {
    $staff = userForTenant($this->tenant, Role::Employee);

    $this->actingAs($staff)
        ->getJson('http://audit-co.hrm.test/audit-logs')
        ->assertForbidden();
});

it('records leave approvals in the trail', function () {
    $manager = Employee::factory()->forTenant($this->tenant)->create();
    $employee = Employee::factory()->forTenant($this->tenant)->create(['manager_id' => $manager->id]);

    LeaveApproved::dispatch(
        LeaveRequest::factory()->forEmployee($employee)->approved()->create(['tenant_id' => $this->tenant->id])
    );

    expect(AuditLog::query()->where('event', 'leave.approved')->exists())->toBeTrue();
});

it('lets an authorised user delete one audit entry or clear the whole trail, leaving a record of it', function () {
    $other = tenantWithRoles(['slug' => 'other-audit-co']);
    $keep = AuditLog::factory()->forTenant($other)->create(['event' => 'employee.updated']);
    $one = AuditLog::factory()->forTenant($this->tenant)->create(['event' => 'demo.one']);
    AuditLog::factory()->forTenant($this->tenant)->create(['event' => 'demo.two']);

    $this->actingAs($this->hr);

    Livewire::test(AuditLogComponent::class)->call('deleteEntry', $one->uuid);
    expect(AuditLog::query()->where('event', 'demo.one')->exists())->toBeFalse()
        ->and(AuditLog::query()->where('event', 'demo.two')->exists())->toBeTrue();

    Livewire::test(AuditLogComponent::class)->call('clearAll');
    expect(AuditLog::query()->where('tenant_id', $this->tenant->id)->where('event', '!=', 'audit.cleared')->where('event', '!=', 'audit.entry_deleted')->count())->toBe(0)
        ->and(AuditLog::query()->where('event', 'audit.cleared')->exists())->toBeTrue()
        ->and(AuditLog::query()->whereKey($keep->id)->exists())->toBeTrue();
});

it('refuses audit deletion without the permission', function () {
    $log = AuditLog::factory()->forTenant($this->tenant)->create();

    $viewer = userForTenant($this->tenant, Role::HrManager);
    $viewer->givePermissionTo('audit.view');
    $this->actingAs($viewer);

    Livewire::test(AuditLogComponent::class)->call('deleteEntry', $log->uuid)->assertForbidden();
    expect(AuditLog::query()->whereKey($log->id)->exists())->toBeTrue();
});

it('still blocks deleting an audit entry through the model', function () {
    $log = AuditLog::factory()->forTenant($this->tenant)->create();

    expect(fn () => $log->delete())->toThrow(RuntimeException::class);
});
