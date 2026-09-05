<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Announcement;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

class AnnouncementPolicy
{
    use AuthorizesWithinTenant;

    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return $user->tenant_id === $announcement->tenant_id;
    }

    public function create(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::EmployeesUpdate, Permission::SettingsManage);
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $user->tenant_id === $announcement->tenant_id
            && $this->permits($user, $announcement->tenant_id, Permission::EmployeesUpdate, Permission::SettingsManage);
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $this->update($user, $announcement);
    }
}
