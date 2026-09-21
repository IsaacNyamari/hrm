<?php

use App\Models\User;
use Illuminate\Support\Str;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the dashboard greets the user and shows the unread notification count in the top bar', function () {
    $user = User::factory()->create(['name' => 'Henry Klein']);

    foreach (range(1, 3) as $ignored) {
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'data' => ['message' => 'Hello'],
        ]);
    }

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Hi, welcome back!')
        ->assertSee('Henry Klein')
        ->assertSeeInOrder(['aria-label="Notifications"', '>3<'], false);
});
