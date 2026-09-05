<?php

use App\Actions\Tenancy\ProvisionTenant;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // Global permission catalogue + platform roles for every feature test.
        $this->seed(RolePermissionSeeder::class);
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Tenancy test helpers
|--------------------------------------------------------------------------
*/

/**
 * Create a tenant with its standard role set provisioned.
 */
function tenantWithRoles(array $attributes = []): Tenant
{
    $tenant = Tenant::factory()->create($attributes);

    app(ProvisionTenant::class)->handle($tenant);

    return $tenant;
}

/**
 * Create a user attached to $tenant and assigned $role within that tenant's
 * team scope.
 */
function userForTenant(Tenant $tenant, Role $role = Role::HrManager): User
{
    $user = User::factory()->forTenant($tenant)->create();

    app(TenantContext::class)->run($tenant, function () use ($user, $role): void {
        $user->assignRole($role->value);
    });

    return $user;
}

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

function something()
{
    // ..
}
