<?php

declare(strict_types=1);

use App\Models\LoginActivity;
use App\Models\User;

it('records a successful login with request context', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();

    $activity = LoginActivity::query()->where('user_id', $user->id)->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->successful)->toBeTrue()
        ->and($activity->email)->toBe($user->email)
        ->and($activity->ip_address)->not->toBeNull();
});

it('records a failed login attempt', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();

    $activity = LoginActivity::query()->where('email', $user->email)->where('successful', false)->first();

    expect($activity)->not->toBeNull()
        ->and($activity->reason)->toBe('invalid_credentials');
});
