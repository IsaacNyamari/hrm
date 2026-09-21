<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Livewire\Performance\Dashboard;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\Kpi;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Services\Performance\PerformanceService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    Notification::fake();

    $this->tenant = tenantWithRoles(['slug' => 'perf-dash-co', 'name' => 'Perf Dash Co']);
    app(TenantContext::class)->set($this->tenant);

    $this->hr = userForTenant($this->tenant, Role::HrAdministrator); // performance.manage

    $this->employeeUser = userForTenant($this->tenant, Role::Employee);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create(['user_id' => $this->employeeUser->id]);
});

it('creates a draft cycle from the dashboard', function () {
    $this->actingAs($this->hr);

    Livewire::test(Dashboard::class)
        ->call('startCreateCycle')
        ->set('cycleForm.name', '2027 Annual')
        ->set('cycleForm.type', 'annual')
        ->set('cycleForm.starts_on', '2027-01-01')
        ->set('cycleForm.ends_on', '2027-12-31')
        ->call('saveCycle')
        ->assertDispatched('toast-show')
        ->assertSet('showCycleForm', false);

    expect(PerformanceCycle::query()->where('name', '2027 Annual')->exists())->toBeTrue();
});

it('rejects cycle weights that do not add up to 100 from the dashboard', function () {
    $this->actingAs($this->hr);

    Livewire::test(Dashboard::class)
        ->call('startCreateCycle')
        ->set('cycleForm.name', 'Bad weights')
        ->set('cycleForm.starts_on', '2027-01-01')
        ->set('cycleForm.ends_on', '2027-06-30')
        ->set('cycleForm.goal_weight', '70')
        ->set('cycleForm.competency_weight', '40')
        ->call('saveCycle')
        ->assertDispatched('toast-show');

    expect(PerformanceCycle::query()->where('name', 'Bad weights')->exists())->toBeFalse();
});

it('opens a draft cycle and starts its review phase from the dashboard', function () {
    $employee = Employee::factory()->forTenant($this->tenant)->create([
        'user_id' => userForTenant($this->tenant, Role::Employee)->id,
    ]);
    $cycle = PerformanceCycle::factory()->forTenant($this->tenant)->create();

    $this->actingAs($this->hr);

    Livewire::test(Dashboard::class)
        ->call('openCycle', $cycle->uuid)
        ->assertDispatched('toast-show');

    expect($cycle->fresh()->status->value)->toBe('active');

    Livewire::test(Dashboard::class)
        ->call('startReviewPhaseForCycle', $cycle->uuid)
        ->assertDispatched('toast-show');

    expect($cycle->fresh()->status->value)->toBe('in_review');
    expect($employee->fresh())->not->toBeNull();
});

it('deletes a draft cycle from the dashboard but not a non-draft one', function () {
    $draft = PerformanceCycle::factory()->forTenant($this->tenant)->create();
    $active = PerformanceCycle::factory()->forTenant($this->tenant)->create(['status' => 'active']);

    $this->actingAs($this->hr);

    Livewire::test(Dashboard::class)->call('removeCycle', $draft->uuid);
    expect(PerformanceCycle::query()->find($draft->id))->toBeNull();

    Livewire::test(Dashboard::class)
        ->call('removeCycle', $active->uuid)
        ->assertDispatched('toast-show');
    expect(PerformanceCycle::query()->find($active->id))->not->toBeNull();
});

it('forbids managing cycles from the dashboard without performance.manage', function () {
    $this->actingAs($this->employeeUser);

    Livewire::test(Dashboard::class)
        ->call('startCreateCycle')
        ->assertForbidden();
});

it('lets an employee delete their own goal from the dashboard', function () {
    $goal = Goal::factory()->forEmployee($this->employee)->create();

    $this->actingAs($this->employeeUser);

    Livewire::test(Dashboard::class)->call('deleteGoal', $goal->uuid);

    expect(Goal::query()->find($goal->id))->toBeNull();
});

it('creates, records and deletes a KPI from the dashboard', function () {
    $this->actingAs($this->employeeUser);

    Livewire::test(Dashboard::class)
        ->call('startCreateKpi')
        ->set('kpiForm.name', 'Calls handled')
        ->set('kpiForm.unit', 'calls')
        ->set('kpiForm.target_value', '100')
        ->call('saveKpi')
        ->assertDispatched('toast-show');

    $kpi = Kpi::query()->where('employee_id', $this->employee->id)->firstOrFail();

    Livewire::test(Dashboard::class)
        ->call('startRecordKpi', $kpi->uuid)
        ->set('kpiValue', '42')
        ->call('recordKpi')
        ->assertDispatched('toast-show');

    expect((float) $kpi->fresh()->current_value)->toBe(42.0);

    Livewire::test(Dashboard::class)->call('deleteKpi', $kpi->uuid);
    expect(Kpi::query()->find($kpi->id))->toBeNull();
});

it('lets the assigned reviewer submit a review from the dashboard', function () {
    $managerUser = userForTenant($this->tenant, Role::Employee);
    $manager = Employee::factory()->forTenant($this->tenant)->create(['user_id' => $managerUser->id]);
    $this->employee->update(['manager_id' => $manager->id]);

    $cycle = PerformanceCycle::factory()->forTenant($this->tenant)->inReview()->create();
    app(PerformanceService::class)->openCycle($cycle);

    $review = PerformanceReview::query()
        ->where('performance_cycle_id', $cycle->id)
        ->where('employee_id', $this->employee->id)
        ->where('reviewer_type', 'manager')
        ->firstOrFail();

    $this->actingAs($managerUser);

    Livewire::test(Dashboard::class)
        ->call('startReview', $review->uuid)
        ->set('reviewRatings.job_knowledge', '4')
        ->set('reviewRatings.teamwork', '5')
        ->set('reviewComments', 'Solid year.')
        ->call('submitReview')
        ->assertDispatched('toast-show');

    expect($review->fresh()->status->value)->toBe('submitted');
});

it('lets the reviewed employee acknowledge their submitted review from the dashboard', function () {
    $cycle = PerformanceCycle::factory()->forTenant($this->tenant)->inReview()->create();
    app(PerformanceService::class)->openCycle($cycle);

    $review = PerformanceReview::query()
        ->where('performance_cycle_id', $cycle->id)
        ->where('employee_id', $this->employee->id)
        ->firstOrFail();

    app(PerformanceService::class)->submitReview($review, ['ratings' => [['competency' => 'job_knowledge', 'rating' => 4]]]);

    $this->actingAs($this->employeeUser);

    Livewire::test(Dashboard::class)
        ->call('acknowledgeReview', $review->uuid)
        ->assertDispatched('toast-show');

    expect($review->fresh()->status->value)->toBe('acknowledged');
});
