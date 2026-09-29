<?php

declare(strict_types=1);

use App\Enums\CallParticipantStatus;
use App\Enums\CallStatus;
use App\Enums\Role;
use App\Events\CallDeclined;
use App\Events\CallEnded;
use App\Events\IncomingCall;
use App\Exceptions\CallException;
use App\Services\Calls\CallService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->tenant = tenantWithRoles(['slug' => 'call-co']);
    app(TenantContext::class)->set($this->tenant);
    $this->caller = userForTenant($this->tenant, Role::HrManager);
    $this->callee = userForTenant($this->tenant, Role::Employee);
});

it('starts a call, joining the caller and ringing every invitee', function () {
    Event::fake([IncomingCall::class]);

    $call = app(CallService::class)->start($this->caller, [$this->callee->id]);

    expect($call->status)->toBe(CallStatus::Ringing)
        ->and($call->tenant_id)->toBe($this->tenant->id);

    $callerParticipant = $call->participants()->where('user_id', $this->caller->id)->firstOrFail();
    $calleeParticipant = $call->participants()->where('user_id', $this->callee->id)->firstOrFail();

    expect($callerParticipant->status)->toBe(CallParticipantStatus::Joined)
        ->and($calleeParticipant->status)->toBe(CallParticipantStatus::Invited);

    Event::assertDispatched(IncomingCall::class, fn (IncomingCall $e): bool => $e->calleeUserId === $this->callee->id && $e->callUuid === $call->uuid);
});

it('refuses to start a call with no invitees', function () {
    app(CallService::class)->start($this->caller, []);
})->throws(CallException::class);

it('ignores an invitee id outside the caller\'s tenant', function () {
    $stranger = userForTenant(tenantWithRoles(['slug' => 'rival-call-co']), Role::Employee);

    $call = app(CallService::class)->start($this->caller, [$stranger->id]);
})->throws(CallException::class); // no valid invitees left once the stranger is filtered out

it('marks the call active once an invitee accepts', function () {
    $call = app(CallService::class)->start($this->caller, [$this->callee->id]);

    app(CallService::class)->accept($call, $this->callee);

    expect($call->fresh()->status)->toBe(CallStatus::Active);
    $participant = $call->participants()->where('user_id', $this->callee->id)->firstOrFail();
    expect($participant->status)->toBe(CallParticipantStatus::Joined);
});

it('notifies the caller when an invitee declines', function () {
    Event::fake([CallDeclined::class]);
    $call = app(CallService::class)->start($this->caller, [$this->callee->id]);

    app(CallService::class)->decline($call, $this->callee);

    $participant = $call->participants()->where('user_id', $this->callee->id)->firstOrFail();
    expect($participant->status)->toBe(CallParticipantStatus::Declined);
    Event::assertDispatched(CallDeclined::class, fn (CallDeclined $e): bool => $e->call->is($call) && $e->declinedByName === $this->callee->name);
});

it('ends the call automatically once the last joined participant leaves', function () {
    Event::fake([CallEnded::class]);
    $call = app(CallService::class)->start($this->caller, [$this->callee->id]);
    app(CallService::class)->accept($call, $this->callee);

    app(CallService::class)->leave($call, $this->caller);
    expect($call->fresh()->status)->toBe(CallStatus::Active); // callee still joined

    app(CallService::class)->leave($call, $this->callee);
    expect($call->fresh()->status)->toBe(CallStatus::Ended);

    Event::assertDispatched(CallEnded::class);
});

it('marks invitees who never answered as missed when the call ends', function () {
    $call = app(CallService::class)->start($this->caller, [$this->callee->id]);

    app(CallService::class)->endForEveryone($call);

    expect($call->fresh()->status)->toBe(CallStatus::Ended);
    $participant = $call->participants()->where('user_id', $this->callee->id)->firstOrFail();
    expect($participant->status)->toBe(CallParticipantStatus::Missed);
});

it('tells a still-ringing invitee\'s own channel the call ended, so their ringtone can stop', function () {
    Event::fake([CallEnded::class]);
    $call = app(CallService::class)->start($this->caller, [$this->callee->id]);

    app(CallService::class)->endForEveryone($call);

    Event::assertDispatched(CallEnded::class, fn (CallEnded $e): bool => $e->call->is($call) && $e->missedUserIds === [$this->callee->id]);
});

it('does not list an already-joined participant as missed when the call ends', function () {
    Event::fake([CallEnded::class]);
    $call = app(CallService::class)->start($this->caller, [$this->callee->id]);
    app(CallService::class)->accept($call, $this->callee);

    app(CallService::class)->endForEveryone($call);

    Event::assertDispatched(CallEnded::class, fn (CallEnded $e): bool => $e->missedUserIds === []);
});

it('lets more people be invited into an in-progress call', function () {
    Event::fake([IncomingCall::class]);
    $call = app(CallService::class)->start($this->caller, [$this->callee->id]);
    $third = userForTenant($this->tenant, Role::Employee);

    $added = app(CallService::class)->invite($call, $this->caller, [$third->id]);

    expect($added)->toHaveCount(1);
    expect($call->participants()->where('user_id', $third->id)->exists())->toBeTrue();
    Event::assertDispatched(IncomingCall::class, fn (IncomingCall $e): bool => $e->calleeUserId === $third->id);
});

it('refuses to invite more people into an ended call', function () {
    $call = app(CallService::class)->start($this->caller, [$this->callee->id]);
    app(CallService::class)->endForEveryone($call);
    $third = userForTenant($this->tenant, Role::Employee);

    app(CallService::class)->invite($call, $this->caller, [$third->id]);
})->throws(CallException::class);

it('refuses actions from a user outside the call\'s tenant', function () {
    $call = app(CallService::class)->start($this->caller, [$this->callee->id]);
    $stranger = userForTenant(tenantWithRoles(['slug' => 'rival-call-co-2']), Role::HrManager);

    app(CallService::class)->accept($call, $stranger);
})->throws(CallException::class);

it('refuses actions from a tenant member who was never invited', function () {
    $call = app(CallService::class)->start($this->caller, [$this->callee->id]);
    $bystander = userForTenant($this->tenant, Role::Employee);

    app(CallService::class)->accept($call, $bystander);
})->throws(CallException::class);
