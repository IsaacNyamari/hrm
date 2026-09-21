<?php

declare(strict_types=1);

use App\Enums\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('renders a branded page for each error code', function (string $view, string $title) {
    $html = view("errors.{$view}", ['exception' => new HttpException((int) str_replace('xx', '18', $view))])->render();

    expect($html)->toContain($title)->toContain('Go to homepage')->not->toContain('@vite');
})->with([
    ['401', 'Please log in'],
    ['402', 'Payment required'],
    ['403', 'Access denied'],
    ['404', 'Page not found'],
    ['419', 'Session expired'],
    ['429', 'Too many requests'],
    ['500', 'Something went wrong'],
    ['4xx', 'Request problem'],
    ['5xx', 'Server error'],
]);

it('renders the maintenance page', function () {
    $html = view('errors.503', ['exception' => new HttpException(503)])->render();

    expect($html)->toContain('Back shortly')->toContain('Refresh');
});

it('shows the branded 404 for an unknown url', function () {
    $this->get('/definitely-not-a-page')->assertNotFound()->assertSee('Page not found');
});

it('shows the branded 403 when a tenant user opens the platform area', function () {
    $this->actingAs(userForTenant(tenantWithRoles(), Role::Employee))
        ->get(route('platform.dashboard'))
        ->assertForbidden()
        ->assertSee('Access denied');
});

it('shows the status code on the generic fallback page', function () {
    $this->post('/pricing')->assertStatus(405)->assertSee('405')->assertSee('Request problem');
});
