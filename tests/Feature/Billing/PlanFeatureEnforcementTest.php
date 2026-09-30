<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\FeatureGate;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'gate-co']);
    $this->owner = userForTenant($this->tenant, Role::PayrollManager);
});

function subscribeToFlags($test, array $flags): void
{
    $plan = Plan::factory()->create(['features' => ['flags' => $flags, 'limits' => []]]);
    Subscription::factory()->forTenant($test->tenant)->onPlan($plan)->create();
    app(FeatureGate::class)->flush($test->tenant);
}

it('refuses payroll, API tokens and webhooks on a plan that does not include them', function () {
    subscribeToFlags($this, ['hr_core', 'leave', 'attendance', 'reports']);

    foreach (['payroll-periods', 'tax-rules', 'api-tokens', 'webhook-endpoints'] as $path) {
        $this->actingAs($this->owner)->getJson("http://gate-co.hrm.test/{$path}")->assertStatus(402);
    }

    $this->actingAs($this->owner)->get('http://gate-co.hrm.test/payroll')->assertRedirect(route('billing.index', absolute: false));
});

it('allows payroll once the plan includes it but still refuses plan features it lacks', function () {
    subscribeToFlags($this, ['hr_core', 'payroll']);

    $this->actingAs($this->owner)->getJson('http://gate-co.hrm.test/payroll-periods')->assertOk();
    $this->actingAs($this->owner)->getJson('http://gate-co.hrm.test/api-tokens')->assertStatus(402);
});

it('does not block a tenant that has no plan on record', function () {
    $this->actingAs($this->owner)->getJson('http://gate-co.hrm.test/payroll-periods')->assertOk();
});
