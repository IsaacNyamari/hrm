<?php

declare(strict_types=1);

namespace App\Livewire\Finance;

use App\Enums\AssetAssignmentStatus;
use App\Livewire\Concerns\BindsTenant;
use App\Models\Asset;
use App\Models\AssetAssignment;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Assets extends Component
{
    use BindsTenant;

    public function render(): View
    {
        $user = auth()->user();
        $employee = $user?->employee;
        $canManage = $user?->can('assets.view') || $user?->can('assets.manage');

        $mine = $employee !== null
            ? AssetAssignment::query()
                ->where('employee_id', $employee->getKey())
                ->where('status', AssetAssignmentStatus::Active->value)
                ->with('asset')
                ->get()
            : collect();

        $register = $canManage
            ? Asset::query()->with('activeAssignments.employee:id,first_name,last_name')->orderBy('asset_tag')->limit(200)->get()
            : collect();

        return view('livewire.finance.assets', [
            'canManage' => $canManage,
            'mine' => $mine,
            'register' => $register,
        ]);
    }
}
