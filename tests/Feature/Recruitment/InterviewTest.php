<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Application;
use App\Models\Employee;
use App\Models\Interview;
use App\Models\Vacancy;
use App\Notifications\InterviewScheduledNotification;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    Notification::fake();

    $this->tenant = tenantWithRoles(['slug' => 'iv-co', 'name' => 'IV Co']);
    app(TenantContext::class)->set($this->tenant);
    $this->hr = userForTenant($this->tenant, Role::HrAdministrator);

    $this->panelUser = userForTenant($this->tenant, Role::Supervisor); // has recruitment.interview only
    Employee::factory()->forTenant($this->tenant)->create(['user_id' => $this->panelUser->id]);

    $this->vacancy = Vacancy::factory()->forTenant($this->tenant)->open()->create();
    $this->application = Application::factory()->forVacancy($this->vacancy)->create();
});

it('schedules an interview and notifies the interviewer', function () {
    $this->actingAs($this->hr)
        ->postJson('http://iv-co.hrm.test/interviews', [
            'application_uuid' => $this->application->uuid,
            'interviewer_id' => $this->panelUser->id,
            'scheduled_at' => now()->addDays(2)->toIso8601String(),
            'mode' => 'video',
        ])
        ->assertCreated()
        ->assertJsonPath('data.round', 1);

    Notification::assertSentTo($this->panelUser, InterviewScheduledNotification::class);
});

it('lets the assigned interviewer submit feedback', function () {
    $interview = Interview::factory()->forApplication($this->application)->create(['interviewer_id' => $this->panelUser->id]);

    $this->actingAs($this->panelUser)
        ->postJson("http://iv-co.hrm.test/interviews/{$interview->uuid}/feedback", [
            'recommendation' => 'strong_yes', 'rating' => 5, 'feedback' => 'Excellent problem solver.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.recommendation', 'strong_yes');
});

it('stops an unrelated user from submitting interview feedback', function () {
    $interview = Interview::factory()->forApplication($this->application)->create(['interviewer_id' => $this->panelUser->id]);
    $stranger = userForTenant($this->tenant, Role::Employee);

    $this->actingAs($stranger)
        ->postJson("http://iv-co.hrm.test/interviews/{$interview->uuid}/feedback", ['recommendation' => 'no'])
        ->assertForbidden();
});
