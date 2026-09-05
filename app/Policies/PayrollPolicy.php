<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Payslip;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWithinTenant;

/**
 * Covers PayrollPeriod, PayrollRun, SalaryComponent, SalaryStructure and
 * TaxRule via ability-name matching, plus Payslip.view (own-or-permitted).
 * Registered explicitly in AppServiceProvider for those models.
 */
class PayrollPolicy
{
    use AuthorizesWithinTenant;

    public function viewAny(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::PayrollView, Permission::PayrollManage, Permission::PayrollProcess, Permission::PayrollApprove);
    }

    public function view(User $user, mixed $model): bool
    {
        return $user->tenant_id === $model->tenant_id && $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->permits($user, $user->tenant_id, Permission::PayrollManage);
    }

    public function update(User $user, mixed $model): bool
    {
        return $user->tenant_id === $model->tenant_id
            && $this->permits($user, $model->tenant_id, Permission::PayrollManage);
    }

    public function delete(User $user, mixed $model): bool
    {
        return $this->update($user, $model);
    }

    public function process(User $user, mixed $model): bool
    {
        return $user->tenant_id === $model->tenant_id
            && $this->permits($user, $model->tenant_id, Permission::PayrollProcess, Permission::PayrollManage);
    }

    public function approve(User $user, mixed $model): bool
    {
        return $user->tenant_id === $model->tenant_id
            && $this->permits($user, $model->tenant_id, Permission::PayrollApprove);
    }

    public function viewPayslip(User $user, Payslip $payslip): bool
    {
        if ($user->tenant_id !== $payslip->tenant_id) {
            return false;
        }

        if ($this->permits($user, $payslip->tenant_id, Permission::PayrollView, Permission::PayrollManage)) {
            return true;
        }

        return $user->employee?->getKey() === $payslip->employee_id;
    }
}
