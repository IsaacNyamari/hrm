<?php

declare(strict_types=1);

use App\Actions\Tenancy\SendRegistrationInvitation;
use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Enums\SubscriptionStatus;
use App\Models\Lead;
use App\Models\Plan;
use App\Models\RegistrationInvitation;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\RegistrationAccessNotification;
use App\Support\TenantContext;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    Plan::factory()->create(['slug' => 'starter', 'sort_order' => 1, 'trial_days' => 14]);
    $this->token = Str::random(64);
});

function makeInvitation(object $test, array $attributes = []): RegistrationInvitation
{
    return RegistrationInvitation::factory()->withToken($test->token)->create([
        'email' => 'owner@newco.test',
        'company' => 'NewCo',
        'name' => 'Nia Owner',
        ...$attributes,
    ]);
}

function registration(): array
{
    return ['organization' => 'NewCo Kenya', 'name' => 'Nia Owner', 'password' => 'Str0ng!Passw0rd', 'password_confirmation' => 'Str0ng!Passw0rd'];
}

it('emails a single-use link and marks the lead as invited', function () {
    Notification::fake();
    $lead = Lead::factory()->create(['email' => 'Owner@NewCo.test']);

    $invitation = app(SendRegistrationInvitation::class)->handle($lead->email, $lead->name, $lead->company, $lead);

    expect($invitation->email)->toBe('owner@newco.test')
        ->and($invitation->token_hash)->toHaveLength(64)
        ->and($lead->fresh()->status)->toBe(LeadStatus::Invited);

    Notification::assertSentTo(
        new AnonymousNotifiable,
        RegistrationAccessNotification::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'owner@newco.test',
    );
});

it('revokes an earlier unopened link when a new one is sent', function () {
    Notification::fake();
    $first = app(SendRegistrationInvitation::class)->handle('owner@newco.test');
    app(SendRegistrationInvitation::class)->handle('owner@newco.test');

    expect($first->fresh()->canBeOpened())->toBeFalse();
});

it('does not consume the link when it is only viewed, so mail scanners cannot burn it', function () {
    $invitation = makeInvitation($this);

    $this->get(route('join.open', $this->token))->assertOk()->assertSee('Create my workspace');
    $this->get(route('join.open', $this->token))->assertOk()->assertSee('Create my workspace');

    expect($invitation->fresh()->opened_at)->toBeNull();
});

it('expires the link the moment it is opened', function () {
    $invitation = makeInvitation($this);

    $this->post(route('join.claim', $this->token))->assertRedirect(route('join.create'));

    expect($invitation->fresh()->opened_at)->not->toBeNull();

    // A second browser (fresh session) cannot use it again.
    $this->flushSession();
    $this->get(route('join.open', $this->token))->assertSee('This link is no longer valid');
    $this->post(route('join.claim', $this->token))->assertSee('This link is no longer valid');
});

it('creates the organization, owner and trial from an opened link', function () {
    $invitation = makeInvitation($this);

    $this->post(route('join.claim', $this->token));
    $this->get(route('join.create'))->assertOk()->assertSee('owner@newco.test');

    $this->post(route('join.store'), registration())->assertRedirect(route('dashboard'));

    $owner = User::query()->where('email', 'owner@newco.test')->sole();
    $tenant = Tenant::query()->findOrFail($owner->tenant_id);

    expect($tenant->name)->toBe('NewCo Kenya')
        ->and($tenant->slug)->toBe('newco-kenya')
        ->and($tenant->subscription()->first()->status)->toBe(SubscriptionStatus::Trialing)
        ->and($owner->email_verified_at)->not->toBeNull()
        ->and($invitation->fresh()->used_at)->not->toBeNull()
        ->and($invitation->fresh()->tenant_id)->toBe($tenant->id);

    app(TenantContext::class)->run($tenant, fn () => expect($owner->fresh()->hasRole(Role::TenantOwner->value))->toBeTrue());

    $this->assertAuthenticatedAs($owner);
});

it('cannot register a second organization with the same link', function () {
    makeInvitation($this);

    $this->post(route('join.claim', $this->token));
    $this->post(route('join.store'), registration());
    auth()->logout();

    $this->post(route('join.store'), [...registration(), 'organization' => 'Second Co'])->assertSee('This link is no longer valid');

    expect(Tenant::query()->where('name', 'Second Co')->exists())->toBeFalse();
});

it('rejects the registration form without an opened link', function () {
    $this->get(route('join.create'))->assertSee('This link is no longer valid');
    $this->post(route('join.store'), registration())->assertSee('This link is no longer valid');

    expect(User::query()->where('email', 'owner@newco.test')->exists())->toBeFalse();
});

it('closes the registration window after it runs out', function () {
    makeInvitation($this);
    $this->post(route('join.claim', $this->token));

    $this->travel(RegistrationInvitation::SESSION_MINUTES + 1)->minutes();

    $this->get(route('join.create'))->assertSee('This link is no longer valid');
});

it('rejects an expired link that was never opened', function () {
    makeInvitation($this, ['expires_at' => now()->subMinute()]);

    $this->get(route('join.open', $this->token))->assertSee('This link is no longer valid');
});

it('refuses to register an email that already has an account', function () {
    User::factory()->create(['email' => 'owner@newco.test']);
    makeInvitation($this);

    $this->post(route('join.claim', $this->token));
    $this->post(route('join.store'), registration())->assertSessionHasErrors('organization');

    expect(Tenant::query()->where('name', 'NewCo Kenya')->exists())->toBeFalse();
});
