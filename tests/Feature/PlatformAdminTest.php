<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->superAdmin = User::factory()->platformAdmin(Role::SuperAdmin)->create();
});

it('redirects guests from the platform area to login', function () {
    $this->get(route('platform.dashboard'))->assertRedirect(route('login'));
});

it('forbids a tenant user from the platform area', function () {
    $tenant = tenantWithRoles();
    $user = userForTenant($tenant, Role::TenantOwner);

    $this->actingAs($user)->get(route('platform.dashboard'))->assertForbidden();
});

it('sends a platform admin from /dashboard to the platform overview', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('dashboard'))
        ->assertRedirect(route('platform.dashboard'));
});

it('shows cross-organization figures on the platform dashboard', function () {
    $plan = Plan::factory()->create(['name' => 'Growth', 'price_cents' => 500000]);
    $a = Tenant::factory()->create(['status' => TenantStatus::Active]);
    $b = Tenant::factory()->create(['status' => TenantStatus::Trialing]);
    Subscription::factory()->forTenant($a)->onPlan($plan)->create(['status' => SubscriptionStatus::Active]);

    Employee::factory()->count(3)->create(['tenant_id' => $a->getKey()]);

    $this->actingAs($this->superAdmin)
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertSee('Platform overview')
        ->assertSee('Growth');
});

it('lists every organization regardless of tenant scope', function () {
    $a = Tenant::factory()->create(['name' => 'Alpha Org']);
    $b = Tenant::factory()->create(['name' => 'Beta Org']);

    $this->actingAs($this->superAdmin)
        ->get(route('platform.tenants.index'))
        ->assertOk()
        ->assertSee('Alpha Org')
        ->assertSee('Beta Org');
});

it('shows an organization detail with its employee count', function () {
    $tenant = Tenant::factory()->create(['name' => 'Detail Org']);
    Employee::factory()->count(2)->create(['tenant_id' => $tenant->getKey()]);

    $this->actingAs($this->superAdmin)
        ->get(route('platform.tenants.show', $tenant))
        ->assertOk()
        ->assertSee('Detail Org')
        ->assertSee('Employees');
});

it('suspends and reactivates an organization and audits both', function () {
    $tenant = Tenant::factory()->create(['status' => TenantStatus::Active]);

    $this->actingAs($this->superAdmin)
        ->post(route('platform.tenants.suspend', $tenant))
        ->assertRedirect(route('platform.tenants.show', $tenant));

    expect($tenant->refresh()->status)->toBe(TenantStatus::Suspended);

    $this->actingAs($this->superAdmin)
        ->post(route('platform.tenants.activate', $tenant))
        ->assertRedirect(route('platform.tenants.show', $tenant));

    expect($tenant->refresh()->status)->toBe(TenantStatus::Active)
        ->and(AuditLog::query()->where('event', 'platform.tenant.suspended')->where('tenant_id', $tenant->getKey())->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', 'platform.tenant.activated')->where('tenant_id', $tenant->getKey())->exists())->toBeTrue();
});

it('creates a plan from the platform area', function () {
    $this->actingAs($this->superAdmin)
        ->post(route('platform.plans.store'), [
            'name' => 'Scale',
            'billing_interval' => 'monthly',
            'price_cents' => 900000,
            'trial_days' => 7,
        ])
        ->assertRedirect(route('platform.plans.index'));

    expect(Plan::query()->where('name', 'Scale')->where('price_cents', 900000)->exists())->toBeTrue();
});

it('updates an existing plan', function () {
    $plan = Plan::factory()->create(['name' => 'Old', 'price_cents' => 100000]);

    $this->actingAs($this->superAdmin)
        ->put(route('platform.plans.update', $plan), [
            'name' => 'Renamed',
            'billing_interval' => $plan->billing_interval->value,
            'price_cents' => 250000,
        ])
        ->assertRedirect(route('platform.plans.index'));

    expect($plan->refresh()->name)->toBe('Renamed')
        ->and($plan->price_cents)->toBe(250000);
});

it('lets a support admin view tenants but not manage plans', function () {
    $support = User::factory()->platformAdmin(Role::SupportAdmin)->create();

    $this->actingAs($support)->get(route('platform.tenants.index'))->assertOk();
    $this->actingAs($support)->get(route('platform.plans.index'))->assertForbidden();
    $this->actingAs($support)
        ->post(route('platform.tenants.suspend', Tenant::factory()->create()))
        ->assertForbidden();
});
