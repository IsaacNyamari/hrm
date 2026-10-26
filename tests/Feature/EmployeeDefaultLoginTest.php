<?php

declare(strict_types=1);

use App\Actions\Employees\CreateEmployee;
use App\Actions\Employees\CreateStaffAccount;
use App\Models\User;
use App\Notifications\WelcomeNewHireNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    config(['tenancy.central_domains' => ['hrm.test']]);

    $this->tenant = tenantWithRoles(['slug' => 'default-login-co']);
    $this->employee = app(CreateEmployee::class)->handle($this->tenant, [
        'first_name' => 'Wanjiru',
        'last_name' => 'Mwangi',
        'email' => 'wanjiru@example.com',
    ]);
    $this->user = User::query()->where('email', 'wanjiru@example.com')->firstOrFail();
});

it('creates a login with the email as password and emails a 7-day setup link', function () {
    expect($this->employee->user_id)->toBe($this->user->id)
        ->and($this->user->must_change_password)->toBeTrue();

    Notification::assertSentTo($this->user, WelcomeNewHireNotification::class);

    $url = CreateStaffAccount::welcomeUrl($this->user);

    $this->travelTo(now()->addDays(CreateStaffAccount::LINK_DAYS)->subMinute());
    $this->get($url)->assertOk();
});

it('signs in with the email as password, then forces a password change', function () {
    $this->post('/login', ['email' => 'wanjiru@example.com', 'password' => 'wanjiru@example.com']);
    $this->assertAuthenticatedAs($this->user);

    $this->get(route('portal'))->assertRedirect(route('password.change'));

    $this->post(route('password.change.update'), ['password' => 'wanjiru@example.com', 'password_confirmation' => 'wanjiru@example.com'])
        ->assertSessionHasErrors('password');

    $this->post(route('password.change.update'), ['password' => 'Sup3r-Secret!pass', 'password_confirmation' => 'Sup3r-Secret!pass'])
        ->assertRedirect(route('portal'));

    expect($this->user->fresh()->must_change_password)->toBeFalse();
    $this->get(route('password.change'))->assertRedirect(route('portal'));
});

it('revokes the setup link once it has been opened', function () {
    $url = CreateStaffAccount::welcomeUrl($this->user);

    $this->get($url)->assertOk()->assertSee('Welcome, Wanjiru!');
    $this->get($url)->assertSee('This link is no longer valid');

    $this->post($url, ['password' => 'Sup3r-Secret!pass', 'password_confirmation' => 'Sup3r-Secret!pass'])
        ->assertRedirect(route('portal'));

    expect($this->user->fresh()->must_change_password)->toBeFalse();
});

it('refuses a setup link submitted from a browser that never opened it', function () {
    $this->post(CreateStaffAccount::welcomeUrl($this->user), ['password' => 'Sup3r-Secret!pass', 'password_confirmation' => 'Sup3r-Secret!pass'])
        ->assertSee('This link is no longer valid');

    $this->assertGuest();
});

it('expires the setup link after 7 days', function () {
    $url = CreateStaffAccount::welcomeUrl($this->user);

    $this->travelTo(now()->addDays(CreateStaffAccount::LINK_DAYS)->addMinute());

    $this->get($url)->assertSee('This link is no longer valid');
});
