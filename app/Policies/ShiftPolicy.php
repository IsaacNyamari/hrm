<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Shift;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

class ShiftPolicy
{
    use AuthorizesWithinTenant;

    public function viewAny(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::AttendanceView, Permission::ShiftsManage);
    }

    public function view(User $user, Shift $shift): bool
    {
        return $user->tenant_id === $shift->tenant_id
            && $this->permits($user, $shift->tenant_id, Permission::AttendanceView, Permission::ShiftsManage);
    }

    public function create(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::ShiftsManage);
    }

    public function update(User $user, Shift $shift): bool
    {
        return $user->tenant_id === $shift->tenant_id
            && $this->permits($user, $shift->tenant_id, Permission::ShiftsManage);
    }

    public function delete(User $user, Shift $shift): bool
    {
        return $this->update($user, $shift);
    }
}
