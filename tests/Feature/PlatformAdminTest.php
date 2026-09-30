<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Livewire\Platform\Dashboard as PlatformDashboard;
use App\Livewire\Platform\Plans;
use App\Livewire\Platform\TenantDetail;
use App\Livewire\Platform\Tenants;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

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
    Tenant::factory()->create(['status' => TenantStatus::Trialing]);
    Subscription::factory()->forTenant($a)->onPlan($plan)->create(['status' => SubscriptionStatus::Active]);
    Employee::factory()->count(3)->create(['tenant_id' => $a->getKey()]);

    $this->actingAs($this->superAdmin)
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertSee('Platform overview')
        ->assertSee('Growth');
});

it('links each platform dashboard card to its filtered resource', function () {
    $this->actingAs($this->superAdmin)
        ->get(route('platform.dashboard'))
        ->assertOk()
        ->assertSee(route('platform.billing', ['tab' => 'subscriptions']))
        ->assertSee(route('platform.billing', ['tab' => 'invoices', 'status' => 'open']))
        ->assertSee(route('platform.billing', ['tab' => 'payments', 'status' => 'succeeded']))
        ->assertSee(route('platform.tenants.index', ['status' => 'suspended']));
});

it('charts monthly revenue, sign-ups, status mix and the largest organizations', function () {
    $this->travelTo(now()->setDate(2026, 9, 15));

    $big = Tenant::factory()->create(['name' => 'Big Org', 'status' => TenantStatus::Active]);
    $small = Tenant::factory()->create(['name' => 'Small Org', 'status' => TenantStatus::Suspended]);
    Employee::factory()->count(3)->create(['tenant_id' => $big->getKey()]);
    Employee::factory()->create(['tenant_id' => $small->getKey()]);

    Payment::factory()->forTenant($big)->succeeded()->create(['amount_cents' => 250000, 'paid_at' => now()]);
    Payment::factory()->forTenant($big)->succeeded()->create(['amount_cents' => 100000, 'paid_at' => now()->subMonthNoOverflow()]);
    Payment::factory()->forTenant($big)->create(['status' => PaymentStatus::Failed, 'amount_cents' => 999999, 'paid_at' => now()]);

    $this->actingAs($this->superAdmin);

    Livewire::test(PlatformDashboard::class)
        ->assertViewHas('revenue_series', fn (array $series): bool => count($series) === 12
            && end($series)['value'] === 250000
            && $series[10]['value'] === 100000)
        ->assertViewHas('signup_series', fn (array $series): bool => end($series)['value'] === 2)
        ->assertViewHas('status_breakdown', fn (array $rows): bool => collect($rows)->pluck('count', 'status')->all() === ['active' => 1, 'suspended' => 1])
        ->assertViewHas('top_tenants', fn (array $rows): bool => $rows[0]['name'] === 'Big Org' && $rows[0]['employees'] === 3)
        ->assertViewHas('cards', fn (array $cards): bool => collect($cards)->firstWhere('key', 'revenue_month')['trend'] === 150.0);
});

