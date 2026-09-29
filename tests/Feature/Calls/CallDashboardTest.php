<?php

declare(strict_types=1);

use App\Enums\CallStatus;
use App\Enums\Role;
use App\Events\IncomingCall;
use App\Livewire\Calls\IncomingCallListener;
use App\Livewire\Calls\Room;
use App\Models\Call;
use App\Services\Calls\CallService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = tenantWithRoles(['slug' => 'calldash-co']);
    app(TenantContext::class)->set($this->tenant);
    $this->caller = userForTenant($this->tenant, Role::HrManager);
    $this->callee = userForTenant($this->tenant, Role::Employee);
});

it('starts a call from the dashboard and tells the browser which room to join', function () {
    Event::fake([IncomingCall::class]);
    $this->actingAs($this->caller);

    Livewire::test(Room::class)
        ->call('startCall', [$this->callee->id])
        ->assertDispatched('call-started');

    expect(Call::query()->where('created_by', $this->caller->id)->exists())->toBeTrue();
});

it('toasts instead of starting a call with no one selected', function () {
    $this->actingAs($this->caller);

    Livewire::test(Room::class)
        ->call('startCall', [])
        ->assertDispatched('toast-show')
        ->assertNotDispatched('call-started');
});

it('accepts an incoming call and redirects into the room', function () {
    $call = app(CallService::class)->start($this->caller, [$this->callee->id]);
    $this->actingAs($this->callee);

    Livewire::test(IncomingCallListener::class)
        ->call('accept', $call->uuid)
        ->assertRedirect(route('calls', ['call' => $call->uuid]));

    expect($call->fresh()->status)->toBe(CallStatus::Active);
});

it('declines an incoming call without joining it', function () {
    $call = app(CallService::class)->start($this->caller, [$this->callee->id]);
    $this->actingAs($this->callee);

    Livewire::test(IncomingCallListener::class)->call('decline', $call->uuid);

    expect($call->participants()->where('user_id', $this->callee->id)->first()->status->value)->toBe('declined');
});

it('lets the caller end a call for everyone but not a bystander', function () {
    $call = app(CallService::class)->start($this->caller, [$this->callee->id]);

    $this->actingAs($this->callee);
    Livewire::test(Room::class)
        ->set('call', $call->uuid)
        ->call('endCallForEveryone');

    expect($call->fresh()->status)->not->toBe(CallStatus::Ended);

    $this->actingAs($this->caller);
    Livewire::test(Room::class)
        ->set('call', $call->uuid)
        ->call('endCallForEveryone');

    expect($call->fresh()->status)->toBe(CallStatus::Ended);
});

it('lets a participant leave a call from the dashboard', function () {
    $call = app(CallService::class)->start($this->caller, [$this->callee->id]);
    app(CallService::class)->accept($call, $this->callee);

    $this->actingAs($this->callee);
    Livewire::test(Room::class)
        ->set('call', $call->uuid)
        ->call('leaveCall')
        ->assertSet('call', '');

    expect($call->participants()->where('user_id', $this->callee->id)->first()->status->value)->toBe('left');
});
