<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Employee;
use App\Models\Goal;
use App\Support\TenantContext;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'goal-co', 'name' => 'Goal Co']);
    app(TenantContext::class)->set($this->tenant);

    $this->hr = userForTenant($this->tenant, Role::HrAdministrator);

    $this->managerUser = userForTenant($this->tenant, Role::Employee);
    $this->manager = Employee::factory()->forTenant($this->tenant)->create(['user_id' => $this->managerUser->id]);

    $this->employeeUser = userForTenant($this->tenant, Role::Employee);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create([
        'user_id' => $this->employeeUser->id, 'manager_id' => $this->manager->id,
    ]);
});

it('lets an employee create their own goal', function () {
    $this->actingAs($this->employeeUser)
        ->postJson('http://goal-co.hrm.test/goals', [
            'title' => 'Ship the billing module', 'category' => 'business', 'weight' => 30,
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Ship the billing module');

    expect(Goal::query()->where('employee_id', $this->employee->id)->count())->toBe(1);
});

it('stops an employee assigning a goal to someone else', function () {
    $this->actingAs($this->employeeUser)
        ->postJson('http://goal-co.hrm.test/goals', [
            'employee_id' => $this->manager->id, 'title' => 'Not mine', 'category' => 'business',
        ])
        ->assertForbidden();
});

it('lets the line manager attach a rating but blocks an unrelated colleague', function () {
    $goal = Goal::factory()->forEmployee($this->employee)->create();

    $this->actingAs($this->managerUser)
        ->putJson("http://goal-co.hrm.test/goals/{$goal->uuid}", ['manager_rating' => 4, 'manager_comment' => 'Strong'])
        ->assertOk();

    expect($goal->fresh()->manager_rating)->toBe(4.0)
        ->and($goal->fresh()->manager_comment)->toBe('Strong');

    $stranger = userForTenant($this->tenant, Role::Employee);
    Employee::factory()->forTenant($this->tenant)->create(['user_id' => $stranger->id]);

    $this->actingAs($stranger)
        ->putJson("http://goal-co.hrm.test/goals/{$goal->uuid}", ['manager_rating' => 1])
        ->assertForbidden();
});

it('records a check-in and completes the goal at 100 percent', function () {
    $goal = Goal::factory()->forEmployee($this->employee)->create(['progress' => 20]);

    $this->actingAs($this->employeeUser)
        ->postJson("http://goal-co.hrm.test/goals/{$goal->uuid}/check-ins", ['progress' => 100, 'note' => 'Done'])
        ->assertCreated()
        ->assertJsonPath('data.goal.progress', 100)
        ->assertJsonPath('data.goal.status', 'completed');

    expect($goal->checkIns()->count())->toBe(1);
});

it('does not let an employee read a colleague\'s goal', function () {
    $goal = Goal::factory()->forEmployee($this->manager)->create();

    $this->actingAs($this->employeeUser)
        ->getJson("http://goal-co.hrm.test/goals/{$goal->uuid}")
        ->assertForbidden();
});
