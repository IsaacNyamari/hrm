<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Events\IncomingCall;
use App\Services\Calls\CallService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->tenant = tenantWithRoles(['slug' => 'callauth-co']);
    app(TenantContext::class)->set($this->tenant);
    $this->caller = userForTenant($this->tenant, Role::HrManager);
    $this->callee = userForTenant($this->tenant, Role::Employee);

    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);
    Broadcast::purge();
    require base_path('routes/channels.php');
});

it('lets any tenant member join the tenant-wide calls presence channel', function () {
    $channel = 'presence-tenant.'.$this->tenant->id.'.calls';
    $auth = fn ($user) => $this->actingAs($user)->post('/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '1234.5678']);

    $auth($this->caller)->assertOk();

    $stranger = userForTenant(tenantWithRoles(['slug' => 'rival-callauth-co']), Role::HrManager);
    $auth($stranger)->assertForbidden();
});

it('only lets an invited participant join a specific call\'s presence channel', function () {
    Event::fake([IncomingCall::class]);
    $call = app(CallService::class)->start($this->caller, [$this->callee->id]);
    $channel = 'presence-call.'.$call->uuid;
    $auth = fn ($user) => $this->actingAs($user)->post('/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '1234.5678']);

    $auth($this->caller)->assertOk();
    $auth($this->callee)->assertOk();

    $bystander = userForTenant($this->tenant, Role::Employee);
    $auth($bystander)->assertForbidden();

    $stranger = userForTenant(tenantWithRoles(['slug' => 'rival-callauth-co-2']), Role::HrManager);
    $auth($stranger)->assertForbidden();
});

it('refuses to authorise a channel for a call that does not exist', function () {
    $channel = 'presence-call.'.Str::uuid();
    $this->actingAs($this->caller)
        ->post('/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '1234.5678'])
        ->assertForbidden();
});
