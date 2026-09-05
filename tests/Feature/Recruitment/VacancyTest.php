<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Vacancy;
use App\Support\TenantContext;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'ats-co', 'name' => 'ATS Co']);
    app(TenantContext::class)->set($this->tenant);
    $this->hr = userForTenant($this->tenant, Role::HrAdministrator);
});

it('creates a draft vacancy with the default pipeline', function () {
    $this->actingAs($this->hr)
        ->postJson('http://ats-co.hrm.test/vacancies', ['title' => 'Backend Engineer', 'headcount' => 2])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.pipeline_stages', ['applied', 'screening', 'interview', 'offer', 'hired']);
});

it('publishes a vacancy so it appears on the public careers board', function () {
    $vacancy = Vacancy::factory()->forTenant($this->tenant)->create(['title' => 'Data Analyst']);

    $this->actingAs($this->hr)
        ->postJson("http://ats-co.hrm.test/vacancies/{$vacancy->uuid}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'open');

    $this->getJson('http://ats-co.hrm.test/careers')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', $vacancy->slug);
});

it('hides draft vacancies from the careers board', function () {
    Vacancy::factory()->forTenant($this->tenant)->create();

    $this->getJson('http://ats-co.hrm.test/careers')->assertOk()->assertJsonCount(0, 'data');
});

it('forbids creating a vacancy without the recruitment permission', function () {
    $employee = userForTenant($this->tenant, Role::Employee);

    $this->actingAs($employee)
        ->postJson('http://ats-co.hrm.test/vacancies', ['title' => 'X'])
        ->assertForbidden();
});

it('does not expose another tenant\'s vacancy', function () {
    $other = tenantWithRoles(['slug' => 'rival-ats-co']);
    $vacancy = Vacancy::factory()->forTenant($other)->open()->create();

    $this->actingAs($this->hr)
        ->getJson("http://ats-co.hrm.test/vacancies/{$vacancy->uuid}")
        ->assertNotFound();
});
