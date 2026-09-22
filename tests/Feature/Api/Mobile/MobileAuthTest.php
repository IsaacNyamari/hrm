<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\DeviceToken;
use App\Models\Employee;
use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->tenant = tenantWithRoles(['slug' => 'mob-co', 'name' => 'Mob Co', 'currency' => 'KES']);
    $this->user = userForTenant($this->tenant, Role::Employee);
    $this->user->forceFill(['email' => 'jane@mob.test'])->save();
    Employee::factory()->forTenant($this->tenant)->create(['user_id' => $this->user->id, 'job_title' => 'Accountant']);
});

function mobileLogin(array $overrides = []): array
{
    return array_merge(['email' => 'jane@mob.test', 'password' => 'password', 'device_name' => 'Pixel 9'], $overrides);
}

it('signs a user in and issues a mobile-only token named after the device', function () {
    $response = $this->postJson('/api/v1/auth/login', mobileLogin())
        ->assertOk()
        ->assertJsonPath('data.user.email', 'jane@mob.test')
        ->assertJsonPath('data.employee.job_title', 'Accountant')
        ->assertJsonPath('data.tenant.name', 'Mob Co')
        ->assertJsonPath('data.tenant.currency', 'KES')
        ->assertJsonPath('data.self_service_only', true)
        ->assertJsonStructure(['data' => ['token', 'abilities', 'roles', 'tenant' => ['branding']]]);

    expect($response->json('data.abilities'))->toContain('leave.request');

    $token = PersonalAccessToken::findToken($response->json('data.token'));
    expect($token->name)->toBe('Pixel 9')
        ->and($token->abilities)->toBe(['mobile'])
        ->and(LoginActivity::query()->where('user_id', $this->user->id)->where('successful', true)->exists())->toBeTrue();
});

it('rejects wrong credentials with a field error', function () {
    $this->postJson('/api/v1/auth/login', mobileLogin(['password' => 'nope']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('validates the sign-in payload', function () {
    $this->postJson('/api/v1/auth/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password', 'device_name']);
});

it('rate-limits sign-in attempts per email and IP', function () {
    foreach (range(1, 5) as $ignored) {
        $this->postJson('/api/v1/auth/login', mobileLogin(['password' => 'wrong']))->assertStatus(422);
    }

    $this->postJson('/api/v1/auth/login', mobileLogin())->assertStatus(429);
});

it('refuses platform admins, users without an organisation and unverified users', function () {
    $admin = User::factory()->platformAdmin()->create(['email' => 'ops@platform.test']);
    $this->postJson('/api/v1/auth/login', mobileLogin(['email' => $admin->email]))->assertForbidden();

    $orphan = User::factory()->create(['email' => 'nobody@nowhere.test']);
    $this->postJson('/api/v1/auth/login', mobileLogin(['email' => $orphan->email]))->assertForbidden();

    $this->user->forceFill(['email_verified_at' => null])->save();
    $this->postJson('/api/v1/auth/login', mobileLogin())->assertForbidden();
});

it('asks for a two-factor code, then issues the token for a valid TOTP code', function () {
    $google = app(Google2FA::class);
    $secret = $google->generateSecretKey();
    $this->user->forceFill([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['aaaa-bbbb'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $challenge = $this->postJson('/api/v1/auth/login', mobileLogin())
        ->assertOk()
        ->assertJsonPath('data.two_factor', true)
        ->assertJsonMissingPath('data.token')
        ->json('data.challenge_token');

    $this->postJson('/api/v1/auth/two-factor', ['challenge_token' => $challenge, 'code' => '000000'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('code');

    $this->postJson('/api/v1/auth/two-factor', ['challenge_token' => $challenge, 'code' => $google->getCurrentOtp($secret)])
        ->assertOk()
        ->assertJsonPath('data.user.email', 'jane@mob.test')
        ->assertJsonStructure(['data' => ['token']]);

    // The challenge is single-use.
    $this->postJson('/api/v1/auth/two-factor', ['challenge_token' => $challenge, 'code' => $google->getCurrentOtp($secret)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('challenge_token');
});

it('accepts a recovery code once and replaces it', function () {
    $this->user->forceFill([
        'two_factor_secret' => encrypt(app(Google2FA::class)->generateSecretKey()),
        'two_factor_recovery_codes' => encrypt(json_encode(['aaaa-bbbb', 'cccc-dddd'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $challenge = $this->postJson('/api/v1/auth/login', mobileLogin())->json('data.challenge_token');

    $this->postJson('/api/v1/auth/two-factor', ['challenge_token' => $challenge, 'recovery_code' => 'aaaa-bbbb'])
        ->assertOk();

    expect($this->user->fresh()->recoveryCodes())->not->toContain('aaaa-bbbb')->toContain('cccc-dddd');
});

it('expires an unknown two-factor challenge', function () {
    $this->postJson('/api/v1/auth/two-factor', ['challenge_token' => str_repeat('x', 64), 'code' => '123456'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('challenge_token');
});

it('revokes the current token and its push registration on logout', function () {
    $token = mobileToken($this->user);
    $accessToken = PersonalAccessToken::findToken($token);
    DeviceToken::factory()->forUser($this->user)->create(['personal_access_token_id' => $accessToken->id]);

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

    expect(PersonalAccessToken::query()->whereKey($accessToken->id)->exists())->toBeFalse()
        ->and(DeviceToken::withoutTenantScope()->where('user_id', $this->user->id)->exists())->toBeFalse();
});

it('always answers forgot-password the same way and emails a reset link when the account exists', function () {
    Notification::fake();

    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'jane@mob.test'])->assertOk();
    $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ghost@mob.test'])->assertOk();

    expect($unknown->json('data.message'))->toContain('If that email belongs to an account');
    Notification::assertSentTo($this->user, ResetPassword::class);
});

it('does not let an integration token reach the mobile endpoints, or a mobile token the integration ones', function () {
    $integration = $this->user->createToken('ERP', ['employees:read'])->plainTextToken;
    $this->withToken($integration)->getJson('/api/v1/me/dashboard')->assertForbidden();

    $this->app['auth']->forgetGuards();

    $mobile = mobileToken($this->user);
    $this->withToken($mobile)->getJson('/api/v1/employees')->assertForbidden();
});

it('returns 402 for a lapsed subscription but still allows sign-out', function () {
    $this->tenant->forceFill(['status' => 'expired', 'trial_ends_at' => now()->subDay()])->save();
    $token = mobileToken($this->user);

    $this->withToken($token)->getJson('/api/v1/me/dashboard')->assertStatus(402);
    $this->withToken($token)->getJson('/api/v1/me/profile')->assertOk();
    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
});

it('serves the public start-up config', function () {
    config(['mobile.reverb.key' => 'public-key', 'mobile.reverb.host' => 'hrm.example.com', 'mobile.reverb.port' => 443, 'mobile.reverb.scheme' => 'https', 'mobile.min_version' => '1.2.0']);

    $this->getJson('/api/v1/config')
        ->assertOk()
        ->assertJsonPath('data.reverb.key', 'public-key')
        ->assertJsonPath('data.reverb.host', 'hrm.example.com')
        ->assertJsonPath('data.reverb.port', 443)
        ->assertJsonPath('data.min_app_version', '1.2.0')
        ->assertJsonStructure(['data' => ['support' => ['email', 'phone', 'url'], 'broadcasting_auth_url']])
        ->assertJsonMissingPath('data.reverb.secret');
});
