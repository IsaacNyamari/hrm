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
        return view('livewire.organization.chart', [
            'tree' => $this->view === 'departments' ? $chart->departmentTree() : $chart->reportingTree(),
            'childKey' => $this->view === 'departments' ? 'children' : 'reports',
        ]);
    }
}
