<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Attendance;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

class AttendancePolicy
{
    use AuthorizesWithinTenant;

    public function viewAny(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::AttendanceView, Permission::AttendanceManage);
    }

    public function view(User $user, Attendance $attendance): bool
    {
        if ($user->tenant_id !== $attendance->tenant_id) {
            return false;
        }

        if ($this->permits($user, $attendance->tenant_id, Permission::AttendanceView, Permission::AttendanceManage)) {
            return true;
        }

        return $user->employee?->getKey() === $attendance->employee_id;
    }

    public function manage(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::AttendanceManage);
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, Attendance $attendance): bool
    {
        return $user->tenant_id === $attendance->tenant_id && $this->manage($user);
    }

    public function delete(User $user, Attendance $attendance): bool
    {
        return $this->update($user, $attendance);
    }
}
