<?php

declare(strict_types=1);

use App\Actions\Tenancy\AcceptInvitation;
use App\Actions\Tenancy\SendInvitation;
use App\Enums\Role;
use App\Facades\Tenancy;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\TenantInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

it('creates a pending invitation and notifies the invitee', function () {
    Notification::fake();
    $tenant = tenantWithRoles();

    $invitation = app(SendInvitation::class)->handle($tenant, 'Jane@Example.com', Role::HrManager);

    expect($invitation->tenant_id)->toBe($tenant->id)
        ->and($invitation->email)->toBe('jane@example.com')
        ->and($invitation->isPending())->toBeTrue()
        ->and(strlen($invitation->token))->toBe(64);

    Notification::assertSentOnDemand(TenantInvitationNotification::class);
});

it('refreshes the token when re-inviting the same pending email', function () {
    Notification::fake();
    $tenant = tenantWithRoles();

    $first = app(SendInvitation::class)->handle($tenant, 'dup@example.com', Role::Employee);
    $second = app(SendInvitation::class)->handle($tenant, 'dup@example.com', Role::HrManager);

    expect(Invitation::query()->where('email', 'dup@example.com')->count())->toBe(1)
        ->and($second->id)->toBe($first->id)
        ->and($second->token)->not->toBe($first->token)
        ->and($second->fresh()->role)->toBe(Role::HrManager);
});

it('accepts an invitation, creating a tenant user with the assigned role', function () {
    $tenant = tenantWithRoles();
    $invitation = app(SendInvitation::class)->handle($tenant, 'newbie@example.com', Role::PayrollManager);

    $user = app(AcceptInvitation::class)->handle($invitation, 'New Bie', 'sup3r-s3cret-pass');

    expect($user->tenant_id)->toBe($tenant->id)
        ->and($user->email)->toBe('newbie@example.com')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();

    Tenancy::run($tenant, fn () => expect($user->hasRole(Role::PayrollManager->value))->toBeTrue());
});

it('rejects an expired invitation', function () {
    $tenant = tenantWithRoles();
    $invitation = app(SendInvitation::class)->handle($tenant, 'late@example.com', Role::Employee);
    $invitation->forceFill(['expires_at' => now()->subDay()])->save();

    app(AcceptInvitation::class)->handle($invitation, 'Late Comer', 'sup3r-s3cret-pass');
})->throws(ValidationException::class);

it('rejects a used invitation', function () {
    $tenant = tenantWithRoles();
    $invitation = app(SendInvitation::class)->handle($tenant, 'once@example.com', Role::Employee);
    app(AcceptInvitation::class)->handle($invitation, 'First Time', 'sup3r-s3cret-pass');

    app(AcceptInvitation::class)->handle($invitation->fresh(), 'Second Time', 'another-s3cret-pass');
})->throws(ValidationException::class);

it('shows the acceptance form for a valid token and 404s a bad one', function () {
    $tenant = tenantWithRoles();
    $invitation = app(SendInvitation::class)->handle($tenant, 'form@example.com', Role::Employee);

    $this->get(route('invitations.show', $invitation->token))
        ->assertOk()
        ->assertSee('form@example.com');

    $this->get(route('invitations.show', str_repeat('x', 64)))->assertNotFound();
});

it('accepts through the web form and logs the user in', function () {
    $tenant = tenantWithRoles();
    $invitation = app(SendInvitation::class)->handle($tenant, 'weblogin@example.com', Role::Employee);

    $response = $this->post(route('invitations.accept', $invitation->token), [
        'name' => 'Web Login',
        'password' => 'sup3r-s3cret-pass',
        'password_confirmation' => 'sup3r-s3cret-pass',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'weblogin@example.com', 'tenant_id' => $tenant->id]);
});

it('does not let an invitation be accepted when the email already has an account', function () {
    $tenant = tenantWithRoles();
    User::factory()->create(['email' => 'taken@example.com']);
    $invitation = app(SendInvitation::class)->handle($tenant, 'taken@example.com', Role::Employee);

    app(AcceptInvitation::class)->handle($invitation, 'Taken', 'sup3r-s3cret-pass');
})->throws(ValidationException::class);
