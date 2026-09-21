<?php

declare(strict_types=1);

namespace App\Livewire\Tasks;

use App\Enums\TaskPriority;
use App\Exceptions\TaskException;
use App\Livewire\Concerns\BindsTenant;
use App\Models\Employee;
use App\Models\Task;
use App\Services\Tasks\TaskService;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Board extends Component
{
    use BindsTenant;

    public bool $showForm = false;

    public ?string $editingUuid = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public string $completingUuid = '';

    public string $completingRemarks = '';

    public function startCreate(): void
    {
        Gate::authorize('create', Task::class);
        $this->resetErrorBag();
        $this->editingUuid = null;
        $this->form = $this->blankForm();
        $this->showForm = true;
    }

    public function startEdit(string $uuid): void
    {
        $task = Task::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('update', $task);

        $this->resetErrorBag();
        $this->editingUuid = $task->uuid;
        $this->form = [
            'employee_id' => (string) $task->employee_id,
            'title' => $task->title,
            'description' => (string) $task->description,
            'priority' => $task->priority->value,
            'due_date' => $task->due_date?->toDateString() ?? '',
        ];
        $this->showForm = true;
    }

    public function save(TaskService $service): void
    {
        $data = $this->validate([
            'form.employee_id' => ['required', 'integer'],
            'form.title' => ['required', 'string', 'max:255'],
            'form.description' => ['nullable', 'string', 'max:5000'],
            'form.priority' => ['required', 'string'],
            'form.due_date' => ['nullable', 'date'],
        ])['form'];

        $employee = Employee::query()->findOrFail((int) $data['employee_id']);
        $dueDate = $data['due_date'] !== '' && $data['due_date'] !== null ? CarbonImmutable::parse($data['due_date']) : null;

        if ($this->editingUuid === null) {
            Gate::authorize('create', Task::class);

            $service->assign(
                $employee,
                $data['title'],
                $data['description'] !== '' ? $data['description'] : null,
                TaskPriority::from($data['priority']),
                $dueDate,
                auth()->user(),
            );

            Flux::toast(variant: 'success', text: __('Task assigned.'));
        } else {
            $task = Task::query()->where('uuid', $this->editingUuid)->firstOrFail();
            Gate::authorize('update', $task);

            if ($task->employee_id !== (int) $data['employee_id']) {
                try {
                    $service->reassign($task, $employee);
                } catch (TaskException $e) {
                    Flux::toast(variant: 'danger', text: $e->getMessage());

                    return;
                }
            }

            $service->update($task, [
                'title' => $data['title'],
                'description' => $data['description'] !== '' ? $data['description'] : null,
                'priority' => TaskPriority::from($data['priority']),
                'due_date' => $dueDate,
            ]);

            Flux::toast(variant: 'success', text: __('Task updated.'));
        }

        $this->showForm = false;
        $this->editingUuid = null;
    }

    public function startTask(string $uuid, TaskService $service): void
    {
        $task = Task::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('updateStatus', $task);

        try {
            $service->start($task, auth()->user());
            Flux::toast(variant: 'success', text: __('Task started.'));
        } catch (TaskException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function startComplete(string $uuid): void
    {
        $task = Task::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('updateStatus', $task);

        $this->completingUuid = $uuid;
        $this->completingRemarks = '';
        Flux::modal('complete-task')->show();
    }

    public function completeTask(TaskService $service): void
    {
        $task = Task::query()->where('uuid', $this->completingUuid)->firstOrFail();
        Gate::authorize('updateStatus', $task);

        try {
            $service->complete($task, auth()->user(), $this->completingRemarks !== '' ? $this->completingRemarks : null);
            Flux::modal('complete-task')->close();
            Flux::toast(variant: 'success', text: __('Task marked complete.'));
        } catch (TaskException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function cancelTask(string $uuid, TaskService $service): void
    {
        $task = Task::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('update', $task);

        try {
            $service->cancel($task);
            Flux::modal('cancel-task-'.$uuid)->close();
            Flux::toast(variant: 'success', text: __('Task cancelled.'));
        } catch (TaskException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    public function removeTask(string $uuid): void
    {
        $task = Task::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('delete', $task);

        $task->delete();
        Flux::modal('delete-task-'.$uuid)->close();
        Flux::toast(variant: 'success', text: __('Task deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function blankForm(): array
    {
        return ['employee_id' => '', 'title' => '', 'description' => '', 'priority' => 'medium', 'due_date' => ''];
    }

    public function render(): View
    {
        $user = auth()->user();
        $employee = $user?->employee;
        $canManage = $user?->can('tasks.manage') ?? false;
        $canAssign = $canManage || ($user?->can('tasks.assign') ?? false);
        $canOversee = $canAssign || ($user?->can('tasks.view') ?? false);

        $myTasks = $employee !== null
            ? $employee->tasks()->with('assignedByUser:id,name')->latest('id')->get()
            : collect();

        $allTasks = $canOversee
            ? Task::query()->with(['employee:id,first_name,last_name,user_id', 'assignedByUser:id,name'])->latest('id')->get()
            : collect();

        return view('livewire.tasks.board', [
            'hasEmployee' => $employee !== null,
            'myTasks' => $myTasks,
            'allTasks' => $allTasks,
            'canOversee' => $canOversee,
            'canAssign' => $canAssign,
            'canManage' => $canManage,
            'priorities' => TaskPriority::cases(),
            'employees' => $canAssign ? Employee::query()->active()->orderBy('first_name')->get(['id', 'first_name', 'last_name']) : collect(),
        ]);
    }
}
