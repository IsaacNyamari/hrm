<?php

declare(strict_types=1);

use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Subscription\SubscriptionManager;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->manager = app(SubscriptionManager::class);
});

it('starts a trial sized by the plan', function () {
    $tenant = tenantWithRoles();
    $plan = Plan::factory()->create(['trial_days' => 21]);

    $subscription = $this->manager->startTrial($tenant, $plan);

    expect($subscription->status)->toBe(SubscriptionStatus::Trialing)
        ->and($subscription->trial_ends_at->toDateString())->toBe(CarbonImmutable::now()->addDays(21)->toDateString())
        ->and($tenant->fresh()->status)->toBe(TenantStatus::Trialing)
        ->and($tenant->fresh()->hasAppAccess())->toBeTrue();
});

it('raises an open invoice and pending payment when subscribing to a paid plan', function () {
    $tenant = tenantWithRoles();
    $plan = Plan::factory()->create(['price_cents' => 500000]);

    [$subscription, $invoice, $payment] = $this->manager->subscribe($tenant, $plan, PaymentProvider::Mpesa, '254700111222');

    expect($subscription->status)->toBe(SubscriptionStatus::PastDue)
        ->and($invoice->total_cents)->toBe(500000)
        ->and($invoice->status->value)->toBe('open')
        ->and($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->amount_cents)->toBe(500000)
        ->and($tenant->fresh()->hasAppAccess())->toBeFalse();
});

it('activates immediately on a free plan with no invoice', function () {
    $tenant = tenantWithRoles();
    $plan = Plan::factory()->free()->create();

    [$subscription, $invoice, $payment] = $this->manager->subscribe($tenant, $plan, PaymentProvider::Manual);

    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($invoice)->toBeNull()
        ->and($payment)->toBeNull()
        ->and($tenant->fresh()->hasAppAccess())->toBeTrue();
});

it('changes plan in place', function () {
    $tenant = tenantWithRoles();
    $starter = Plan::factory()->create(['slug' => 'starter-x']);
    $business = Plan::factory()->create(['slug' => 'business-x']);

    $subscription = $this->manager->startTrial($tenant, $starter);
    $this->manager->changePlan($subscription, $business);

    expect($subscription->fresh()->plan_id)->toBe($business->id);
});

it('cancels with a grace period until the period end', function () {
    $tenant = tenantWithRoles();
    $plan = Plan::factory()->create();
    $subscription = Subscription::factory()->forTenant($tenant)->onPlan($plan)->create([
        'current_period_end' => CarbonImmutable::now()->addDays(10),
    ]);

    $this->manager->cancel($subscription);

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Cancelled)
        ->and($subscription->fresh()->grantsAppAccess())->toBeTrue(); // still in grace

    $subscription->fresh()->forceFill(['ends_at' => CarbonImmutable::now()->subDay()])->save();
    expect($subscription->fresh()->grantsAppAccess())->toBeFalse();
});

it('expires trials past their end date', function () {
    $tenant = tenantWithRoles();
    $plan = Plan::factory()->create();
    Subscription::factory()->forTenant($tenant)->onPlan($plan)->trialing()->create([
        'trial_ends_at' => CarbonImmutable::now()->subDay(),
    ]);

    $expired = $this->manager->expireOverdue();

    expect($expired)->toBe(1)
        ->and($tenant->fresh()->subscription->status)->toBe(SubscriptionStatus::Expired)
        ->and($tenant->fresh()->status)->toBe(TenantStatus::Expired);
});

it('moves an active subscription past its period end to past due, then expires it after grace', function () {
    config(['billing.grace_days' => 3]);
    $tenant = tenantWithRoles();
    $plan = Plan::factory()->create();
    $subscription = Subscription::factory()->forTenant($tenant)->onPlan($plan)->create([
        'status' => SubscriptionStatus::Active,
        'current_period_end' => CarbonImmutable::now()->subDay(),
    ]);

    $this->manager->expireOverdue();
    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::PastDue);

    $subscription->fresh()->forceFill(['current_period_end' => CarbonImmutable::now()->subDays(5)])->save();
    $this->manager->expireOverdue();
    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Expired);
});

it('runs through the console command', function () {
    $tenant = tenantWithRoles();
    $plan = Plan::factory()->create();
    Subscription::factory()->forTenant($tenant)->onPlan($plan)->trialing()->create([
        'trial_ends_at' => CarbonImmutable::now()->subDay(),
    ]);

    $this->artisan('subscriptions:expire')->assertSuccessful();

    expect($tenant->fresh()->subscription->status)->toBe(SubscriptionStatus::Expired);
});
