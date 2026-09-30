<?php

declare(strict_types=1);

it('gives every public marketing page a meta keywords tag and a canonical link', function (string $route) {
    $this->get(route($route))
        ->assertOk()
        ->assertSee('<meta name="keywords" content="', false)
        ->assertSee('<link rel="canonical"', false);
})->with(['home', 'features', 'pricing', 'about', 'docs', 'contact', 'quote', 'privacy']);

it('targets Kenya-specific HR software search terms on the homepage', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('HR software Kenya')
        ->assertSee('PAYE NSSF SHIF payroll software');
});

it('gives each marketing page its own relevant keywords rather than one generic set', function () {
    $pricing = $this->get(route('pricing'))->getContent();
    $features = $this->get(route('features'))->getContent();

    expect($pricing)->toContain('HR software pricing Kenya')
        ->and($features)->toContain('HR software features Kenya')
        ->and($pricing)->not->toContain('HR software features Kenya');
});

it('publishes Organization structured data on every marketing page, and SoftwareApplication only on the homepage', function () {
    $home = $this->get(route('home'))->getContent();
    $features = $this->get(route('features'))->getContent();

    expect($home)->toContain('"@type":"Organization"')
        ->and($home)->toContain('"@type":"SoftwareApplication"')
        ->and($features)->toContain('"@type":"Organization"')
        ->and($features)->not->toContain('"@type":"SoftwareApplication"');
});

it('serves a robots.txt that points crawlers at the sitemap', function () {
    $response = $this->get(route('robots'));

    $response->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

    expect($response->getContent())
        ->toContain('User-agent: *')
        ->toContain('Sitemap: '.route('sitemap'));
});
