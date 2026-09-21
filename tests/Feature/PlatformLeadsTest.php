<?php

declare(strict_types=1);

use App\Enums\LeadStatus;
use App\Enums\Role;
use App\Livewire\Platform\Leads;
use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\RegistrationInvitation;
use App\Models\User;
use App\Notifications\RegistrationAccessNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('lets a platform admin send an access link from a lead', function () {
    Notification::fake();
    $admin = User::factory()->platformAdmin(Role::SuperAdmin)->create();
    $lead = Lead::factory()->create(['name' => 'Jane', 'email' => 'jane@acme.test', 'company' => 'Acme']);

    $this->actingAs($admin)->get(route('platform.leads'))->assertOk()->assertSee('jane@acme.test');

    Livewire::test(Leads::class)
        ->call('sendAccess', $lead->uuid)
        ->assertSet('notice', 'Access link sent to jane@acme.test.');

    $invitation = RegistrationInvitation::query()->sole();

    expect($lead->fresh()->status)->toBe(LeadStatus::Invited)
        ->and($invitation->lead_id)->toBe($lead->id)
        ->and($invitation->invited_by)->toBe($admin->id)
        ->and($invitation->company)->toBe('Acme')
        ->and(AuditLog::query()->where('event', 'platform.lead.access_sent')->exists())->toBeTrue();

    Notification::assertSentTo(new AnonymousNotifiable, RegistrationAccessNotification::class);
});

it('shows the link once to the admin when outgoing mail only logs', function () {
    Notification::fake();
    config(['mail.default' => 'log']);
    $this->actingAs(User::factory()->platformAdmin(Role::SuperAdmin)->create());
    $lead = Lead::factory()->create();

    $component = Livewire::test(Leads::class)->call('sendAccess', $lead->uuid);

    expect($component->get('manualLink'))->toStartWith(url('/join/'));
});

it('does not expose the link when real mail is configured', function () {
    Notification::fake();
    config(['mail.default' => 'smtp']);
    $this->actingAs(User::factory()->platformAdmin(Role::SuperAdmin)->create());

    Livewire::test(Leads::class)
        ->call('sendAccess', Lead::factory()->create()->uuid)
        ->assertSet('manualLink', null);
});

it('marks a lead as declined', function () {
    $this->actingAs(User::factory()->platformAdmin(Role::SuperAdmin)->create());
    $lead = Lead::factory()->create();

    Livewire::test(Leads::class)->call('setStatus', $lead->uuid, 'declined');

    expect($lead->fresh()->status)->toBe(LeadStatus::Declined);
});

it('forbids platform staff without the leads permission', function () {
    $this->actingAs(User::factory()->platformAdmin(Role::SupportAdmin)->create())
        ->get(route('platform.leads'))
        ->assertForbidden();
});

it('forbids tenant users', function () {
    $tenant = tenantWithRoles();

    $this->actingAs(userForTenant($tenant, Role::TenantOwner))
        ->get(route('platform.leads'))
        ->assertForbidden();
});
