<?php

declare(strict_types=1);

use App\Models\Plan;

it('renders every public marketing page', function (string $route) {
    $this->get(route($route))->assertOk()->assertSee('Request a quote');
})->with(['home', 'features', 'pricing', 'about', 'docs', 'contact', 'quote']);

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
