<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\AttendanceDevice;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

class AttendanceDevicePolicy
{
    use AuthorizesWithinTenant;

    public function viewAny(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::AttendanceManage);
    }

    public function view(User $user, AttendanceDevice $device): bool
    {
        return $user->tenant_id === $device->tenant_id
            && $this->permits($user, $device->tenant_id, Permission::AttendanceManage);
    }

    public function create(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::AttendanceManage);
    }

    public function update(User $user, AttendanceDevice $device): bool
    {
        return $user->tenant_id === $device->tenant_id
            && $this->permits($user, $device->tenant_id, Permission::AttendanceManage);
    }

    public function delete(User $user, AttendanceDevice $device): bool
    {
        return $this->update($user, $device);
    }
}
