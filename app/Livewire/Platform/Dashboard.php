<?php

declare(strict_types=1);

namespace App\Livewire\Platform;

use App\Enums\PlatformPermission;
use App\Livewire\Concerns\BindsTenant;
use App\Livewire\Platform\Concerns\WithPlatformGuard;
use App\Services\Platform\PlatformMetrics;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Dashboard extends Component
{
    use BindsTenant;
    use WithPlatformGuard;

    public function mount(): void
    {
        $this->authorizePlatform(PlatformPermission::DashboardView);
    }

    public function render(PlatformMetrics $metrics): View
    {
        return view('livewire.platform.dashboard', $metrics->snapshot());
    }
}
