<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Facades\Tenancy;
use App\Models\Employee;
use App\Models\User;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
});

it('resolves a tenant by exact custom domain', function () {
    $tenant = tenantWithRoles(['custom_domain' => 'hr.acme.example']);
    $user = userForTenant($tenant, Role::HrManager);
    Employee::factory()->count(2)->forTenant($tenant)->create();

    $this->actingAs($user)
        ->getJson('http://hr.acme.example/employees')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('falls back to the authenticated user\'s tenant on a central domain', function () {
    $tenant = tenantWithRoles();
    $user = userForTenant($tenant, Role::HrManager);
    Employee::factory()->count(3)->forTenant($tenant)->create();

    $this->actingAs($user)
        ->getJson('http://hrm.test/employees')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('404s on a central domain when the user has no tenant', function () {
    $admin = User::factory()->platformAdmin()->create();

    $this->actingAs($admin)
        ->getJson('http://hrm.test/employees')
        ->assertNotFound();
});

it('does not leak tenant context between requests', function () {
    $a = tenantWithRoles(['slug' => 'leak-a']);
    $b = tenantWithRoles(['slug' => 'leak-b']);
    $userA = userForTenant($a, Role::HrManager);
    $userB = userForTenant($b, Role::HrManager);
    Employee::factory()->forTenant($a)->create();
    Employee::factory()->count(2)->forTenant($b)->create();

    $this->actingAs($userA)->getJson('http://leak-a.hrm.test/employees')->assertJsonCount(1, 'data');
    $this->actingAs($userB)->getJson('http://leak-b.hrm.test/employees')->assertJsonCount(2, 'data');

    expect(Tenancy::has())->toBeFalse();
});
