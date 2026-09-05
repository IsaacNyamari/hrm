<?php

declare(strict_types=1);

namespace App\Livewire\Analytics;

use App\Livewire\Concerns\BindsTenant;
use App\Services\Reporting\DashboardMetrics;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Overview extends Component
{
    use BindsTenant;

    public function render(DashboardMetrics $metrics): View
    {
        $user = auth()->user();

        return view('livewire.analytics.overview', [
            'cards' => $user !== null ? $metrics->for($user) : [],
        ]);
    }
}
