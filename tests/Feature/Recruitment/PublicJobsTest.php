<?php

declare(strict_types=1);

use App\Enums\ApplicationStatus;
use App\Enums\OfferStatus;
use App\Enums\Role;
use App\Livewire\Marketing\ApplicationTracker;
use App\Livewire\Marketing\JobApplicationForm;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Offer;
use App\Models\Tenant;
use App\Models\Vacancy;
use App\Notifications\CandidateUpdateNotification;
use App\Services\Recruitment\RecruitmentService;
use App\Support\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();

    $this->tenant = tenantWithRoles(['slug' => 'acme', 'name' => 'Acme Ltd']);
    $this->vacancy = app(TenantContext::class)->run($this->tenant, fn () => Vacancy::factory()->forTenant($this->tenant)->open()->create([
        'title' => 'Senior Accountant', 'slug' => 'senior-accountant',
    ]));
});

function applyFor(object $test, string $email = 'jane@example.com'): Application
{
    Livewire::test(JobApplicationForm::class, ['organization' => 'acme', 'vacancy' => 'senior-accountant'])
        ->set('first_name', 'Jane')
        ->set('last_name', 'Wanjiku')
        ->set('email', $email)
        ->set('phone', '0712345678')
        ->set('resume', UploadedFile::fake()->create('cv.pdf', 120, 'application/pdf'))
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSee('Application sent!');

    return Application::query()->withoutGlobalScopes()->latest('id')->firstOrFail();
}

it('lists open jobs from every organization on the public jobs page', function () {
    $other = tenantWithRoles(['slug' => 'beta', 'name' => 'Beta Corp']);
    app(TenantContext::class)->run($other, function () use ($other): void {
        Vacancy::factory()->forTenant($other)->open()->create(['title' => 'Warehouse Lead']);
        Vacancy::factory()->forTenant($other)->create(['title' => 'Secret Draft Role']);
    });

    $this->get(route('jobs.index'))
        ->assertOk()
        ->assertSee('Senior Accountant')
        ->assertSee('Warehouse Lead')
        ->assertDontSee('Secret Draft Role');
});

it('hides jobs of suspended organizations', function () {
    $this->tenant->forceFill(['status' => 'suspended'])->save();

    $this->get(route('jobs.index'))->assertOk()->assertDontSee('Senior Accountant');
    $this->get(route('jobs.show', ['acme', 'senior-accountant']))->assertNotFound();
});

it('shows a job with its application form', function () {
    $this->get(route('jobs.show', ['acme', 'senior-accountant']))
        ->assertOk()
        ->assertSee('Senior Accountant')
        ->assertSee('Acme Ltd')
        ->assertSee('Submit application');
});

it('accepts an application without an account and emails a tracking code', function () {
    $application = applyFor($this);

    expect($application->tracking_code)->toMatch('/^APP-[2-9A-HJKMNP-Z]{8}$/')
        ->and($application->tenant_id)->toBe($this->tenant->id);

    Notification::assertSentOnDemand(CandidateUpdateNotification::class, function (CandidateUpdateNotification $n, array $channels, object $notifiable) use ($application): bool {
        return $n->kind === CandidateUpdateNotification::RECEIVED
            && $n->application->is($application)
            && $notifiable->routes['mail'] === 'jane@example.com';
    });
});

it('refuses a second application for the same job', function () {
    applyFor($this);

    Livewire::test(JobApplicationForm::class, ['organization' => 'acme', 'vacancy' => 'senior-accountant'])
        ->set('first_name', 'Jane')->set('last_name', 'Wanjiku')->set('email', 'jane@example.com')->set('phone', '0712345678')
        ->set('resume', UploadedFile::fake()->create('cv.pdf', 120, 'application/pdf'))
        ->call('submit')
        ->assertHasErrors('email');
});

it('requires both the code and the matching email to track', function () {
    $application = applyFor($this);

    Livewire::test(ApplicationTracker::class)
        ->set('code', $application->tracking_code)
        ->set('email', 'someone-else@example.com')
        ->call('lookup')
        ->assertHasErrors('code')
        ->assertDontSee('Senior Accountant');

    Livewire::test(ApplicationTracker::class)
        ->set('code', strtolower($application->tracking_code))
        ->set('email', 'JANE@example.com')
        ->call('lookup')
        ->assertHasNoErrors()
        ->assertSee('Senior Accountant')
        ->assertSee('Application received');
});

it('tells the candidate when they are unsuccessful', function () {
    $application = applyFor($this);
    app(TenantContext::class)->run($this->tenant, fn () => app(RecruitmentService::class)->reject($application, 'Not a fit'));

    Notification::assertSentOnDemand(CandidateUpdateNotification::class, fn (CandidateUpdateNotification $n) => $n->kind === CandidateUpdateNotification::UNSUCCESSFUL);

    Livewire::test(ApplicationTracker::class)
        ->set('code', $application->tracking_code)->set('email', 'jane@example.com')
        ->call('lookup')
        ->assertSee('Not successful this time')
        ->assertDontSee('Not a fit'); // internal reasons stay internal
});

it('shows interview details and emails the invitation', function () {
    $application = applyFor($this);
    app(TenantContext::class)->run($this->tenant, fn () => app(RecruitmentService::class)->scheduleInterview($application, [
        'scheduled_at' => now()->addDays(3)->setTime(10, 0), 'mode' => 'video', 'meeting_url' => 'https://meet.example.com/abc',
    ]));

    Notification::assertSentOnDemand(CandidateUpdateNotification::class, fn (CandidateUpdateNotification $n) => $n->kind === CandidateUpdateNotification::INTERVIEW);

    Livewire::test(ApplicationTracker::class)
        ->set('code', $application->tracking_code)->set('email', 'jane@example.com')
        ->call('lookup')
        ->assertSee('Interview scheduled')
        ->assertSee('https://meet.example.com/abc');
});

it('lets the candidate accept an offer from the tracking page', function () {
    $application = applyFor($this);
    $offer = app(TenantContext::class)->run($this->tenant, function () use ($application): Offer {
        $offer = Offer::factory()->forApplication($application)->create(['salary_cents' => 12_000_000, 'job_title' => 'Senior Accountant']);

        return app(RecruitmentService::class)->sendOffer($offer);
    });

    Livewire::test(ApplicationTracker::class)
        ->set('code', $application->tracking_code)->set('email', 'jane@example.com')
        ->call('lookup')
        ->assertSee('You have a job offer')
        ->assertSee('120,000.00')
        ->call('respond', true)
        ->assertSee('Offer accepted');

    expect($offer->fresh()->status)->toBe(OfferStatus::Accepted)
        ->and($application->fresh()->status)->toBe(ApplicationStatus::Hired);
});

it('does not leak another organization\'s applications to a signed-in user', function () {
    $application = applyFor($this);
    $rival = tenantWithRoles(['slug' => 'rival']);
    $this->actingAs(userForTenant($rival, Role::HrManager));
    app(TenantContext::class)->set($rival);

    // They can still track their own application by code + email (the public page ignores the viewer's tenant)...
    Livewire::test(ApplicationTracker::class)
        ->set('code', $application->tracking_code)->set('email', 'jane@example.com')
        ->call('lookup')
        ->assertSee('Senior Accountant');

    // ...but a guessed code without the email finds nothing.
    expect(Application::findForTracking($application->tracking_code, 'x@example.com'))->toBeNull()
        ->and(Applicant::query()->count())->toBe(0)
        ->and(Tenant::query()->whereKey($this->tenant->id)->exists())->toBeTrue();
});
