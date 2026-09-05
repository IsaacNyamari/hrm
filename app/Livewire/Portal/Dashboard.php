<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Livewire\Concerns\BindsTenant;
use App\Services\SelfService\SelfServiceDashboard;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    use BindsTenant;

    public function render(SelfServiceDashboard $dashboard): View
    {
        $employee = auth()->user()?->employee;

        return view('livewire.portal.dashboard', [
            'hasEmployee' => $employee !== null,
            'data' => $employee !== null ? $dashboard->for($employee) : null,
        ]);
    }
}
