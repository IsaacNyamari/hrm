<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->superAdmin = User::factory()->platformAdmin(Role::SuperAdmin)->create();
});

it('redirects guests away from platform billing', function () {
    $this->get(route('platform.billing'))->assertRedirect(route('login'));
});

it('forbids a tenant user from platform billing', function () {
    $tenant = tenantWithRoles();
    $this->actingAs(userForTenant($tenant, Role::TenantOwner))
        ->get(route('platform.billing'))
        ->assertForbidden();
});

it('lists payments from every organization', function () {
    $a = Tenant::factory()->create(['name' => 'Alpha Org']);
    $b = Tenant::factory()->create(['name' => 'Beta Org']);
    Payment::factory()->forTenant($a)->succeeded()->create(['reference' => 'PAY-ALPHA-1', 'amount_cents' => 300000]);
    Payment::factory()->forTenant($b)->create(['reference' => 'PAY-BETA-1', 'status' => PaymentStatus::Failed]);

    $this->actingAs($this->superAdmin)
        ->get(route('platform.billing'))
        ->assertOk()
        ->assertSee('PAY-ALPHA-1')
        ->assertSee('PAY-BETA-1')
        ->assertSee('Alpha Org');
});

it('filters payments by status', function () {
    $tenant = Tenant::factory()->create();
    Payment::factory()->forTenant($tenant)->succeeded()->create(['reference' => 'PAY-OK']);
    Payment::factory()->forTenant($tenant)->create(['reference' => 'PAY-PENDING', 'status' => PaymentStatus::Pending]);

    $this->actingAs($this->superAdmin)
        ->get(route('platform.billing', ['status' => PaymentStatus::Succeeded->value]))
        ->assertOk()
        ->assertSee('PAY-OK')
        ->assertDontSee('PAY-PENDING');
});

it('ignores an unknown status filter instead of failing', function () {
    $tenant = Tenant::factory()->create();
    Payment::factory()->forTenant($tenant)->succeeded()->create(['reference' => 'PAY-VISIBLE']);

    $this->actingAs($this->superAdmin)
        ->get(route('platform.billing', ['status' => 'not-a-status']))
        ->assertOk()
        ->assertSee('PAY-VISIBLE');
});

it('shows a single payment with its organization', function () {
    $tenant = Tenant::factory()->create(['name' => 'Detail Org']);
    $payment = Payment::factory()->forTenant($tenant)->succeeded()->create(['reference' => 'PAY-DETAIL']);

    $this->actingAs($this->superAdmin)
        ->get(route('platform.billing.payments.show', $payment))
        ->assertOk()
        ->assertSee('PAY-DETAIL')
        ->assertSee('Detail Org');
});

it('shows invoices to a manager and forbids a support admin', function () {
    $tenant = Tenant::factory()->create();
    Invoice::factory()->forTenant($tenant)->create(['number' => 'INV-ACROSS']);

    $this->actingAs($this->superAdmin)
        ->get(route('platform.billing', ['tab' => 'invoices']))
        ->assertOk()
        ->assertSee('INV-ACROSS');

    $support = User::factory()->platformAdmin(Role::SupportAdmin)->create();
    $this->actingAs($support)
        ->get(route('platform.billing', ['tab' => 'invoices']))
        ->assertForbidden();
});

it('shows subscriptions to a manager and forbids a support admin', function () {
    $tenant = Tenant::factory()->create(['name' => 'Sub Org']);
    $plan = Plan::factory()->create(['name' => 'Growth']);
    Subscription::factory()->forTenant($tenant)->onPlan($plan)->create();

    $this->actingAs($this->superAdmin)
        ->get(route('platform.billing', ['tab' => 'subscriptions']))
        ->assertOk()
        ->assertSee('Sub Org')
        ->assertSee('Growth');

    $support = User::factory()->platformAdmin(Role::SupportAdmin)->create();
    $this->actingAs($support)
        ->get(route('platform.billing', ['tab' => 'subscriptions']))
        ->assertForbidden();
});

it('shows payment history on the organization detail page', function () {
    $tenant = Tenant::factory()->create();
    Payment::factory()->forTenant($tenant)->succeeded()->create(['reference' => 'PAY-ONTENANT', 'amount_cents' => 450000]);

    $this->actingAs($this->superAdmin)
        ->get(route('platform.tenants.show', $tenant))
        ->assertOk()
        ->assertSee('PAY-ONTENANT');
});
