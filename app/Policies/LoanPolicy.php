<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Loan;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

class LoanPolicy
{
    use AuthorizesWithinTenant;

    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, Loan $loan): bool
    {
        if ($user->tenant_id !== $loan->tenant_id) {
            return false;
        }

        return $this->permits($user, $loan->tenant_id, Permission::PayrollView, Permission::PayrollManage)
            || $user->employee?->getKey() === $loan->employee_id;
    }

    public function create(User $user): bool
    {
        // Any employee may request a loan / salary advance for themselves;
        // requesting on someone's behalf is gated in the controller.
        return $user->tenant_id !== null;
    }

    public function cancel(User $user, Loan $loan): bool
    {
        if ($user->tenant_id !== $loan->tenant_id) {
            return false;
        }

        return $user->employee?->getKey() === $loan->employee_id
            || $this->permits($user, $loan->tenant_id, Permission::PayrollManage);
    }

    public function update(User $user, Loan $loan): bool
    {
        return $user->tenant_id === $loan->tenant_id
            && $this->permits($user, $loan->tenant_id, Permission::PayrollManage);
    }

    /**
     * Nobody approves a loan or advance taken out by themselves, however senior.
     */
    public function approve(User $user, Loan $loan): bool
    {
        return $user->tenant_id === $loan->tenant_id
            && $user->employee?->getKey() !== $loan->employee_id
            && $this->permits($user, $loan->tenant_id, Permission::PayrollApprove, Permission::PayrollManage);
    }

    public function delete(User $user, Loan $loan): bool
    {
        return $this->update($user, $loan) && $loan->outstanding_cents === $loan->principal_cents;
    }
}
