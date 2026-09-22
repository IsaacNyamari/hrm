<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Employee;
use App\Models\Task;
use App\Support\TenantContext;

beforeEach(function () {
    $this->travelTo('2026-09-22');
    $this->tenant = tenantWithRoles(['slug' => 'task-co']);
    app(TenantContext::class)->set($this->tenant);
    $this->user = userForTenant($this->tenant, Role::Employee);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create(['user_id' => $this->user->id]);
    $this->token = mobileToken($this->user);
});

it('lists open tasks soonest-due first with overdue flags', function () {
    Task::factory()->forEmployee($this->employee)->create(['title' => 'Later', 'due_date' => '2026-10-10']);
    Task::factory()->forEmployee($this->employee)->create(['title' => 'Overdue', 'due_date' => '2026-09-18']);
    Task::factory()->forEmployee($this->employee)->completed()->create(['title' => 'Done']);
    Task::factory()->forEmployee(Employee::factory()->forTenant($this->tenant)->create())->create(['title' => 'Not mine']);

    $this->withToken($this->token)
        ->getJson('/api/v1/me/tasks?status=open')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.title', 'Overdue')
        ->assertJsonPath('data.0.overdue', true)
        ->assertJsonPath('data.1.title', 'Later');

    $this->withToken($this->token)
        ->getJson('/api/v1/me/tasks?status=completed')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Done');

    $this->withToken($this->token)->getJson('/api/v1/me/tasks?status=bogus')->assertStatus(422);
});

it('starts and completes a task', function () {
    $task = Task::factory()->forEmployee($this->employee)->create();

    $this->withToken($this->token)
        ->postJson("/api/v1/me/tasks/{$task->uuid}/start")
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');

    $this->withToken($this->token)
        ->postJson("/api/v1/me/tasks/{$task->uuid}/complete", ['remarks' => 'Filed with KRA'])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    expect($task->fresh()->status)->toBe(TaskStatus::Completed)
        ->and($task->fresh()->remarks)->toBe('Filed with KRA');

    $this->withToken($this->token)->postJson("/api/v1/me/tasks/{$task->uuid}/start")->assertStatus(422);
});

it('cannot work on someone else\'s task', function () {
    $task = Task::factory()->forEmployee(Employee::factory()->forTenant($this->tenant)->create())->create();

    $this->withToken($this->token)->postJson("/api/v1/me/tasks/{$task->uuid}/start")->assertNotFound();
});
