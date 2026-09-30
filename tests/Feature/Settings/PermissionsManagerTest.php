<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\Settings\PermissionsManager;
use App\Models\AuditLog;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = tenantWithRoles(['slug' => 'permissions-co']);
    app(TenantContext::class)->set($this->tenant);
    $this->owner = userForTenant($this->tenant, Role::TenantOwner);
    $this->employee = userForTenant($this->tenant, Role::Employee);
});

it('lets a tenant owner promote an employee to a new role and audits it', function () {
    $this->actingAs($this->owner);

    Livewire::test(PermissionsManager::class)
        ->call('updateRole', $this->employee->id, Role::DepartmentManager->value);

    app(TenantContext::class)->run($this->tenant, function (): void {
        expect($this->employee->fresh()->hasRole(Role::DepartmentManager->value))->toBeTrue();
    });

    expect(AuditLog::query()->where('event', 'tenant.user.role_changed')->where('auditable_id', $this->employee->id)->exists())->toBeTrue();
});

it('refuses to let a tenant owner grant the Tenant Owner role themselves', function () {
    $this->actingAs($this->owner);

    Livewire::test(PermissionsManager::class)
        ->call('updateRole', $this->employee->id, Role::TenantOwner->value)
        ->assertStatus(422);
});

it('refuses to let another tenant admin change the current owner\'s role', function () {
    $admin = userForTenant($this->tenant, Role::HrAdministrator);
    $this->actingAs($admin);

    Livewire::test(PermissionsManager::class)
        ->call('updateRole', $this->owner->id, Role::Employee->value);

    app(TenantContext::class)->run($this->tenant, function (): void {
        expect($this->owner->fresh()->hasRole(Role::TenantOwner->value))->toBeTrue();
    });
});

it('refuses to let a user change their own role', function () {
    $this->actingAs($this->owner);

    Livewire::test(PermissionsManager::class)
        ->call('updateRole', $this->owner->id, Role::Employee->value);

    app(TenantContext::class)->run($this->tenant, function (): void {
        expect($this->owner->fresh()->hasRole(Role::TenantOwner->value))->toBeTrue();
    });
});

it('refuses a plain employee without roles.manage', function () {
    $this->actingAs($this->employee)
        ->get(route('settings.permissions'))
        ->assertForbidden();
});

it('refuses to assign roles across tenants', function () {
    $this->actingAs($this->owner);
    $otherTenant = tenantWithRoles(['slug' => 'permissions-rival-co']);
    $stranger = userForTenant($otherTenant, Role::Employee);

    Livewire::test(PermissionsManager::class)
        ->call('updateRole', $stranger->id, Role::HrManager->value);
})->throws(ModelNotFoundException::class);
