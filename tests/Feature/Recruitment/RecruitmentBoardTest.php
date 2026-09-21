<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\Recruitment\Board;
use App\Models\Application;
use App\Models\Employee;
use App\Models\Interview;
use App\Models\Offer;
use App\Models\Vacancy;
use App\Support\TenantContext;
use Livewire\Livewire;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'board-co', 'name' => 'Board Co']);
    app(TenantContext::class)->set($this->tenant);
    $this->hr = userForTenant($this->tenant, Role::HrAdministrator);
    $this->vacancy = Vacancy::factory()->forTenant($this->tenant)->open()->create(['headcount' => 2]);
});

it('moves a candidate through the pipeline from the dashboard', function () {
    $application = Application::factory()->forVacancy($this->vacancy)->create();

    $this->actingAs($this->hr);

    Livewire::test(Board::class)
        ->set('vacancyUuid', $this->vacancy->uuid)
        ->call('moveStage', $application->uuid, 'screening')
        ->assertDispatched('toast-show');

    expect($application->fresh()->stage)->toBe('screening');
});

it('does not crash the board when moving an already-hired candidate', function () {
    // Regression: clicking a stage button on a hired (closed) application used
    // to let RecruitmentException::applicationClosed() bubble out of the
    // component uncaught, which broke the Livewire request entirely.
    $application = Application::factory()->forVacancy($this->vacancy)->create([
        'stage' => 'hired', 'status' => 'hired',
    ]);

    $this->actingAs($this->hr);

    Livewire::test(Board::class)
        ->set('vacancyUuid', $this->vacancy->uuid)
        ->call('moveStage', $application->uuid, 'applied')
        ->assertOk()
        ->assertDispatched('toast-show');

    // Nothing changed — the closed application was left alone.
    expect($application->fresh()->stage)->toBe('hired')
        ->and($application->fresh()->status->value)->toBe('hired');
});

it('does not crash the board when a candidate is rejected then moved again', function () {
    $application = Application::factory()->forVacancy($this->vacancy)->create([
        'status' => 'rejected',
    ]);

    $this->actingAs($this->hr);

    Livewire::test(Board::class)
        ->set('vacancyUuid', $this->vacancy->uuid)
        ->call('moveStage', $application->uuid, 'screening')
        ->assertOk()
        ->assertDispatched('toast-show');

    expect($application->fresh()->status->value)->toBe('rejected');
});

it('hides pipeline move controls for a candidate that is no longer active', function () {
    Application::factory()->forVacancy($this->vacancy)->create([
        'stage' => 'hired', 'status' => 'hired',
    ]);

    $this->actingAs($this->hr);

    Livewire::test(Board::class)
        ->set('vacancyUuid', $this->vacancy->uuid)
        ->assertDontSee('→ Applied')
        ->assertDontSee('→ Screening');
});

it('creates and publishes a vacancy from the dashboard', function () {
    $this->actingAs($this->hr);

    Livewire::test(Board::class)
        ->call('startCreateVacancy')
        ->set('vacancyForm.title', 'Backend Engineer')
        ->set('vacancyForm.headcount', '2')
        ->call('saveVacancy')
        ->assertDispatched('toast-show');

    $created = Vacancy::query()->where('title', 'Backend Engineer')->firstOrFail();
    expect($created->status->value)->toBe('draft')
        ->and($created->stages())->toBe(['applied', 'screening', 'interview', 'offer', 'hired']);

    Livewire::test(Board::class)
        ->call('publishVacancy', $created->uuid)
        ->assertDispatched('toast-show');

    expect($created->fresh()->status->value)->toBe('open');
});

it('edits and closes a vacancy from the dashboard', function () {
    $vacancy = Vacancy::factory()->forTenant($this->tenant)->open()->create(['title' => 'Old title']);

    $this->actingAs($this->hr);

    Livewire::test(Board::class)
        ->call('startEditVacancy', $vacancy->uuid)
        ->assertSet('vacancyForm.title', 'Old title')
        ->set('vacancyForm.title', 'New title')
        ->call('saveVacancy')
        ->assertDispatched('toast-show');

    expect($vacancy->fresh()->title)->toBe('New title');

    Livewire::test(Board::class)->call('closeVacancy', $vacancy->uuid);
    expect($vacancy->fresh()->status->value)->toBe('closed');
});

