<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use App\Support\TenantContext;

beforeEach(function () {
    $this->tenant = tenantWithRoles(['slug' => 'my-permissions-co']);
    app(TenantContext::class)->set($this->tenant);
});

it('shows an employee their own role and permissions', function () {
    $employee = userForTenant($this->tenant, Role::Employee);
    $this->actingAs($employee);

    $this->get(route('permissions.mine'))
        ->assertOk()
        ->assertSee('Employee')
        ->assertSee('leave request');
});

it('shows a user with no role that none is assigned yet', function () {
    $user = User::factory()->forTenant($this->tenant)->create();
    $this->actingAs($user);

    $this->get(route('permissions.mine'))
        ->assertOk()
        ->assertSee("You don't have any permissions yet.");
});

it('shows a manager the fuller permission set their role grants', function () {
    $manager = userForTenant($this->tenant, Role::HrManager);
    $this->actingAs($manager);

    $this->get(route('permissions.mine'))
        ->assertOk()
        ->assertSee('HR Manager')
        ->assertSee('employees view');
});
