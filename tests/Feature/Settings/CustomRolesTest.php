<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\Settings\CustomRoles;
use App\Livewire\Settings\PermissionsManager;
use App\Models\AuditLog;
use App\Support\TenantContext;
use Livewire\Livewire;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function () {
    $this->tenant = tenantWithRoles(['slug' => 'roles-co']);
    app(TenantContext::class)->set($this->tenant);
    $this->owner = userForTenant($this->tenant, Role::TenantOwner);
    $this->employee = userForTenant($this->tenant, Role::Employee);
});

it('lets an administrator build a custom role, assign it, and audits both', function () {
    $this->actingAs($this->owner);

    Livewire::test(CustomRoles::class)
        ->call('startCreate')
        ->set('name', 'Payroll Auditor')
        ->set('selected', ['payroll.view', 'reports.view', 'audit.view'])
        ->call('save')
        ->assertHasNoErrors();

    $role = SpatieRole::query()->where('tenant_id', $this->tenant->id)->where('name', 'Payroll Auditor')->firstOrFail();
    expect($role->permissions->pluck('name')->sort()->values()->all())->toBe(['audit.view', 'payroll.view', 'reports.view']);

    Livewire::test(PermissionsManager::class)->call('updateRole', $this->employee->id, 'Payroll Auditor');

    app(TenantContext::class)->run($this->tenant, function (): void {
        expect($this->employee->fresh()->hasRole('Payroll Auditor'))->toBeTrue()
            ->and($this->employee->fresh()->can('payroll.view'))->toBeTrue()
            ->and($this->employee->fresh()->can('payroll.manage'))->toBeFalse();
    });

    expect(AuditLog::query()->where('event', 'tenant.role_created')->exists())->toBeTrue();
});

it('will not let a role hold permissions its creator does not have', function () {
    $this->actingAs($this->owner);
    Livewire::test(CustomRoles::class)->call('startCreate')->set('name', 'Role Admin')->set('selected', ['roles.manage', 'leave.view'])->call('save')->assertHasNoErrors();
    Livewire::test(PermissionsManager::class)->call('updateRole', $this->employee->id, 'Role Admin');

    $this->actingAs($this->employee->fresh());

    Livewire::test(CustomRoles::class)
        ->call('startCreate')
        ->set('name', 'Sneaky')
        ->set('selected', ['payroll.manage'])
        ->call('save')
        ->assertHasErrors('selected');

    expect(SpatieRole::query()->where('name', 'Sneaky')->exists())->toBeFalse();
});

it('keeps people without role-management rights out of the role builder', function () {
    $this->actingAs(userForTenant($this->tenant, Role::PayrollManager));

    Livewire::test(CustomRoles::class)->assertForbidden();
});

it('rejects a name used by a standard role or another custom role', function () {
    $this->actingAs($this->owner);

    Livewire::test(CustomRoles::class)
        ->call('startCreate')
        ->set('name', Role::HrAdministrator->value)
        ->set('selected', ['leave.view'])
        ->call('save')
        ->assertHasErrors('name');
});

it('refuses to assign a role that is not standard and not one of the organization\'s own', function () {
    $this->actingAs($this->owner);

    $other = tenantWithRoles(['slug' => 'other-roles-co']);
    SpatieRole::query()->create(['name' => 'Foreign Role', 'guard_name' => 'web', 'tenant_id' => $other->id]);

    Livewire::test(PermissionsManager::class)
        ->call('updateRole', $this->employee->id, 'Foreign Role')
        ->assertStatus(422);
});

it('only deletes a custom role nobody holds', function () {
    $this->actingAs($this->owner);

    Livewire::test(CustomRoles::class)->call('startCreate')->set('name', 'Temp Role')->set('selected', ['leave.view'])->call('save');
    $role = SpatieRole::query()->where('name', 'Temp Role')->firstOrFail();

    Livewire::test(PermissionsManager::class)->call('updateRole', $this->employee->id, 'Temp Role');
    Livewire::test(CustomRoles::class)->call('delete', $role->id);
    expect(SpatieRole::query()->whereKey($role->id)->exists())->toBeTrue();

    Livewire::test(PermissionsManager::class)->call('updateRole', $this->employee->id, Role::Employee->value);
    Livewire::test(CustomRoles::class)->call('delete', $role->id);
    expect(SpatieRole::query()->whereKey($role->id)->exists())->toBeFalse();
});
