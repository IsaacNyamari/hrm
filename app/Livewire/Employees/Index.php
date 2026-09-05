<?php

declare(strict_types=1);

namespace App\Livewire\Employees;

use App\Enums\EmploymentStatus;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $status = '';

    #[Url(history: true)]
    public string $department = '';

    #[Url]
    public int $perPage = 15;

    public function mount(): void
    {
        Gate::authorize('viewAny', Employee::class);
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

    public function render(): View
    {
        $employees = Employee::query()
            ->search($this->search)
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->department !== '', fn ($q) => $q->whereRelation('department', 'uuid', $this->department))
            ->with(['department:id,name', 'designation:id,name', 'branch:id,name'])
            ->orderBy('employee_number')
            ->paginate($this->perPage);

        return view('livewire.employees.index', [
            'employees' => $employees,
            'departments' => Department::query()->orderBy('name')->get(['uuid', 'name']),
            'statuses' => EmploymentStatus::cases(),
        ]);
    }
}
