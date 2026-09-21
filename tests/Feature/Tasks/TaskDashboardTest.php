<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\TaskPriority;
use App\Livewire\Tasks\Board;
use App\Models\Employee;
use App\Models\Task;
use App\Services\Tasks\TaskService;
use App\Support\TenantContext;
use Livewire\Livewire;

beforeEach(function () {
    config(['tenancy.central_domains' => ['hrm.test']]);
    $this->tenant = tenantWithRoles(['slug' => 'task-co']);
    app(TenantContext::class)->set($this->tenant);

    $this->employeeUser = userForTenant($this->tenant, Role::Employee);
    $this->employee = Employee::factory()->forTenant($this->tenant)->create(['user_id' => $this->employeeUser->id]);

    $this->hr = userForTenant($this->tenant, Role::HrManager); // tasks.assign + tasks.manage
});

it('assigns a task to an employee from the dashboard', function () {
    $this->actingAs($this->hr);

    Livewire::test(Board::class)
        ->call('startCreate')
        ->set('form.employee_id', (string) $this->employee->id)
        ->set('form.title', 'Submit Q3 report')
        ->set('form.priority', 'high')
        ->set('form.due_date', now()->addDays(3)->toDateString())
        ->call('save')
        ->assertDispatched('toast-show')
        ->assertSet('showForm', false);

    $task = Task::query()->where('title', 'Submit Q3 report')->firstOrFail();
    expect($task->employee_id)->toBe($this->employee->id)
        ->and($task->priority->value)->toBe('high')
        ->and($task->status->value)->toBe('pending')
        ->and($task->assigned_by)->toBe($this->hr->id);
});

it('forbids assigning a task from the dashboard without tasks.assign', function () {
    $this->actingAs($this->employeeUser);

    Livewire::test(Board::class)->call('startCreate')->assertForbidden();
});

it('lets the assignee start their own task from the dashboard', function () {
    $task = app(TaskService::class)->assign($this->employee, 'Fix the printer', null, TaskPriority::Medium, null, $this->hr);

    $this->actingAs($this->employeeUser);

    Livewire::test(Board::class)
        ->call('startTask', $task->uuid)
        ->assertDispatched('toast-show');

    expect($task->fresh()->status->value)->toBe('in_progress');
});

it('lets the assignee complete their own task with remarks from the dashboard', function () {
    $task = app(TaskService::class)->assign($this->employee, 'Fix the printer', null, TaskPriority::Medium, null, $this->hr);

    $this->actingAs($this->employeeUser);

    Livewire::test(Board::class)
        ->call('startComplete', $task->uuid)
        ->set('completingRemarks', 'Replaced the toner.')
        ->call('completeTask')
        ->assertDispatched('toast-show');

    expect($task->fresh()->status->value)->toBe('completed')
        ->and($task->fresh()->remarks)->toBe('Replaced the toner.');
});

it('blocks a bystander from updating someone else\'s task through the dashboard', function () {
    $task = app(TaskService::class)->assign($this->employee, 'Fix the printer', null, TaskPriority::Medium, null, $this->hr);
    $bystander = userForTenant($this->tenant, Role::Employee);

    $this->actingAs($bystander);

    Livewire::test(Board::class)->call('startTask', $task->uuid)->assertForbidden();

    expect($task->fresh()->status->value)->toBe('pending');
});

it('lets a manager reassign and edit a task from the dashboard', function () {
    $task = app(TaskService::class)->assign($this->employee, 'Fix the printer', null, TaskPriority::Medium, null, $this->hr);
    $other = Employee::factory()->forTenant($this->tenant)->create();

    $this->actingAs($this->hr);

    Livewire::test(Board::class)
        ->call('startEdit', $task->uuid)
        ->set('form.employee_id', (string) $other->id)
        ->set('form.title', 'Fix the printer (urgent)')
        ->call('save')
        ->assertDispatched('toast-show');

    expect($task->fresh()->employee_id)->toBe($other->id)
        ->and($task->fresh()->title)->toBe('Fix the printer (urgent)');
});

it('lets a manager cancel an open task from the dashboard', function () {
    $task = app(TaskService::class)->assign($this->employee, 'Fix the printer', null, TaskPriority::Medium, null, $this->hr);

    $this->actingAs($this->hr);

    Livewire::test(Board::class)->call('cancelTask', $task->uuid)->assertDispatched('toast-show');

    expect($task->fresh()->status->value)->toBe('cancelled');
});

it('lets a manager delete a task from the dashboard', function () {
    $task = app(TaskService::class)->assign($this->employee, 'Fix the printer', null, TaskPriority::Medium, null, $this->hr);

    $this->actingAs($this->hr);

    Livewire::test(Board::class)->call('removeTask', $task->uuid)->assertDispatched('toast-show');

    expect(Task::query()->find($task->id))->toBeNull();
});

it('forbids a non-manager from editing or deleting a task from the dashboard', function () {
    $task = app(TaskService::class)->assign($this->employee, 'Fix the printer', null, TaskPriority::Medium, null, $this->hr);

    $this->actingAs($this->employeeUser);

    Livewire::test(Board::class)->call('startEdit', $task->uuid)->assertForbidden();
    Livewire::test(Board::class)->call('removeTask', $task->uuid)->assertForbidden();
});

it('shows only the current tenant\'s tasks on the dashboard', function () {
    $other = tenantWithRoles(['slug' => 'task-rival']);
    $rivalEmployee = Employee::factory()->forTenant($other)->create();
    app(TenantContext::class)->run($other, fn () => app(TaskService::class)->assign($rivalEmployee, 'Rival task', null, TaskPriority::Low, null));

    app(TaskService::class)->assign($this->employee, 'Mine task', null, TaskPriority::Low, null, $this->hr);

    $this->actingAs($this->hr);

    Livewire::test(Board::class)
        ->assertSee('Mine task')
        ->assertDontSee('Rival task');
});
