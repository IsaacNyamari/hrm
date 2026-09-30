<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\Payroll\PayrollService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'hard-co']);
    $this->owner = userForTenant($this->tenant, Role::HrAdministrator);
});

it('keeps the platform area closed until the operator has confirmed two-factor authentication', function () {
    config(['security.require_two_factor_platform' => true]);
    $admin = User::factory()->platformAdmin(Role::SuperAdmin)->create();

    $this->actingAs($admin)->get('/platform')->assertRedirect(route('security.edit', absolute: false));

    $admin->forceFill(['two_factor_confirmed_at' => now()])->save();

    $this->actingAs($admin->fresh())->get('/platform')->assertOk();
});

it('issues API tokens that expire, 90 days by default and never beyond the maximum', function () {
    $this->travelTo(CarbonImmutable::create(2026, 10, 1));

    $default = $this->actingAs($this->owner)
        ->postJson('http://hard-co.hrm.test/api-tokens', ['name' => 'ERP'])
        ->assertCreated();

    expect(CarbonImmutable::parse($default->json('data.expires_at'))->toDateString())->toBe('2026-12-30');

    $this->actingAs($this->owner)
        ->postJson('http://hard-co.hrm.test/api-tokens', ['name' => 'Forever', 'expires_in_days' => 4000])
        ->assertUnprocessable();

    $this->travelBack();
});

it('refuses an expired API token', function () {
    $token = $this->owner->createToken('old', ['employees:read'], now()->subDay());

    $this->withToken($token->plainTextToken)
        ->getJson('http://hard-co.hrm.test/api/v1/employees')
        ->assertUnauthorized();
});

it('sends browsers that open a payroll run to the payroll screen but still serves JSON to API clients', function () {
    $run = app(PayrollService::class)->run(
        PayrollPeriod::factory()->forTenant($this->tenant)->forMonth(2026, 9)->create(),
    );

    $this->actingAs($this->owner)->get("http://hard-co.hrm.test/payroll-runs/{$run->uuid}")->assertRedirect(route('payroll'));
    $this->actingAs($this->owner)->getJson("http://hard-co.hrm.test/payroll-runs/{$run->uuid}")->assertOk()->assertJsonPath('data.uuid', $run->uuid);
});

it('sends a report-only content security policy', function () {
    $this->get('/')->assertHeader('Content-Security-Policy-Report-Only');
});
