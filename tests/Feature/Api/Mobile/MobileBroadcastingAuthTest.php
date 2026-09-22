<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Broadcast;

beforeEach(function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);
    Broadcast::purge();
    require base_path('routes/channels.php');

    $this->tenant = tenantWithRoles(['slug' => 'echo-co']);
    app(TenantContext::class)->set($this->tenant);
    $this->employee = userForTenant($this->tenant, Role::Employee);
    $this->hr = userForTenant($this->tenant, Role::HrManager);
});

function authorizeChannel(object $test, string $token, string $channel)
{
    return $test->withToken($token)->postJson('/api/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '1234.5678']);
}

it('authorises a bearer token for the user\'s own private channel only', function () {
    authorizeChannel($this, mobileToken($this->employee), 'private-App.Models.User.'.$this->employee->id)
        ->assertOk()
        ->assertJsonStructure(['auth']);

    $this->app['auth']->forgetGuards();

    authorizeChannel($this, mobileToken($this->employee), 'private-App.Models.User.'.$this->hr->id)->assertForbidden();
});

it('authorises the organisation attendance channel for attendance staff', function () {
    $channel = 'private-tenant.'.$this->tenant->id.'.attendance';

    authorizeChannel($this, mobileToken($this->hr), $channel)->assertOk();

    $this->app['auth']->forgetGuards();
    authorizeChannel($this, mobileToken($this->employee), $channel)->assertForbidden();

    $this->app['auth']->forgetGuards();
    $outsider = userForTenant(tenantWithRoles(['slug' => 'echo-rival']), Role::HrManager);
    authorizeChannel($this, mobileToken($outsider), $channel)->assertForbidden();
});

it('rejects channel auth without a token', function () {
    $this->postJson('/api/broadcasting/auth', ['channel_name' => 'private-App.Models.User.'.$this->employee->id, 'socket_id' => '1234.5678'])
        ->assertUnauthorized();
});

it('keeps the web session channel auth working', function () {
    $this->actingAs($this->employee)
        ->post('/broadcasting/auth', ['channel_name' => 'private-App.Models.User.'.$this->employee->id, 'socket_id' => '1234.5678'])
        ->assertOk();
});
