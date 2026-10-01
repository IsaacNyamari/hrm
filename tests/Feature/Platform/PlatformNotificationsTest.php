<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Illuminate\Notifications\Notification;

class FakePlatformNotification extends Notification
{
    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['type' => 'platform_event', 'subject' => 'Platform subject'];
    }
}

it('shows a platform admin their own notifications instead of a 404', function () {
    $admin = User::factory()->platformAdmin(Role::SuperAdmin)->create();
    $admin->notify(new FakePlatformNotification);

    $this->actingAs($admin)
        ->get(route('platform.notifications'))
        ->assertOk()
        ->assertSee('Platform subject');
});
