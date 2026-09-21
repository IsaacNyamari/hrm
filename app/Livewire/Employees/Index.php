<?php

declare(strict_types=1);

namespace App\Livewire\Employees;

use App\Actions\Employees\CreateEmployee;
use App\Actions\Employees\UpdateEmployee;
use App\Enums\EmploymentStatus;
use App\Exceptions\PlanLimitExceededException;
use App\Facades\Tenancy;
use App\Livewire\Concerns\BindsTenant;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\PayrollPeriod;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use BindsTenant;
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $status = '';

    #[Url(history: true)]
    public string $department = '';

    #[Url]
    public int $perPage = 15;

    public bool $showForm = false;

    public ?string $editingUuid = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', Employee::class);
        $this->form = $this->blankForm();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'department'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status', 'department');
        $this->resetPage();
    }

    public function startCreate(): void
    {
        Gate::authorize('create', Employee::class);
        $this->resetErrorBag();
        $this->editingUuid = null;
        $this->form = $this->blankForm();
        $this->showForm = true;
    }

    public function startEdit(string $uuid): void
    {
        $employee = Employee::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('update', $employee);

        $this->resetErrorBag();
        $this->editingUuid = $employee->uuid;
        $this->form = [
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'email' => (string) $employee->email,
            'phone' => (string) $employee->phone,
            'job_title' => (string) $employee->job_title,
            'status' => $employee->status->value,
            'hire_date' => (string) $employee->hire_date?->toDateString(),
            'exit_date' => (string) $employee->exit_date?->toDateString(),
            'department_id' => (string) $employee->department_id,
            'designation_id' => (string) $employee->designation_id,
            'branch_id' => (string) $employee->branch_id,
            'employment_type_id' => (string) $employee->employment_type_id,
            'manager_id' => (string) $employee->manager_id,
        ];
        $this->showForm = true;
    }

    public function save(CreateEmployee $create, UpdateEmployee $update): void
    {
        $data = $this->validate([
            'form.first_name' => ['required', 'string', 'max:100'],
            'form.last_name' => ['required', 'string', 'max:100'],
            'form.email' => ['nullable', 'email', 'max:255'],
            'form.phone' => ['nullable', 'string', 'max:30'],
            'form.job_title' => ['nullable', 'string', 'max:150'],
            'form.status' => ['required', 'string'],
            'form.hire_date' => ['nullable', 'date'],
            'form.exit_date' => ['nullable', 'date'],
            'form.department_id' => ['nullable', 'integer'],
            'form.designation_id' => ['nullable', 'integer'],
            'form.branch_id' => ['nullable', 'integer'],
            'form.employment_type_id' => ['nullable', 'integer'],
            'form.manager_id' => ['nullable', 'integer'],
        ])['form'];

        $attributes = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'] !== '' ? $data['email'] : null,
            'phone' => $data['phone'] !== '' ? $data['phone'] : null,
            'job_title' => $data['job_title'] !== '' ? $data['job_title'] : null,
            'status' => $status = EmploymentStatus::from($data['status']),
            'hire_date' => $data['hire_date'] !== '' ? $data['hire_date'] : null,
            // Leavers need an exit date for their final pay; default it to today.
            'exit_date' => match (true) {
                ($data['exit_date'] ?? '') !== '' => $data['exit_date'],
                ! $status->isCurrentlyEmployed() => now()->toDateString(),
                default => null,
            },
            'department_id' => $data['department_id'] !== '' ? (int) $data['department_id'] : null,
            'designation_id' => $data['designation_id'] !== '' ? (int) $data['designation_id'] : null,
            'branch_id' => $data['branch_id'] !== '' ? (int) $data['branch_id'] : null,
            'employment_type_id' => $data['employment_type_id'] !== '' ? (int) $data['employment_type_id'] : null,
            'manager_id' => $data['manager_id'] !== '' ? (int) $data['manager_id'] : null,
        ];

        try {
            if ($this->editingUuid === null) {
                Gate::authorize('create', Employee::class);
                $create->handle(Tenancy::getOrFail(), $attributes);
                Flux::toast(variant: 'success', text: __('Employee added.'));
            } else {
                $employee = Employee::query()->where('uuid', $this->editingUuid)->firstOrFail();
                Gate::authorize('update', $employee);
                $wasEmployed = $employee->status->isCurrentlyEmployed();
                $update->handle($employee, $attributes);
                Flux::toast(variant: 'success', text: $wasEmployed && ! $status->isCurrentlyEmployed()
                    ? __('Employee updated. Settle their final pay under Payroll → Final settlements.')
                    : __('Employee updated.'));
            }
        } catch (PlanLimitExceededException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->form = $this->blankForm();
        $this->editingUuid = null;
        $this->showForm = false;
    }

    public function remove(string $uuid): void
    {
        $employee = Employee::query()->where('uuid', $uuid)->firstOrFail();

        Gate::authorize('delete', $employee);

        $employee->delete();
        Flux::modal('delete-employee-'.$uuid)->close();
        Flux::toast(variant: 'success', text: __('Employee removed.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function blankForm(): array
    {
        return [
            'first_name' => '', 'last_name' => '', 'email' => '', 'phone' => '', 'job_title' => '',
            'status' => 'active', 'hire_date' => '', 'exit_date' => '', 'department_id' => '', 'designation_id' => '',
            'branch_id' => '', 'employment_type_id' => '', 'manager_id' => '',
        ];
    }

    public function render(): View
    {
        $employees = Employee::query()
            ->search($this->search)
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->department !== '', fn ($q) => $q->whereRelation('department', 'uuid', $this->department))
            ->with(['department:id,name', 'designation:id,name', 'branch:id,name'])
            ->orderBy('employee_number')
            ->paginate($this->perPage);

        $user = auth()->user();

        return view('livewire.employees.index', [
            'employees' => $employees,
            'departments' => Department::query()->orderBy('name')->get(['id', 'uuid', 'name']),
            'designations' => Designation::query()->orderBy('name')->get(['id', 'name']),
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'employmentTypes' => EmploymentType::query()->orderBy('name')->get(['id', 'name']),
            'managers' => Employee::query()->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'statuses' => EmploymentStatus::cases(),
            'canCreate' => $user?->can('employees.create'),
            'canUpdate' => $user?->can('employees.update'),
            'canDelete' => $user?->can('employees.delete'),
            'canSettle' => Gate::allows('create', PayrollPeriod::class),
        ]);
    }
}
