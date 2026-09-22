<?php

declare(strict_types=1);

use App\Enums\ApplicationStatus;
use App\Enums\OfferStatus;
use App\Models\Application;
use App\Models\Offer;
use App\Models\Vacancy;
use App\Services\Recruitment\RecruitmentService;
use App\Support\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();

    $this->tenant = tenantWithRoles(['slug' => 'acme', 'name' => 'Acme Ltd']);
    $this->vacancy = app(TenantContext::class)->run($this->tenant, fn () => Vacancy::factory()->forTenant($this->tenant)->open()->create([
        'title' => 'Senior Accountant', 'slug' => 'senior-accountant', 'description' => 'Own the ledger.',
    ]));
});

function applyThroughApi(object $test, array $overrides = []): string
{
    return $test->post('/api/v1/public/jobs/acme/senior-accountant/apply', array_merge([
        'first_name' => 'Jane',
        'last_name' => 'Wanjiku',
        'email' => 'Jane@Example.com',
        'phone' => '0712345678',
        'resume' => UploadedFile::fake()->create('cv.pdf', 120, 'application/pdf'),
    ], $overrides), ['Accept' => 'application/json'])
        ->assertCreated()
        ->json('data.tracking_code');
}

it('lists open jobs across organisations without an account', function () {
    $other = tenantWithRoles(['slug' => 'beta', 'name' => 'Beta Corp']);
    app(TenantContext::class)->run($other, fn () => Vacancy::factory()->forTenant($other)->open()->create(['title' => 'Driver', 'slug' => 'driver']));

    $this->getJson('/api/v1/public/jobs')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['title' => 'Senior Accountant'])
        ->assertJsonMissingPath('data.0.description')
        ->assertJsonCount(2, 'meta.organizations');

    $this->getJson('/api/v1/public/jobs?search=driver')->assertJsonCount(1, 'data')->assertJsonPath('data.0.organization.slug', 'beta');
});

it('shows a job with its description, and 404s a closed one', function () {
    $this->getJson('/api/v1/public/jobs/acme/senior-accountant')
        ->assertOk()
        ->assertJsonPath('data.description', 'Own the ledger.')
        ->assertJsonPath('data.organization.name', 'Acme Ltd');

    $this->vacancy->forceFill(['status' => 'closed'])->save();
    $this->getJson('/api/v1/public/jobs/acme/senior-accountant')->assertNotFound();
});

it('applies with a CV and returns a tracking code', function () {
    $code = applyThroughApi($this);

    $application = Application::query()->withoutGlobalScopes()->where('tracking_code', $code)->firstOrFail();
    expect($code)->toMatch('/^APP-[A-Z0-9]{8}$/')
        ->and($application->vacancy_id)->toBe($this->vacancy->id);
});

it('validates the application like the website does', function () {
    $this->postJson('/api/v1/public/jobs/acme/senior-accountant/apply', ['email' => 'nope'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['first_name', 'last_name', 'email', 'phone', 'resume']);

    $this->post('/api/v1/public/jobs/acme/senior-accountant/apply', [
        'first_name' => 'Jane', 'last_name' => 'W', 'email' => 'jane@example.com', 'phone' => '07',
        'resume' => UploadedFile::fake()->create('cv.exe', 10, 'application/octet-stream'),
    ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('resume');
});

it('rate-limits applications per connection', function () {
    foreach (range(1, 6) as $i) {
        applyThroughApi($this, ['email' => "jane{$i}@example.com"]);
    }

    $this->post('/api/v1/public/jobs/acme/senior-accountant/apply', [
        'first_name' => 'Jane', 'last_name' => 'W', 'email' => 'late@example.com', 'phone' => '0712',
        'resume' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf'),
    ], ['Accept' => 'application/json'])->assertStatus(429);
});

it('tracks an application only with the matching code and email', function () {
    $code = applyThroughApi($this);

    $this->postJson('/api/v1/public/applications/track', ['code' => strtolower($code), 'email' => 'jane@example.com'])
        ->assertOk()
        ->assertJsonPath('data.tracking_code', $code)
        ->assertJsonPath('data.job.title', 'Senior Accountant')
        ->assertJsonPath('data.state', 'received')
        ->assertJsonPath('data.steps.0.key', 'applied')
        ->assertJsonPath('data.steps.0.state', 'done')
        ->assertJsonPath('data.channel', 'application.'.$code)
        ->assertJsonCount(5, 'data.steps');

    $this->postJson('/api/v1/public/applications/track', ['code' => $code, 'email' => 'someone@else.com'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('code');
});

it('lets the candidate accept an offer', function () {
    $code = applyThroughApi($this);
    $application = Application::query()->withoutGlobalScopes()->where('tracking_code', $code)->firstOrFail();

    $offer = app(TenantContext::class)->run($this->tenant, function () use ($application): Offer {
        $offer = Offer::factory()->forApplication($application)->create(['salary_cents' => 12_000_000, 'job_title' => 'Senior Accountant']);

        return app(RecruitmentService::class)->sendOffer($offer);
    });

    $this->postJson('/api/v1/public/applications/track', ['code' => $code, 'email' => 'jane@example.com'])
        ->assertJsonPath('data.state', 'offer')
        ->assertJsonPath('data.pending_offer.salary_cents', 12_000_000);

    $this->postJson('/api/v1/public/applications/respond', ['code' => $code, 'email' => 'jane@example.com', 'accept' => true])
        ->assertOk()
        ->assertJsonPath('data.state', 'accepted')
        ->assertJsonPath('meta.message', fn (string $m) => str_contains($m, 'Offer accepted'));

    expect($offer->fresh()->status)->toBe(OfferStatus::Accepted)
        ->and($application->fresh()->status)->toBe(ApplicationStatus::Hired);

    $this->postJson('/api/v1/public/applications/respond', ['code' => $code, 'email' => 'jane@example.com', 'accept' => false])
        ->assertStatus(422)
        ->assertJsonValidationErrors('accept');
});
