<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Kpi;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

class KpiPolicy
{
    use AuthorizesWithinTenant;

    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, Kpi $kpi): bool
    {
        if ($user->tenant_id !== $kpi->tenant_id) {
            return false;
        }

        return $this->owns($user, $kpi)
            || $this->managesSubject($user, $kpi)
            || $this->permits($user, $kpi->tenant_id, Permission::PerformanceView, Permission::PerformanceManage, Permission::PerformanceReview);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function update(User $user, Kpi $kpi): bool
    {
        if ($user->tenant_id !== $kpi->tenant_id) {
            return false;
        }

        return $this->owns($user, $kpi)
            || $this->managesSubject($user, $kpi)
            || $this->permits($user, $kpi->tenant_id, Permission::PerformanceManage);
    }

    public function delete(User $user, Kpi $kpi): bool
    {
        return $this->update($user, $kpi);
    }

    private function owns(User $user, Kpi $kpi): bool
    {
        return $kpi->employee->user_id === $user->getKey();
    }

    private function managesSubject(User $user, Kpi $kpi): bool
    {
        return $kpi->employee->manager?->user_id === $user->getKey();
    }
}
