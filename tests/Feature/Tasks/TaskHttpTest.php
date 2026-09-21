<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\TaskPriority;
use App\Models\Employee;
use App\Models\Task;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskCompletedNotification;
use App\Services\Tasks\TaskService;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'tk-co']);
    app(TenantContext::class)->set($this->tenant);

    $this->employeeUser = userForTenant($this->tenant, Role::Employee);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create(['user_id' => $this->employeeUser->id]);

    $this->hrUser = userForTenant($this->tenant, Role::HrManager); // tasks.assign + tasks.manage
});

it('lets a manager assign a task and notifies the employee', function () {
    Notification::fake();

    $this->actingAs($this->hrUser)
        ->postJson('http://tk-co.hrm.test/tasks', [
            'employee_uuid' => $this->employee->uuid,
            'title' => 'Submit Q3 report',
            'priority' => 'high',
            'due_date' => now()->addDays(3)->toDateString(),
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.priority', 'high');

    Notification::assertSentTo($this->employeeUser, TaskAssignedNotification::class);
});

it('forbids an employee without tasks.assign from creating a task', function () {
    $this->actingAs($this->employeeUser)
        ->postJson('http://tk-co.hrm.test/tasks', [
            'employee_uuid' => $this->employee->uuid,
            'title' => 'Should not be created',
        ])
        ->assertForbidden();
});

it('an employee only sees their own tasks in the list', function () {
    app(TaskService::class)->assign($this->employee, 'Mine', null, TaskPriority::Medium, null, $this->hrUser);

    $other = Employee::factory()->forTenant($this->tenant)->create();
    Task::factory()->forEmployee($other)->count(2)->create();

    $this->actingAs($this->employeeUser)
        ->getJson('http://tk-co.hrm.test/tasks')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($this->hrUser)
        ->getJson('http://tk-co.hrm.test/tasks')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('lets the assignee start and complete their task end to end, notifying the assigner', function () {
    Notification::fake();
    $task = app(TaskService::class)->assign($this->employee, 'Fix the printer', null, TaskPriority::Medium, null, $this->hrUser);

    $this->actingAs($this->employeeUser)
        ->postJson("http://tk-co.hrm.test/tasks/{$task->uuid}/start")
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');

    $this->actingAs($this->employeeUser)
        ->postJson("http://tk-co.hrm.test/tasks/{$task->uuid}/complete", ['remarks' => 'Replaced the toner.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    Notification::assertSentTo($this->hrUser, TaskCompletedNotification::class);
});

it('forbids a bystander from starting someone else\'s task', function () {
    $task = app(TaskService::class)->assign($this->employee, 'Fix the printer', null, TaskPriority::Medium, null, $this->hrUser);
    $bystander = userForTenant($this->tenant, Role::Employee);

    $this->actingAs($bystander)
        ->postJson("http://tk-co.hrm.test/tasks/{$task->uuid}/start")
        ->assertForbidden();
});

it('lets a manager reassign a task via update', function () {
    $task = app(TaskService::class)->assign($this->employee, 'Fix the printer', null, TaskPriority::Medium, null, $this->hrUser);
    $other = Employee::factory()->forTenant($this->tenant)->create();

    $this->actingAs($this->hrUser)
        ->putJson("http://tk-co.hrm.test/tasks/{$task->uuid}", ['employee_uuid' => $other->uuid])
        ->assertOk();

    expect($task->fresh()->employee_id)->toBe($other->id)
        ->and($task->fresh()->status->value)->toBe('pending');
});

it('lets a manager cancel and delete a task', function () {
    $task = app(TaskService::class)->assign($this->employee, 'Fix the printer', null, TaskPriority::Medium, null, $this->hrUser);

    $this->actingAs($this->hrUser)
        ->postJson("http://tk-co.hrm.test/tasks/{$task->uuid}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $this->actingAs($this->hrUser)
        ->deleteJson("http://tk-co.hrm.test/tasks/{$task->uuid}")
        ->assertNoContent();

    expect(Task::query()->find($task->id))->toBeNull();
});

it('cannot act on another tenant\'s task', function () {
    $other = tenantWithRoles(['slug' => 'rival-tk-co']);
    $otherEmployee = Employee::factory()->forTenant($other)->create();
    $victim = Task::factory()->forEmployee($otherEmployee)->create();

    $this->actingAs($this->hrUser)
        ->postJson("http://tk-co.hrm.test/tasks/{$victim->uuid}/cancel")
        ->assertNotFound();
});
