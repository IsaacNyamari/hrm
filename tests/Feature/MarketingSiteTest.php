<?php

declare(strict_types=1);

use App\Models\Plan;

it('renders every public marketing page', function (string $route) {
    $this->get(route($route))->assertOk()->assertSee('Request a quote');
})->with(['home', 'features', 'pricing', 'about', 'docs', 'contact', 'quote', 'privacy']);

it('publishes a privacy policy that covers the mobile app', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee('data-test="privacy-policy"', false)
        ->assertSee('Data Protection Act')
        ->assertSee('does not track your location in the background');

    $this->get(route('home'))->assertSee(route('privacy'));
});

it('lists active plans on the pricing page', function () {
    Plan::factory()->create(['name' => 'Growth Plan', 'is_active' => true]);
    Plan::factory()->create(['name' => 'Retired Plan', 'is_active' => false]);

    $this->get(route('pricing'))
        ->assertOk()
        ->assertSee('Growth Plan')
        ->assertDontSee('Retired Plan');
});

it('keeps public registration closed', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'Walk In',
        'email' => 'walkin@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertStatus(404);

    $this->get(route('login'))->assertOk()->assertSee('Request access')->assertDontSee('Sign up');
});
