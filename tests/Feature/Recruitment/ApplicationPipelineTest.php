<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Vacancy;
use App\Support\TenantContext;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'pipe-co', 'name' => 'Pipe Co']);
    app(TenantContext::class)->set($this->tenant);
    $this->hr = userForTenant($this->tenant, Role::HrAdministrator);
    $this->vacancy = Vacancy::factory()->forTenant($this->tenant)->open()->create(['headcount' => 1]);
});

it('accepts a public application and files it at the first stage', function () {
    $this->postJson("http://pipe-co.hrm.test/careers/{$this->vacancy->slug}/applications", [
        'first_name' => 'Asha', 'last_name' => 'Otieno', 'email' => 'asha@example.com',
        'cover_letter' => 'I would love to join.',
    ])->assertCreated();

    $application = Application::query()->firstOrFail();
    expect($application->stage)->toBe('applied')
        ->and($application->status->value)->toBe('active')
        ->and(Applicant::query()->where('email', 'asha@example.com')->exists())->toBeTrue()
        ->and($application->activities()->count())->toBe(1);
});

it('rejects a duplicate application from the same candidate', function () {
    $payload = ['first_name' => 'Sam', 'last_name' => 'Kariuki', 'email' => 'sam@example.com'];

    $this->postJson("http://pipe-co.hrm.test/careers/{$this->vacancy->slug}/applications", $payload)->assertCreated();
    $this->postJson("http://pipe-co.hrm.test/careers/{$this->vacancy->slug}/applications", $payload)->assertStatus(422);
});

it('rejects an application to a vacancy that is not open', function () {
    $draft = Vacancy::factory()->forTenant($this->tenant)->create();

    $this->postJson("http://pipe-co.hrm.test/careers/{$draft->slug}/applications", [
        'first_name' => 'No', 'last_name' => 'Go', 'email' => 'no@example.com',
    ])->assertNotFound();
});

it('lets a recruiter move a candidate through the pipeline', function () {
    $application = Application::factory()->forVacancy($this->vacancy)->create();

    $this->actingAs($this->hr)
        ->postJson("http://pipe-co.hrm.test/applications/{$application->uuid}/stage", ['stage' => 'screening'])
        ->assertOk()
        ->assertJsonPath('data.stage', 'screening');

    expect($application->fresh()->activities()->where('type', 'stage_change')->count())->toBe(1);
});

it('rejects an unknown pipeline stage', function () {
    $application = Application::factory()->forVacancy($this->vacancy)->create();

    $this->actingAs($this->hr)
        ->postJson("http://pipe-co.hrm.test/applications/{$application->uuid}/stage", ['stage' => 'nonsense'])
        ->assertStatus(422);
});

it('marks the candidate hired and fills the vacancy at the final stage', function () {
    $application = Application::factory()->forVacancy($this->vacancy)->create();

    $this->actingAs($this->hr)
        ->postJson("http://pipe-co.hrm.test/applications/{$application->uuid}/stage", ['stage' => 'hired'])
        ->assertOk()
        ->assertJsonPath('data.status', 'hired');

    expect($this->vacancy->fresh()->positions_filled)->toBe(1)
        ->and($this->vacancy->fresh()->status->value)->toBe('filled');
});

it('records a rejection with a reason', function () {
    $application = Application::factory()->forVacancy($this->vacancy)->create();

    $this->actingAs($this->hr)
        ->postJson("http://pipe-co.hrm.test/applications/{$application->uuid}/reject", ['reason' => 'Skills mismatch'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    expect($application->fresh()->rejected_reason)->toBe('Skills mismatch');
});

it('keeps applications scoped to their tenant', function () {
    $other = tenantWithRoles(['slug' => 'rival-pipe-co']);
    $application = Application::factory()->forVacancy(Vacancy::factory()->forTenant($other)->open()->create())->create();

    $this->actingAs($this->hr)
        ->getJson("http://pipe-co.hrm.test/applications/{$application->uuid}")
        ->assertNotFound();
});
