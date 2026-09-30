<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Livewire\Concerns\BindsTenant;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * A read-only view of the authenticated user's own role and the permissions
 * it grants, so an employee can see what they're allowed to do without
 * needing `roles.manage` themselves (that page is App\Livewire\Settings\PermissionsManager).
 */
class MyPermissions extends Component
{
    use BindsTenant;

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.settings.my-permissions', [
            'roles' => $user?->getRoleNames() ?? collect(),
            'permissions' => $user?->getAllPermissions()->pluck('name')->sort()->values() ?? collect(),
        ]);
    }
}
