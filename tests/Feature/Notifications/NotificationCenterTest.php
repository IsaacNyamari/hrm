<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Support\TenantContext;
use Illuminate\Notifications\Notification;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'ntf-co', 'name' => 'Ntf Co']);
    app(TenantContext::class)->set($this->tenant);
    $this->user = userForTenant($this->tenant, Role::HrManager);
});

class FakeCenterNotification extends Notification
{
    public function __construct(private readonly string $subject) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['type' => 'demo_event', 'subject' => $this->subject];
    }
}

it('lists the user notifications with an unread count', function () {
    $this->user->notify(new FakeCenterNotification('First'));
    $this->user->notify(new FakeCenterNotification('Second'));

    $response = $this->actingAs($this->user)
        ->getJson('http://ntf-co.hrm.test/notifications/feed')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.unread', 2);

    $subjects = collect($response->json('data'))->pluck('data.subject');
    expect($subjects)->toContain('First', 'Second');
});

it('marks one notification read', function () {
    $this->user->notify(new FakeCenterNotification('Hi'));
    $id = $this->user->notifications()->firstOrFail()->id;

    $this->actingAs($this->user)
        ->postJson("http://ntf-co.hrm.test/notifications/{$id}/read")
        ->assertOk()
        ->assertJsonPath('data.unread', 0);

    expect($this->user->unreadNotifications()->count())->toBe(0);
});

it('marks all read', function () {
    $this->user->notify(new FakeCenterNotification('a'));
    $this->user->notify(new FakeCenterNotification('b'));

    $this->actingAs($this->user)->postJson('http://ntf-co.hrm.test/notifications/read-all')->assertNoContent();

    expect($this->user->unreadNotifications()->count())->toBe(0);
});

it('reports the unread count', function () {
    $this->user->notify(new FakeCenterNotification('x'));

    $this->actingAs($this->user)
        ->getJson('http://ntf-co.hrm.test/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.unread', 1);
});

it('does not expose another user\'s notifications', function () {
    $other = userForTenant($this->tenant, Role::Employee);
    $other->notify(new FakeCenterNotification('secret'));

    $this->actingAs($this->user)
        ->getJson('http://ntf-co.hrm.test/notifications/feed')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
