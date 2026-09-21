<?php

declare(strict_types=1);

use App\Actions\Tenancy\ProvisionTenantRoles;
use App\Enums\Role;
use App\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

it('creates permissions missing from a stale catalogue before syncing roles', function () {
    Permission::query()->where('name', 'tasks.view')->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $tenant = Tenant::factory()->create();

    app(ProvisionTenantRoles::class)->handle($tenant);

    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
    $owner = Spatie\Permission\Models\Role::findByName(Role::TenantOwner->value, 'web');

    expect(Permission::query()->where('name', 'tasks.view')->exists())->toBeTrue()
        ->and($owner->hasPermissionTo('tasks.view'))->toBeTrue();
});
