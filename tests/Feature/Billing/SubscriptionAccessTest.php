<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Employee;
use App\Models\Plan;
use App\Models\Subscription;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->plan = Plan::factory()->create();
});

it('blocks the app for a lapsed subscription but leaves billing reachable', function () {
    $tenant = tenantWithRoles(['slug' => 'lapsed-co']);
    Subscription::factory()->forTenant($tenant)->onPlan($this->plan)->expired()->create();
    $user = userForTenant($tenant, Role::TenantOwner);
    Employee::factory()->forTenant($tenant)->create();

    $this->actingAs($user)->getJson('http://lapsed-co.hrm.test/employees')->assertStatus(402);
    $this->actingAs($user)->getJson('http://lapsed-co.hrm.test/billing')->assertOk();
});

it('allows the app while trialing', function () {
    $tenant = tenantWithRoles(['slug' => 'trial-co']);
    Subscription::factory()->forTenant($tenant)->onPlan($this->plan)->trialing()->create();
    $user = userForTenant($tenant, Role::HrManager);
    Employee::factory()->forTenant($tenant)->create();

    $this->actingAs($user)->getJson('http://trial-co.hrm.test/employees')->assertOk();
});

it('redirects browser traffic to billing when lapsed', function () {
    $tenant = tenantWithRoles(['slug' => 'redir-co']);
    Subscription::factory()->forTenant($tenant)->onPlan($this->plan)->expired()->create();
    $user = userForTenant($tenant, Role::TenantOwner);

    $this->actingAs($user)->get('http://redir-co.hrm.test/employees')->assertStatus(302);
});
