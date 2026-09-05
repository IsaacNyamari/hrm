<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Asset;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

class AssetPolicy
{
    use AuthorizesWithinTenant;

    public function viewAny(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::AssetsView, Permission::AssetsManage);
    }

    public function view(User $user, Asset $asset): bool
    {
        return $user->tenant_id === $asset->tenant_id
            && $this->permits($user, $asset->tenant_id, Permission::AssetsView, Permission::AssetsManage);
    }

    public function create(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::AssetsManage);
    }

    public function update(User $user, Asset $asset): bool
    {
        return $user->tenant_id === $asset->tenant_id
            && $this->permits($user, $asset->tenant_id, Permission::AssetsManage);
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $this->update($user, $asset);
    }
}