it('forbids creating a vacancy from the dashboard without recruitment.manage', function () {
    $employee = userForTenant($this->tenant, Role::Employee);
    $this->actingAs($employee);

    Livewire::test(Board::class)
        ->call('startCreateVacancy')
        ->assertForbidden();
});

it('adds a candidate to the selected vacancy from the dashboard', function () {
    $this->actingAs($this->hr);

    Livewire::test(Board::class)
        ->set('vacancyUuid', $this->vacancy->uuid)
        ->call('startAddCandidate')
        ->set('candidateForm.first_name', 'Asha')
        ->set('candidateForm.last_name', 'Otieno')
        ->set('candidateForm.email', 'asha@example.com')
        ->call('addCandidate')
        ->assertDispatched('toast-show');

    expect(Application::query()->whereHas('applicant', fn ($q) => $q->where('email', 'asha@example.com'))->exists())->toBeTrue();
});

it('rejects a candidate from the dashboard', function () {
    $application = Application::factory()->forVacancy($this->vacancy)->create();

    $this->actingAs($this->hr);

    Livewire::test(Board::class)
        ->set('vacancyUuid', $this->vacancy->uuid)
        ->call('rejectApplication', $application->uuid)
        ->assertDispatched('toast-show');

    expect($application->fresh()->status->value)->toBe('rejected');
});

it('schedules an interview and records feedback from the dashboard', function () {
    $application = Application::factory()->forVacancy($this->vacancy)->create();
    $interviewerUser = userForTenant($this->tenant, Role::Employee);
    $interviewer = Employee::factory()->forTenant($this->tenant)->create(['user_id' => $interviewerUser->id]);

    $this->actingAs($this->hr);

    Livewire::test(Board::class)
        ->set('vacancyUuid', $this->vacancy->uuid)
        ->call('startScheduleInterview', $application->uuid)
        ->set('interviewForm.scheduled_at', now()->addDays(2)->format('Y-m-d\TH:i'))
        ->set('interviewForm.interviewer_id', (string) $interviewer->id)
        ->call('scheduleInterview')
        ->assertDispatched('toast-show');

    $interview = Interview::query()->where('application_id', $application->id)->firstOrFail();
    expect($interview->interviewer_id)->toBe($interviewerUser->id);

    $this->actingAs($interviewerUser);

    Livewire::test(Board::class)
        ->set('vacancyUuid', $this->vacancy->uuid)
        ->call('startFeedback', $interview->uuid)
        ->set('feedbackForm.recommendation', 'strong_yes')
        ->set('feedbackForm.rating', '5')
        ->call('saveFeedback')
        ->assertDispatched('toast-show');

    expect($interview->fresh()->recommendation->value)->toBe('strong_yes');
});

it('drafts and sends an offer from the dashboard', function () {
    $application = Application::factory()->forVacancy($this->vacancy)->create();

    $this->actingAs($this->hr);

    Livewire::test(Board::class)
        ->set('vacancyUuid', $this->vacancy->uuid)
        ->call('startMakeOffer', $application->uuid)
        ->set('offerForm.salary_cents', '950000')
        ->call('makeOffer')
        ->assertDispatched('toast-show');

    $offer = Offer::query()->where('application_id', $application->id)->firstOrFail();
    expect($offer->status->value)->toBe('draft');

    Livewire::test(Board::class)
        ->set('vacancyUuid', $this->vacancy->uuid)
        ->call('sendOffer', $offer->uuid)
        ->assertDispatched('toast-show');

    expect($offer->fresh()->status->value)->toBe('sent');
});

it('blocks a user without recruitment.manage from moving a candidate', function () {
    $application = Application::factory()->forVacancy($this->vacancy)->create();
    $viewer = userForTenant($this->tenant, Role::Employee);

    $this->actingAs($viewer);

    Livewire::test(Board::class)
        ->set('vacancyUuid', $this->vacancy->uuid)
        ->call('moveStage', $application->uuid, 'screening')
        ->assertOk()
        ->assertDispatched('toast-show');

    expect($application->fresh()->stage)->toBe('applied');
});
