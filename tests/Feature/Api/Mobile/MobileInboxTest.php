<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->tenant = tenantWithRoles(['slug' => 'inbox-co']);
    app(TenantContext::class)->set($this->tenant);
    $this->user = userForTenant($this->tenant, Role::Employee);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create(['user_id' => $this->user->id]);
    $this->token = mobileToken($this->user);
});

function storeNotification(User $user, array $data, bool $read = false): string
{
    $id = (string) Str::uuid();

    $user->notifications()->create([
        'id' => $id,
        'type' => 'App\\Notifications\\TaskAssignedNotification',
        'data' => $data,
        'read_at' => $read ? now() : null,
    ]);

    return $id;
}

it('lists notifications with toast-style titles, deep-link data and the unread count', function () {
    storeNotification($this->user, ['type' => 'task_assigned', 'task_uuid' => 'abc', 'title' => 'File the report']);
    storeNotification($this->user, ['type' => 'leave_decision', 'leave_request_uuid' => 'xyz', 'status' => 'approved'], read: true);
    storeNotification(userForTenant($this->tenant, Role::Employee), ['type' => 'task_assigned']);

    $this->withToken($this->token)
        ->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.unread', 1)
        ->assertJsonFragment(['title' => 'Task Assigned', 'detail' => 'File the report'])
        ->assertJsonFragment(['task_uuid' => 'abc']);

    $this->withToken($this->token)->getJson('/api/v1/notifications?unread=1')->assertJsonCount(1, 'data');
});

it('marks one or all notifications read', function () {
    $first = storeNotification($this->user, ['type' => 'task_assigned']);
    storeNotification($this->user, ['type' => 'task_assigned']);

    $this->withToken($this->token)->postJson("/api/v1/notifications/{$first}/read")->assertOk()->assertJsonPath('data.unread', 1);
    $this->withToken($this->token)->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.unread', 1);
    $this->withToken($this->token)->postJson('/api/v1/notifications/read-all')->assertOk()->assertJsonPath('data.unread', 0);

    expect($this->user->unreadNotifications()->count())->toBe(0);
});

it('will not mark another user\'s notification', function () {
    $theirs = storeNotification(userForTenant($this->tenant, Role::Employee), ['type' => 'task_assigned']);

    $this->withToken($this->token)->postJson("/api/v1/notifications/{$theirs}/read")->assertNotFound();
});

it('lists announcements visible to the employee and records reads', function () {
    $finance = Department::factory()->forTenant($this->tenant)->create();
    $all = Announcement::factory()->forTenant($this->tenant)->create(['title' => 'Office closed Friday']);
    Announcement::factory()->forTenant($this->tenant)->create(['title' => 'Finance only', 'audience' => 'department', 'department_id' => $finance->id]);
    Announcement::factory()->forTenant($this->tenant)->draft()->create(['title' => 'Draft']);

    $other = tenantWithRoles(['slug' => 'other-inbox']);
    Announcement::factory()->forTenant($other)->create(['title' => 'Other organisation']);

    $this->withToken($this->token)
        ->getJson('/api/v1/announcements')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Office closed Friday')
        ->assertJsonPath('data.0.read', false);

    $this->withToken($this->token)->postJson("/api/v1/announcements/{$all->uuid}/read")->assertOk();
    $this->withToken($this->token)->getJson('/api/v1/announcements')->assertJsonPath('data.0.read', true);
});
