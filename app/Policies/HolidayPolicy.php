<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Holiday;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

class HolidayPolicy
{
    use AuthorizesWithinTenant;

    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, Holiday $holiday): bool
    {
        return $user->tenant_id === $holiday->tenant_id;
    }

    public function create(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::LeaveManage);
    }

    public function update(User $user, Holiday $holiday): bool
    {
        return $user->tenant_id === $holiday->tenant_id
            && $this->permits($user, $holiday->tenant_id, Permission::LeaveManage);
    }

    public function delete(User $user, Holiday $holiday): bool
    {
        return $this->update($user, $holiday);
    }
}
