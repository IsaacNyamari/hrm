<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Notifications\PerformanceReviewSubmittedNotification;
use App\Services\Performance\PerformanceService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    Notification::fake();

    $this->tenant = tenantWithRoles(['slug' => 'rev-co', 'name' => 'Rev Co']);
    app(TenantContext::class)->set($this->tenant);

    $this->hr = userForTenant($this->tenant, Role::HrAdministrator);

    $this->managerUser = userForTenant($this->tenant, Role::Employee);
    $this->manager = Employee::factory()->forTenant($this->tenant)->create(['user_id' => $this->managerUser->id]);

    $this->employeeUser = userForTenant($this->tenant, Role::Employee);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create([
        'user_id' => $this->employeeUser->id, 'manager_id' => $this->manager->id,
    ]);

    $this->cycle = PerformanceCycle::factory()->forTenant($this->tenant)->inReview()->create();
    app(PerformanceService::class)->openCycle($this->cycle);

    $this->managerReview = PerformanceReview::query()
        ->where('performance_cycle_id', $this->cycle->id)
        ->where('employee_id', $this->employee->id)
        ->where('reviewer_type', 'manager')
        ->firstOrFail();
});

it('assigns the manager review to the line manager\'s user', function () {
    expect($this->managerReview->reviewer_id)->toBe($this->managerUser->id);
});

it('lets the reviewer submit ratings and computes a weighted overall score', function () {
    $this->actingAs($this->managerUser)
        ->postJson("http://rev-co.hrm.test/performance-reviews/{$this->managerReview->uuid}/submit", [
            'comments' => 'Solid year.',
            'ratings' => [
                ['competency' => 'job_knowledge', 'rating' => 4, 'weight' => 2],
                ['competency' => 'teamwork', 'rating' => 5, 'weight' => 1],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted');

    expect($this->managerReview->fresh()->overall_rating)->toBe(4.33);

    Notification::assertSentTo($this->employeeUser, PerformanceReviewSubmittedNotification::class);
});

it('rejects a submit from someone who is not the reviewer', function () {
    $this->actingAs($this->employeeUser)
        ->postJson("http://rev-co.hrm.test/performance-reviews/{$this->managerReview->uuid}/submit", [
            'ratings' => [['competency' => 'job_knowledge', 'rating' => 5]],
        ])
        ->assertForbidden();
});

it('will not let a review be submitted twice', function () {
    $payload = ['ratings' => [['competency' => 'job_knowledge', 'rating' => 3]]];

    $this->actingAs($this->managerUser)
        ->postJson("http://rev-co.hrm.test/performance-reviews/{$this->managerReview->uuid}/submit", $payload)
        ->assertOk();

    $this->actingAs($this->managerUser)
        ->postJson("http://rev-co.hrm.test/performance-reviews/{$this->managerReview->uuid}/submit", $payload)
        ->assertStatus(422);
});

it('lets the employee acknowledge their own review but no one else', function () {
    app(PerformanceService::class)->submitReview($this->managerReview, [
        'ratings' => [['competency' => 'job_knowledge', 'rating' => 4]],
    ]);

    $this->actingAs($this->managerUser)
        ->postJson("http://rev-co.hrm.test/performance-reviews/{$this->managerReview->uuid}/acknowledge", [])
        ->assertForbidden();

    $this->actingAs($this->employeeUser)
        ->postJson("http://rev-co.hrm.test/performance-reviews/{$this->managerReview->uuid}/acknowledge", ['note' => 'Thanks'])
        ->assertOk()
        ->assertJsonPath('data.status', 'acknowledged');
});

it('snapshots a final weighted score when the cycle closes', function () {
    Goal::factory()->forEmployee($this->employee)->create([
        'performance_cycle_id' => $this->cycle->id, 'weight' => 1, 'manager_rating' => 4.0,
    ]);

    app(PerformanceService::class)->submitReview($this->managerReview, [
        'ratings' => [['competency' => 'job_knowledge', 'rating' => 3]],
    ]);

    app(PerformanceService::class)->closeCycle($this->cycle->fresh());

    // objectives 4.0 * 60 + competency 3.0 * 40 => 360 / 100 = 3.6
    expect($this->managerReview->fresh()->final_score)->toBe(3.6)
        ->and($this->cycle->fresh()->status->value)->toBe('closed');
});
