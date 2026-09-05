<?php

declare(strict_types=1);

use App\Enums\PaymentProvider;
use App\Enums\Role;
use App\Models\Invoice;
use App\Models\Plan;
use App\Services\Billing\InvoiceService;
use App\Services\Subscription\SubscriptionManager;

it('numbers invoices per tenant and itemises the plan', function () {
    config(['billing.tax_rate' => 16]);
    $tenant = tenantWithRoles();
    $plan = Plan::factory()->create(['price_cents' => 500000, 'name' => 'Business']);

    [$subscription] = app(SubscriptionManager::class)->subscribe($tenant, $plan, PaymentProvider::Manual);
    $invoice = Invoice::query()->where('tenant_id', $tenant->id)->latest('id')->first();

    expect($invoice->number)->toStartWith('INV-'.$tenant->id.'-')
        ->and($invoice->subtotal_cents)->toBe(500000)
        ->and($invoice->tax_cents)->toBe(80000)
        ->and($invoice->total_cents)->toBe(580000)
        ->and($invoice->items()->count())->toBe(1)
        ->and($invoice->items()->first()->description)->toContain('Business');
});

it('marks an invoice paid manually', function () {
    $tenant = tenantWithRoles();
    $plan = Plan::factory()->create(['price_cents' => 300000]);
    [$subscription, $invoice, $payment] = app(SubscriptionManager::class)->subscribe($tenant, $plan, PaymentProvider::Manual);

    app(InvoiceService::class)->markPaid($invoice, $payment);

    expect($invoice->fresh()->isPaid())->toBeTrue()
        ->and($invoice->fresh()->amount_paid_cents)->toBe($invoice->total_cents)
        ->and($payment->fresh()->status->value)->toBe('succeeded');
});

it('exposes a tenant billing summary to a user with billing.view', function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $tenant = tenantWithRoles(['slug' => 'summary-co']);
    $plan = Plan::factory()->create();
    app(SubscriptionManager::class)->startTrial($tenant, $plan);
    $user = userForTenant($tenant, Role::TenantOwner);

    $this->actingAs($user)
        ->getJson('http://summary-co.hrm.test/billing')
        ->assertOk()
        ->assertJsonPath('data.status', 'trialing')
        ->assertJsonPath('data.has_app_access', true);
});

it('forbids the billing summary without billing.view', function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $tenant = tenantWithRoles(['slug' => 'noview-co']);
    $plan = Plan::factory()->create();
    app(SubscriptionManager::class)->startTrial($tenant, $plan);
    $user = userForTenant($tenant, Role::Employee);

    $this->actingAs($user)
        ->getJson('http://noview-co.hrm.test/billing')
        ->assertForbidden();
});
