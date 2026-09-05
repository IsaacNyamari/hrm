<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\FeatureGate;

beforeEach(function () {
    $this->gate = app(FeatureGate::class);
});

it('reads limits and feature flags from the active plan', function () {
    $tenant = tenantWithRoles();
    $plan = Plan::factory()->create([
        'employee_limit' => 20,
        'features' => ['flags' => ['payroll'], 'limits' => ['branches' => 2]],
    ]);
    Subscription::factory()->forTenant($tenant)->onPlan($plan)->create();
    $this->gate->flush($tenant);

    expect($this->gate->limit($tenant, 'employees'))->toBe(20)
        ->and($this->gate->limit($tenant, 'branches'))->toBe(2)
        ->and($this->gate->allows($tenant, 'payroll'))->toBeTrue()
        ->and($this->gate->allows($tenant, 'recruitment'))->toBeFalse();
});

it('enforces a numeric limit', function () {
    $tenant = tenantWithRoles();
    $plan = Plan::factory()->create(['employee_limit' => 3]);
    Subscription::factory()->forTenant($tenant)->onPlan($plan)->create();
    $this->gate->flush($tenant);

    expect($this->gate->withinLimit($tenant, 'employees', 2))->toBeTrue()
        ->and($this->gate->withinLimit($tenant, 'employees', 3))->toBeFalse()
        ->and($this->gate->remaining($tenant, 'employees', 1))->toBe(2);
});

it('treats a null limit as unlimited', function () {
    $tenant = tenantWithRoles();
    $plan = Plan::factory()->unlimited()->create();
    Subscription::factory()->forTenant($tenant)->onPlan($plan)->create();
    $this->gate->flush($tenant);

    expect($this->gate->withinLimit($tenant, 'employees', 999999))->toBeTrue()
        ->and($this->gate->remaining($tenant, 'employees', 10))->toBeNull();
});
