<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Expense;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

class ExpensePolicy
{
    use AuthorizesWithinTenant;

    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function view(User $user, Expense $expense): bool
    {
        if ($user->tenant_id !== $expense->tenant_id) {
            return false;
        }

        return $this->owns($user, $expense)
            || $this->canOversee($user, $expense);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    public function update(User $user, Expense $expense): bool
    {
        if ($user->tenant_id !== $expense->tenant_id) {
            return false;
        }

        return ($this->owns($user, $expense) && $expense->status->isEditable())
            || $this->permits($user, $expense->tenant_id, Permission::ExpensesManage);
    }

    public function submit(User $user, Expense $expense): bool
    {
        return $user->tenant_id === $expense->tenant_id && $this->owns($user, $expense);
    }

    public function decide(User $user, Expense $expense): bool
    {
        if ($user->tenant_id !== $expense->tenant_id) {
            return false;
        }

        // Nobody decides their own claim, however senior.
        if ($expense->employee->user_id === $user->getKey()) {
            return false;
        }

        return $this->permits($user, $expense->tenant_id, Permission::ExpensesApprove, Permission::ExpensesManage)
            || $expense->employee->manager?->user_id === $user->getKey();
    }

    public function reimburse(User $user, Expense $expense): bool
    {
        return $user->tenant_id === $expense->tenant_id
            && $this->permits($user, $expense->tenant_id, Permission::ExpensesManage);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->tenant_id === $expense->tenant_id
            && $this->owns($user, $expense)
            && $expense->status->isEditable();
    }

    private function owns(User $user, Expense $expense): bool
    {
        return $expense->submitted_by === $user->getKey()
            || $expense->employee->user_id === $user->getKey();
    }

    private function canOversee(User $user, Expense $expense): bool
    {
        return $this->permits($user, $expense->tenant_id, Permission::ExpensesView, Permission::ExpensesApprove, Permission::ExpensesManage)
            || $expense->employee->manager?->user_id === $user->getKey();
    }
}
