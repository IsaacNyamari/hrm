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

    /**
     * Refresh when this employee's attendance changes elsewhere (a biometric
     * device, an HR correction or another open tab).
     *
     * @return array<string, string>
     */
    protected function getListeners(): array
    {
        $id = auth()->id();

        return $id === null ? [] : ["echo-private:App.Models.User.{$id},.attendance.logged" => 'refreshOwnAttendance'];
    }

    public function refreshOwnAttendance(): void {}

    public function render(SelfServiceDashboard $dashboard): View
    {
        $employee = auth()->user()?->employee;

        return view('livewire.portal.dashboard', [
            'hasEmployee' => $employee !== null,
            'data' => $employee !== null ? $dashboard->for($employee) : null,
        ]);
    }
}
