<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;
use App\Facades\Tenancy;
use App\Models\Employee;

it('provisions the standard tenant role set scoped to the tenant', function () {
    $tenant = tenantWithRoles();

    Tenancy::run($tenant, function () use ($tenant) {
        $roles = Spatie\Permission\Models\Role::query()->where('tenant_id', $tenant->id)->pluck('name');

        expect($roles)->toContain(Role::TenantOwner->value, Role::HrManager->value, Role::Employee->value);
    });
});

it('keeps role assignments isolated per tenant', function () {
    $tenantA = tenantWithRoles();
    $tenantB = tenantWithRoles();

    $user = userForTenant($tenantA, Role::HrManager);

    Tenancy::run($tenantA, fn () => expect($user->hasRole(Role::HrManager->value))->toBeTrue());
    Tenancy::run($tenantB, fn () => expect($user->fresh()->hasRole(Role::HrManager->value))->toBeFalse());
});

it('grants permissions through the assigned role', function () {
    $tenant = tenantWithRoles();
    $hr = userForTenant($tenant, Role::HrManager);
    $employee = userForTenant($tenant, Role::Employee);

    Tenancy::run($tenant, function () use ($hr, $employee) {
        expect($hr->hasPermissionTo(Permission::EmployeesCreate->value))->toBeTrue()
            ->and($employee->hasPermissionTo(Permission::EmployeesCreate->value))->toBeFalse()
            ->and($employee->hasPermissionTo(Permission::LeaveRequest->value))->toBeTrue();
    });
});

it('evaluates a policy in the record\'s tenant scope even with no team bound', function () {
    $tenant = tenantWithRoles();
    $hr = userForTenant($tenant, Role::HrManager);
    $employee = Employee::factory()->forTenant($tenant)->create();

    // no Tenancy / team context bound here
    expect($hr->can('view', $employee))->toBeTrue()
        ->and($hr->can('create', Employee::class))->toBeTrue();
});

it('lets an admin with users.manage invite a teammate', function () {
    $tenant = tenantWithRoles(['slug' => 'inviter-co']);
    config(['tenancy.central_domains' => ['hrm.test']]);
    $owner = userForTenant($tenant, Role::TenantOwner);

    $this->actingAs($owner)
        ->postJson('http://hrm.test/users/invitations', [
            'email' => 'newhire@inviter-co.test',
            'role' => Role::HrManager->value,
        ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'newhire@inviter-co.test');

    $this->assertDatabaseHas('invitations', [
        'tenant_id' => $tenant->id,
        'email' => 'newhire@inviter-co.test',
        'role' => Role::HrManager->value,
    ]);
});

it('forbids inviting without the users.manage permission', function () {
    $tenant = tenantWithRoles(['slug' => 'noperm-co']);
    config(['tenancy.central_domains' => ['hrm.test']]);
    $user = userForTenant($tenant, Role::Employee);

    $this->actingAs($user)
        ->postJson('http://hrm.test/users/invitations', [
            'email' => 'x@noperm-co.test',
            'role' => Role::HrManager->value,
        ])
        ->assertForbidden();
});

it('rejects inviting into a platform role', function () {
    $tenant = tenantWithRoles(['slug' => 'role-guard-co']);
    config(['tenancy.central_domains' => ['hrm.test']]);
    $owner = userForTenant($tenant, Role::TenantOwner);

    $this->actingAs($owner)
        ->postJson('http://hrm.test/users/invitations', [
            'email' => 'evil@role-guard-co.test',
            'role' => Role::SuperAdmin->value,
        ])
        ->assertStatus(422);
});
