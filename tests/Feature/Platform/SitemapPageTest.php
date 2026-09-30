<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vacancy;

it('shows a super admin the sitemap url and a breakdown of what it contains', function () {
    $tenant = Tenant::factory()->create();
    Vacancy::factory()->forTenant($tenant)->open()->create();

    $this->actingAs(User::factory()->platformAdmin(Role::SuperAdmin)->create())
        ->get(route('platform.sitemap'))
        ->assertOk()
        ->assertSee(route('sitemap'))
        ->assertSee('1'); // one open vacancy counted
});

it('forbids a platform role without sitemap.manage', function () {
    $this->actingAs(User::factory()->platformAdmin(Role::SupportAdmin)->create())
        ->get(route('platform.sitemap'))
        ->assertForbidden();
});

it('forbids a tenant user from the platform sitemap page', function () {
    $tenant = tenantWithRoles();

    $this->actingAs(userForTenant($tenant, Role::TenantOwner))
        ->get(route('platform.sitemap'))
        ->assertForbidden();
});
