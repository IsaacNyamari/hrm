<?php

declare(strict_types=1);

namespace App\Livewire\Organization;

use App\Enums\Permission;
use App\Livewire\Concerns\BindsTenant;
use App\Models\Employee;
use App\Policies\Concerns\AuthorizesWithinTenant;
use App\Services\Organization\OrgChartService;
use App\Support\TenantCache;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

class Chart extends Component
{
    use AuthorizesWithinTenant;
    use BindsTenant;

    #[Url]
    public string $view = 'reporting';

    public ?string $reassigningUuid = null;

    public string $reassigningName = '';

    public string $newManagerId = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Employee::class);
    }

    public function startReassign(string $uuid): void
    {
        $employee = Employee::query()->where('uuid', $uuid)->firstOrFail();
        Gate::authorize('update', $employee);

        $this->resetErrorBag();
        $this->reassigningUuid = $uuid;
        $this->reassigningName = $employee->fullName();
        $this->newManagerId = $employee->manager_id !== null ? (string) $employee->manager_id : '';
        Flux::modal('reassign-manager')->show();
    }

    public function saveManager(): void
    {
        $employee = Employee::query()->where('uuid', $this->reassigningUuid)->firstOrFail();
        Gate::authorize('update', $employee);

        $data = $this->validate(['newManagerId' => ['nullable', 'integer']]);
        $managerId = $data['newManagerId'] !== '' && $data['newManagerId'] !== null ? (int) $data['newManagerId'] : null;

        if ($managerId === $employee->id) {
            Flux::toast(variant: 'danger', text: __('An employee cannot manage themselves.'));

            return;
        }

        if ($managerId !== null && $this->wouldCreateCycle($employee, $managerId)) {
            Flux::toast(variant: 'danger', text: __('That would create a reporting loop — choose someone outside :name\'s own reporting line.', ['name' => $employee->fullName()]));

            return;
        }

        $employee->update(['manager_id' => $managerId]);
        TenantCache::forget('org.reporting-tree.v2');

        $this->reassigningUuid = null;
        Flux::modal('reassign-manager')->close();
        Flux::toast(variant: 'success', text: __(':name\'s manager was updated.', ['name' => $employee->fullName()]));
    }

    /**
     * True if $candidateManagerId is $employee themselves, or one of their own
     * (direct or indirect) reports — assigning either would create a loop.
     */
    private function wouldCreateCycle(Employee $employee, int $candidateManagerId): bool
    {
        $currentId = $candidateManagerId;
        $seen = [];

        while ($currentId !== null) {
            if ($currentId === $employee->id) {
                return true;
            }

            if (isset($seen[$currentId])) {
                return false; // pre-existing loop elsewhere in the data; not this change's doing
            }

            $seen[$currentId] = true;
            $currentId = Employee::query()->where('id', $currentId)->value('manager_id');
        }

        return false;
    }

    public function render(OrgChartService $chart): View
    {
        $isDepartments = $this->view === 'departments';
        $tree = $isDepartments ? $chart->departmentTree() : $chart->reportingTree();
        $childKey = $isDepartments ? 'children' : 'reports';

        $nodes = $this->flatten($tree, $childKey);
        $withChildren = collect($nodes)->filter(fn (array $node): bool => count($node[$childKey]) > 0)->count();

        $stats = $isDepartments
            ? [
                [__('Departments'), count($nodes), 'building-office-2'],
                [__('People in departments'), collect($nodes)->sum('headcount'), 'users'],
                [__('Without a head'), collect($nodes)->whereNull('head')->count(), 'exclamation-triangle'],
                [__('Levels'), $this->depth($tree, $childKey), 'bars-3-bottom-left'],
            ]
            : [
                [__('People'), count($nodes), 'users'],
                [__('Managers'), $withChildren, 'user-group'],
                [__('Top-level'), count($tree), 'star'],
                [__('Levels'), $this->depth($tree, $childKey), 'bars-3-bottom-left'],
            ];

        return view('livewire.organization.chart', [
            'tree' => $tree,
            'childKey' => $childKey,
            'stats' => $stats,
            'canManage' => auth()->user() !== null && $this->permits(auth()->user(), auth()->user()->tenant_id, Permission::EmployeesUpdate),
            'employees' => Employee::query()->active()->orderBy('first_name')->get(['id', 'uuid', 'first_name', 'last_name']),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $tree
     * @return array<int, array<string, mixed>>
     */
    private function flatten(array $tree, string $childKey): array
    {
        $nodes = [];

        foreach ($tree as $node) {
            $nodes[] = $node;
            array_push($nodes, ...$this->flatten($node[$childKey], $childKey));
        }

        return $nodes;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tree
     */
    private function depth(array $tree, string $childKey): int
    {
        return $tree === [] ? 0 : 1 + max(array_map(fn (array $node): int => $this->depth($node[$childKey], $childKey), $tree));
    }
}
