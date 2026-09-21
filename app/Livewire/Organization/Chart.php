<?php

declare(strict_types=1);

namespace App\Livewire\Organization;

use App\Livewire\Concerns\BindsTenant;
use App\Models\Employee;
use App\Services\Organization\OrgChartService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

class Chart extends Component
{
    use BindsTenant;

    #[Url]
    public string $view = 'reporting';

    public function mount(): void
    {
        Gate::authorize('viewAny', Employee::class);
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
