<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Application;
use App\Models\Employee;
use App\Models\Offer;
use App\Models\Vacancy;
use App\Support\TenantContext;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'offer-co', 'name' => 'Offer Co']);
    app(TenantContext::class)->set($this->tenant);
    $this->hr = userForTenant($this->tenant, Role::HrAdministrator);
    $this->vacancy = Vacancy::factory()->forTenant($this->tenant)->open()->create(['title' => 'Product Manager', 'headcount' => 1]);
    $this->application = Application::factory()->forVacancy($this->vacancy)->create();
});

it('drafts an offer and advances the application to the offer stage', function () {
    $this->actingAs($this->hr)
        ->postJson('http://offer-co.hrm.test/offers', [
            'application_uuid' => $this->application->uuid,
            'salary_cents' => 950000,
            'start_date' => now()->addMonth()->toDateString(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft');

    expect($this->application->fresh()->stage)->toBe('offer');
});

it('sends, accepts and converts an offer into an employee', function () {
    $offer = Offer::factory()->forApplication($this->application)->create(['salary_cents' => 1200000, 'job_title' => 'Product Manager']);

    $this->actingAs($this->hr)->postJson("http://offer-co.hrm.test/offers/{$offer->uuid}/send")
        ->assertOk()->assertJsonPath('data.status', 'sent');

    $this->actingAs($this->hr)->postJson("http://offer-co.hrm.test/offers/{$offer->uuid}/respond", ['accepted' => true])
        ->assertOk()->assertJsonPath('data.status', 'accepted');

    expect($this->application->fresh()->status->value)->toBe('hired');

    $response = $this->actingAs($this->hr)
        ->postJson("http://offer-co.hrm.test/offers/{$offer->uuid}/convert", [])
        ->assertCreated();

    $employee = Employee::query()->where('uuid', $response->json('data.employee.uuid'))->firstOrFail();
    expect($employee->job_title)->toBe('Product Manager')
        ->and($employee->salary_amount_cents)->toBe(1200000)
        ->and($offer->fresh()->converted_employee_id)->toBe($employee->id);
});

it('will not convert an offer twice', function () {
    $offer = Offer::factory()->forApplication($this->application)->create();
    $offer->forceFill(['status' => 'accepted'])->save();

    $this->actingAs($this->hr)->postJson("http://offer-co.hrm.test/offers/{$offer->uuid}/convert", [])->assertCreated();
    $this->actingAs($this->hr)->postJson("http://offer-co.hrm.test/offers/{$offer->uuid}/convert", [])->assertStatus(422);
});

it('forbids offer management without the recruitment permission', function () {
    $offer = Offer::factory()->forApplication($this->application)->create();
    $employee = userForTenant($this->tenant, Role::Employee);

    $this->actingAs($employee)
        ->postJson("http://offer-co.hrm.test/offers/{$offer->uuid}/send")
        ->assertForbidden();
});