it('lists every organization regardless of tenant scope', function () {
    Tenant::factory()->create(['name' => 'Alpha Org']);
    Tenant::factory()->create(['name' => 'Beta Org']);

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

    $this->actingAs($this->superAdmin);

    Livewire::test(Tenants::class)->call('suspend', $tenant->id);
    expect($tenant->refresh()->status)->toBe(TenantStatus::Suspended);

    Livewire::test(Tenants::class)->call('activate', $tenant->id);
    expect($tenant->refresh()->status)->toBe(TenantStatus::Active)
        ->and(AuditLog::query()->where('event', 'platform.tenant.suspended')->where('tenant_id', $tenant->getKey())->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', 'platform.tenant.activated')->where('tenant_id', $tenant->getKey())->exists())->toBeTrue();
});

it('reflects a suspended organization on the dashboard immediately, not after the metrics cache expires', function () {
    $tenant = Tenant::factory()->create(['status' => TenantStatus::Active]);

    $this->actingAs($this->superAdmin);

    // Warm the metrics cache with the pre-suspension figures.
    Livewire::test(PlatformDashboard::class)
        ->assertViewHas('minor_cards', fn (array $cards): bool => collect($cards)->firstWhere('key', 'active')['value'] === 1
            && collect($cards)->firstWhere('key', 'suspended')['value'] === 0);

    Livewire::test(Tenants::class)->call('suspend', $tenant->id);

    Livewire::test(PlatformDashboard::class)
        ->assertViewHas('minor_cards', fn (array $cards): bool => collect($cards)->firstWhere('key', 'active')['value'] === 0
            && collect($cards)->firstWhere('key', 'suspended')['value'] === 1);
});

it('reflects a status change made from the organization detail page on the dashboard immediately', function () {
    $tenant = Tenant::factory()->create(['status' => TenantStatus::Active]);

    $this->actingAs($this->superAdmin);

    Livewire::test(PlatformDashboard::class)
        ->assertViewHas('minor_cards', fn (array $cards): bool => collect($cards)->firstWhere('key', 'suspended')['value'] === 0);

    Livewire::test(TenantDetail::class, ['tenant' => $tenant])->call('suspend');

    Livewire::test(PlatformDashboard::class)
        ->assertViewHas('minor_cards', fn (array $cards): bool => collect($cards)->firstWhere('key', 'suspended')['value'] === 1);
});

it('creates a plan from the platform area', function () {
    $this->actingAs($this->superAdmin);

    Livewire::test(Plans::class)
        ->call('create')
        ->set('form.name', 'Scale')
        ->set('form.billing_interval', 'monthly')
        ->set('form.price_cents', 900000)
        ->set('form.trial_days', 7)
        ->call('save')
        ->assertHasNoErrors();

    expect(Plan::query()->where('name', 'Scale')->where('price_cents', 900000)->exists())->toBeTrue();
});

it('updates an existing plan', function () {
    $plan = Plan::factory()->create(['name' => 'Old', 'price_cents' => 100000]);

    $this->actingAs($this->superAdmin);

    Livewire::test(Plans::class)
        ->call('edit', $plan->id)
        ->set('form.name', 'Renamed')
        ->set('form.price_cents', 250000)
        ->call('save')
        ->assertHasNoErrors();

    expect($plan->refresh()->name)->toBe('Renamed')
        ->and($plan->price_cents)->toBe(250000);
});

it('lets a support admin view tenants but not manage plans or organizations', function () {
    $support = User::factory()->platformAdmin(Role::SupportAdmin)->create();

    $this->actingAs($support)->get(route('platform.tenants.index'))->assertOk();
    $this->actingAs($support)->get(route('platform.plans.index'))->assertForbidden();

    Livewire::test(Tenants::class)
        ->call('suspend', Tenant::factory()->create()->id)
        ->assertStatus(403);
});

it('asks for confirmation before deleting a plan', function () {
    $plan = Plan::factory()->create(['name' => 'Legacy']);

    $this->actingAs($this->superAdmin);

    Livewire::test(Plans::class)
        ->assertSeeHtml('delete-plan-'.$plan->id)
        ->assertSee('Delete the “Legacy” plan?');
});

it('deletes a plan once confirmed and audits it', function () {
    $plan = Plan::factory()->create(['name' => 'Legacy']);

    $this->actingAs($this->superAdmin);

    Livewire::test(Plans::class)
        ->call('delete', $plan->id)
        ->assertSet('notice', 'Plan “Legacy” deleted.');

    expect(Plan::query()->whereKey($plan->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->where('event', 'platform.plan.deleted')->exists())->toBeTrue();
});

it('renders the platform dashboard from a serialising cache store', function () {
    config(['cache.default' => 'database']);
    Tenant::factory()->create(['name' => 'Cached Org']);

    $this->actingAs($this->superAdmin);

    $this->get(route('platform.dashboard'))->assertOk();
    $this->get(route('platform.dashboard'))->assertOk()->assertSee('Cached Org');
});
