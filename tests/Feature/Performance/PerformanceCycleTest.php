<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceReview;
use App\Support\TenantContext;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'perf-co', 'name' => 'Perf Co']);
    app(TenantContext::class)->set($this->tenant);
    $this->hr = userForTenant($this->tenant, Role::HrAdministrator);
});

it('lets an HR admin create a draft cycle', function () {
    $this->actingAs($this->hr)
        ->postJson('http://perf-co.hrm.test/performance-cycles', [
            'name' => '2026 Annual', 'type' => 'annual',
            'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft');
});

it('rejects weightings that do not add up to 100', function () {
    $this->actingAs($this->hr)
        ->postJson('http://perf-co.hrm.test/performance-cycles', [
            'name' => 'Bad weights', 'type' => 'annual',
            'starts_on' => '2026-01-01', 'ends_on' => '2026-06-30',
            'goal_weight' => 70, 'competency_weight' => 40,
        ])
        ->assertStatus(422);
});

it('forbids creating a cycle without the manage permission', function () {
    $employee = userForTenant($this->tenant, Role::Employee);

    $this->actingAs($employee)
        ->postJson('http://perf-co.hrm.test/performance-cycles', [
            'name' => 'X', 'type' => 'annual', 'starts_on' => '2026-01-01', 'ends_on' => '2026-02-01',
        ])
        ->assertForbidden();
});

it('opens a cycle and fans out reviews for active employees, idempotently', function () {
    $withUser = Employee::factory()->forTenant($this->tenant)->create([
        'user_id' => userForTenant($this->tenant, Role::Employee)->id,
    ]);
    Employee::factory()->forTenant($this->tenant)->create(); // no linked user

    $cycle = PerformanceCycle::factory()->forTenant($this->tenant)->create();

    $first = $this->actingAs($this->hr)
        ->postJson("http://perf-co.hrm.test/performance-cycles/{$cycle->uuid}/open")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    // 2 employees -> 2 manager reviews; 1 has a user -> +1 self review = 3
    expect($first->json('meta.reviews_created'))->toBe(3)
        ->and(PerformanceReview::query()->where('performance_cycle_id', $cycle->id)->count())->toBe(3);

    $second = $this->actingAs($this->hr)
        ->postJson("http://perf-co.hrm.test/performance-cycles/{$cycle->uuid}/open")
        ->assertOk();

    expect($second->json('meta.reviews_created'))->toBe(0)
        ->and(PerformanceReview::query()->where('performance_cycle_id', $cycle->id)->count())->toBe(3);

    expect($withUser->fresh())->not->toBeNull();
});

it('does not expose another tenant\'s cycle', function () {
    $other = tenantWithRoles(['slug' => 'rival-perf-co']);
    $cycle = PerformanceCycle::factory()->forTenant($other)->create();

    $this->actingAs($this->hr)
        ->getJson("http://perf-co.hrm.test/performance-cycles/{$cycle->uuid}")
        ->assertNotFound();
});
