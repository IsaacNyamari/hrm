<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\Developers\Index as DevelopersPage;
use App\Models\DeviceToken;
use App\Models\Employee;
use App\Services\SelfService\SelfServiceDashboard;
use App\Support\TenantContext;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'prof-co', 'name' => 'Prof Co']);
    $this->user = userForTenant($this->tenant, Role::Employee);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create([
        'user_id' => $this->user->id, 'salary_amount_cents' => 8_000_000,
    ]);
});

it('returns the signed-in profile with permissions for role-aware screens', function () {
    $supervisor = userForTenant($this->tenant, Role::Supervisor);
    $supervisorEmployee = Employee::factory()->forTenant($this->tenant)->create(['user_id' => $supervisor->id]);
    $this->employee->forceFill(['manager_id' => $supervisorEmployee->id])->save();

    asMobileUser($supervisor)
        ->getJson('/api/v1/me/profile')
        ->assertOk()
        ->assertJsonPath('data.self_service_only', false)
        ->assertJsonPath('data.manages_people', true)
        ->assertJsonPath('data.tenant.name', 'Prof Co')
        ->assertJson(fn ($json) => $json->where('data.abilities', fn ($abilities) => collect($abilities)->contains('leave.approve'))->etc());
});

it('returns exactly the web My HR dashboard payload', function () {
    $response = asMobileUser($this->user)
        ->getJson('/api/v1/me/dashboard')
        ->assertOk();

    app(TenantContext::class)->set($this->tenant);
    $expected = app(SelfServiceDashboard::class)->for($this->employee->fresh());

    expect($response->json('data.earnings_to_date'))->toEqual($expected['earnings_to_date'])
        ->and($response->json('data.salary'))->toEqual($expected['salary'])
        ->and($response->json('data.leave.balances'))->toEqual($expected['leave']['balances'])
        ->and($response->json('data.job'))->toEqual($expected['job']);
});

it('422s the dashboard for a user without an employee record', function () {
    asMobileUser(userForTenant($this->tenant, Role::Employee))
        ->getJson('/api/v1/me/dashboard')
        ->assertStatus(422)
        ->assertJsonPath('message', 'Your account is not linked to an employee record.');
});

it('rejects requests without a token', function () {
    $this->getJson('/api/v1/me/dashboard')->assertUnauthorized();
});

it('lists mobile sessions and signs other devices out', function () {
    $current = mobileToken($this->user, 'This phone');
    $other = mobileToken($this->user, 'Old tablet');
    $this->user->createToken('ERP', ['employees:read']);

    $otherId = PersonalAccessToken::findToken($other)->id;
    DeviceToken::factory()->forUser($this->user)->create(['personal_access_token_id' => $otherId]);

    $this->withToken($current)
        ->getJson('/api/v1/me/sessions')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['device_name' => 'This phone', 'current' => true])
        ->assertJsonFragment(['device_name' => 'Old tablet', 'current' => false]);

    $this->withToken($current)->deleteJson('/api/v1/me/sessions')->assertNoContent();

    expect($this->user->tokens()->pluck('name')->all())->toEqualCanonicalizing(['This phone', 'ERP'])
        ->and(DeviceToken::withoutTenantScope()->count())->toBe(0);
});

it('signs out one device and never another user\'s', function () {
    $current = mobileToken($this->user);
    $otherId = PersonalAccessToken::findToken(mobileToken($this->user, 'Spare'))->id;
    $strangerId = PersonalAccessToken::findToken(mobileToken(userForTenant($this->tenant, Role::Employee)))->id;

    $this->withToken($current)->deleteJson("/api/v1/me/sessions/{$strangerId}")->assertNotFound();
    $this->withToken($current)->deleteJson("/api/v1/me/sessions/{$otherId}")->assertNoContent();

    expect(PersonalAccessToken::query()->whereKey($otherId)->exists())->toBeFalse()
        ->and(PersonalAccessToken::query()->whereKey($strangerId)->exists())->toBeTrue();
});

it('shows mobile devices separately on the Developers page and signs one out', function () {
    $hr = userForTenant($this->tenant, Role::HrAdministrator);
    $hr->createToken('ERP', ['employees:read']);
    $deviceId = PersonalAccessToken::findToken(mobileToken($hr, 'Galaxy S25'))->id;
    DeviceToken::factory()->forUser($hr)->create(['personal_access_token_id' => $deviceId]);

    app(TenantContext::class)->set($this->tenant);

    Livewire::actingAs($hr)
        ->test(DevelopersPage::class)
        ->assertSee('Mobile app devices')
        ->assertSee('Galaxy S25')
        ->call('signOutDevice', $deviceId)
        ->assertSee('Device signed out.');

    expect($hr->tokens()->pluck('name')->all())->toBe(['ERP'])
        ->and(DeviceToken::withoutTenantScope()->where('user_id', $hr->id)->exists())->toBeFalse();
});
