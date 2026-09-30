<?php

declare(strict_types=1);

use App\Enums\VacancyStatus;
use App\Models\Tenant;
use App\Models\Vacancy;

it('serves an xml sitemap with the static marketing pages as daily', function () {
    $response = $this->get(route('sitemap'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    $xml = $response->getContent();

    expect($xml)->toContain('<?xml version="1.0" encoding="UTF-8"?>')
        ->and($xml)->toContain('<loc>'.route('home').'</loc>')
        ->and($xml)->toContain('<loc>'.route('jobs.index').'</loc>');

    // A static page's <url> block uses changefreq daily.
    preg_match('#<url>\s*<loc>'.preg_quote(route('home'), '#').'</loc>.*?</url>#s', $xml, $matches);
    expect($matches[0] ?? '')->toContain('<changefreq>daily</changefreq>');
});

it('lists an open vacancy with changefreq always', function () {
    $tenant = Tenant::factory()->create(['slug' => 'sitemap-co']);
    $vacancy = Vacancy::factory()->forTenant($tenant)->open()->create(['slug' => 'backend-engineer']);

    $xml = $this->get(route('sitemap'))->getContent();

    $url = route('jobs.show', ['organization' => 'sitemap-co', 'vacancy' => 'backend-engineer']);
    expect($xml)->toContain('<loc>'.$url.'</loc>');

    preg_match('#<url>\s*<loc>'.preg_quote($url, '#').'</loc>.*?</url>#s', $xml, $matches);
    expect($matches[0] ?? '')->toContain('<changefreq>always</changefreq>');
});

it('excludes a draft vacancy from the sitemap', function () {
    $tenant = Tenant::factory()->create(['slug' => 'draft-co']);
    Vacancy::factory()->forTenant($tenant)->create(['slug' => 'hidden-role', 'status' => VacancyStatus::Draft]);

    $xml = $this->get(route('sitemap'))->getContent();

    expect($xml)->not->toContain('hidden-role');
});

it('excludes vacancies belonging to a suspended tenant', function () {
    $tenant = Tenant::factory()->suspended()->create(['slug' => 'suspended-co']);
    Vacancy::factory()->forTenant($tenant)->open()->create(['slug' => 'ghost-role']);

    $xml = $this->get(route('sitemap'))->getContent();

    expect($xml)->not->toContain('ghost-role');
});
