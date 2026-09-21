<?php

declare(strict_types=1);

use App\Enums\OfferStatus;
use App\Enums\Role;
use App\Events\ApplicationProgressUpdated;
use App\Events\UserNotified;
use App\Livewire\Marketing\ApplicationTracker;
use App\Livewire\Notifications\Bell;
use App\Livewire\Recruitment\Board;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\Offer;
use App\Models\Vacancy;
use App\Notifications\ApplicationReceivedNotification;
use App\Notifications\CandidateUpdateNotification;
use App\Services\Recruitment\RecruitmentService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = tenantWithRoles(['slug' => 'live-co', 'name' => 'Live Co']);
    app(TenantContext::class)->set($this->tenant);
    $this->hr = userForTenant($this->tenant, Role::HrAdministrator);

    $vacancy = Vacancy::factory()->forTenant($this->tenant)->open()->create(['title' => 'Designer']);
    $applicant = Applicant::factory()->forTenant($this->tenant)->create(['email' => 'amani@example.com']);
    $this->application = Application::factory()->forVacancy($vacancy)->forApplicant($applicant)->create(['stage' => 'applied']);
});

it('broadcasts each step HR takes to the candidate and the board, and emails the candidate', function () {
    Event::fake([ApplicationProgressUpdated::class]);
    Notification::fake();
    $this->actingAs($this->hr);

    Livewire::test(Board::class)->call('moveStage', $this->application->uuid, 'interview');

    Event::assertDispatched(ApplicationProgressUpdated::class, function (ApplicationProgressUpdated $event): bool {
        $channels = collect($event->broadcastOn())->map->name->all();

        return $event->stage === 'interview'
            && $channels === ['application.'.$this->application->tracking_code, 'private-tenant.'.$this->tenant->id.'.recruitment']
            && $event->broadcastWith() === ['stage' => 'interview', 'status' => 'active'];
    });

    Notification::assertSentOnDemand(CandidateUpdateNotification::class, fn (CandidateUpdateNotification $n) => $n->kind === CandidateUpdateNotification::STAGE && $n->stage === 'interview');
});

it('shows the stage HR chose on the tracking page', function () {
    app(RecruitmentService::class)->moveStage($this->application, 'interview', $this->hr);

    Livewire::test(ApplicationTracker::class)
        ->set('code', $this->application->tracking_code)->set('email', 'amani@example.com')
        ->call('lookup')
        ->assertSee('Shortlisted for interview')
        ->assertSee('Live — updates automatically')
        ->call('progressUpdated')
        ->assertSee('Updated');
});

it('broadcasts when the candidate accepts an offer so the board updates', function () {
    $offer = Offer::factory()->forApplication($this->application)->create(['status' => OfferStatus::Sent]);
    Event::fake([ApplicationProgressUpdated::class]);

    Livewire::test(ApplicationTracker::class)
        ->set('code', $this->application->tracking_code)->set('email', 'amani@example.com')
        ->call('lookup')
        ->call('respond', true);

    expect($offer->fresh()->status)->toBe(OfferStatus::Accepted);
    Event::assertDispatched(ApplicationProgressUpdated::class, fn (ApplicationProgressUpdated $e) => $e->status === 'hired');
});

it('pushes every new in-app notification to the user live', function () {
    Event::fake([UserNotified::class]);

    $this->hr->notify(new ApplicationReceivedNotification($this->application->load(['vacancy', 'applicant'])));

    Event::assertDispatched(UserNotified::class, fn (UserNotified $e) => $e->userId === $this->hr->id
        && $e->title === 'Application Received'
        && str_contains($e->detail, 'Designer'));
});

it('toasts and refreshes the bell when a notification arrives', function () {
    $this->actingAs($this->hr);

    Livewire::test(Bell::class)
        ->call('notified', ['title' => 'Application Received', 'detail' => 'Designer'])
        ->assertOk();
});

it('only lets recruitment staff of the organization join its private channel', function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);
    Broadcast::purge();
    require base_path('routes/channels.php');

    $auth = fn ($user, string $channel) => $this->actingAs($user)
        ->post('/broadcasting/auth', ['channel_name' => $channel, 'socket_id' => '1234.5678']);

    $auth($this->hr, 'private-tenant.'.$this->tenant->id.'.recruitment')->assertOk();
    $auth(userForTenant($this->tenant, Role::Employee), 'private-tenant.'.$this->tenant->id.'.recruitment')->assertForbidden();

    $rival = tenantWithRoles(['slug' => 'rival-live']);
    $auth(userForTenant($rival, Role::HrAdministrator), 'private-tenant.'.$this->tenant->id.'.recruitment')->assertForbidden();

    $auth($this->hr, 'private-App.Models.User.'.$this->hr->id)->assertOk();
});
