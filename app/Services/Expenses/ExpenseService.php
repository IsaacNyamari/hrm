<?php

declare(strict_types=1);

namespace App\Services\Expenses;

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseStatus;
use App\Enums\LeaveApprovalStatus;
use App\Enums\Permission;
use App\Events\ExpenseDecided;
use App\Events\ExpenseSubmitted;
use App\Exceptions\ExpenseException;
use App\Models\Expense;
use App\Models\ExpenseApproval;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class ExpenseService
{
    public function __construct(private readonly TenantContext $context) {}

    public function nextReference(): string
    {
        return sprintf('EXP-%06d', Expense::query()->count() + 1);
    }

    /**
     * Recompute the header total from the line items and apply the configured
     * mileage rate to any mileage line that did not carry its own amount.
     */
    public function recalculateTotal(Expense $expense): Expense
    {
        $rate = $this->mileageRate();

        $total = 0;
        foreach ($expense->items()->get() as $item) {
            if ($item->category === ExpenseCategory::Mileage && $item->quantity !== null) {
                $unit = $item->unit_rate_cents ?? $rate;
                $amount = (int) round($item->quantity * $unit);

                if ($amount !== $item->amount_cents || $item->unit_rate_cents === null) {
                    $item->forceFill(['amount_cents' => $amount, 'unit_rate_cents' => $unit])->save();
                }

                $total += $amount;

                continue;
            }

            $total += $item->amount_cents;
        }

        $expense->forceFill(['total_cents' => $total])->save();

        return $expense;
    }

    public function submit(Expense $expense, ?User $actor = null): Expense
    {
        if (! $expense->status->isEditable()) {
            throw ExpenseException::notEditable();
        }

        $this->recalculateTotal($expense);

        $items = $expense->items()->get();

        if ($items->isEmpty()) {
            throw ExpenseException::noItems();
        }

        $required = $this->receiptRequiredCategories();
        foreach ($items as $item) {
            if (in_array($item->category->value, $required, true) && $item->receipt_path === null) {
                throw ExpenseException::receiptRequired($item->category->value);
            }
        }

        return DB::transaction(function () use ($expense, $actor): Expense {
            $autoApprove = $this->qualifiesForAutoApprove($expense);

            $expense->forceFill([
                'status' => $autoApprove ? ExpenseStatus::Approved : ExpenseStatus::Submitted,
                'current_level' => 1,
                'submitted_by' => $actor?->getKey(),
                'submitted_at' => CarbonImmutable::now(),
                'decided_at' => $autoApprove ? CarbonImmutable::now() : null,
            ])->save();

            $this->buildApprovalChain($expense);

            ExpenseSubmitted::dispatch($expense);

            if ($autoApprove) {
                ExpenseDecided::dispatch($expense, true);
            }

            return $expense->load('approvals');
        });
    }

    public function decide(Expense $expense, User $approver, bool $approved, ?string $comment = null): Expense
    {
        if (! $expense->status->isPending()) {
            throw ExpenseException::notPending();
        }

        $approval = $expense->approvals()->where('level', $expense->current_level)->first();

        if ($approval === null || ! $this->canActOn($expense, $approval, $approver)) {
            throw ExpenseException::notAnApprover();
        }

        return DB::transaction(function () use ($expense, $approval, $approver, $approved, $comment): Expense {
            $approval->forceFill([
                'status' => $approved ? LeaveApprovalStatus::Approved : LeaveApprovalStatus::Rejected,
                'approver_id' => $approver->getKey(),
                'comment' => $comment,
                'decided_at' => CarbonImmutable::now(),
            ])->save();

            if (! $approved) {
                $expense->forceFill([
                    'status' => ExpenseStatus::Rejected,
                    'decided_at' => CarbonImmutable::now(),
                    'decision_note' => $comment,
                ])->save();
                ExpenseDecided::dispatch($expense, false);

                return $expense;
            }

            $nextLevel = $expense->approvals()->where('level', '>', $expense->current_level)->min('level');

            if ($nextLevel !== null) {
                $expense->forceFill(['current_level' => $nextLevel])->save();

                return $expense;
            }

            $expense->forceFill([
                'status' => ExpenseStatus::Approved,
                'decided_at' => CarbonImmutable::now(),
                'decision_note' => $comment,
            ])->save();

            ExpenseDecided::dispatch($expense, true);

            return $expense;
        });
    }

    public function markReimbursed(Expense $expense, ?string $reference, ?User $actor = null): Expense
    {
        if ($expense->status !== ExpenseStatus::Approved) {
            throw ExpenseException::notApproved();
        }

        $expense->forceFill([
            'status' => ExpenseStatus::Reimbursed,
            'reimbursed_at' => CarbonImmutable::now(),
            'payment_reference' => $reference,
        ])->save();

        return $expense;
    }

    public function cancel(Expense $expense): Expense
    {
        if (in_array($expense->status, [ExpenseStatus::Reimbursed, ExpenseStatus::Cancelled], true)) {
            throw ExpenseException::notEditable();
        }

        $expense->forceFill(['status' => ExpenseStatus::Cancelled, 'decided_at' => CarbonImmutable::now()])->save();

        return $expense;
    }

    private function qualifiesForAutoApprove(Expense $expense): bool
    {
        $threshold = $this->setting('auto_approve_under_cents', config('expenses.auto_approve_under_cents'));

        return is_int($threshold) && $expense->total_cents <= $threshold;
    }

    private function buildApprovalChain(Expense $expense): void
    {
        if ($expense->status === ExpenseStatus::Approved) {
            $expense->approvals()->create([
                'tenant_id' => $expense->tenant_id,
                'level' => 1,
                'approver_type' => 'auto',
                'status' => LeaveApprovalStatus::Skipped,
                'decided_at' => CarbonImmutable::now(),
            ]);

            return;
        }

        $employee = $expense->employee;
        $level = 0;

        foreach ($this->approvalChain() as $step) {
            if ($step === 'manager' && $employee->manager_id === null) {
                continue;
            }

            $expense->approvals()->create([
                'tenant_id' => $expense->tenant_id,
                'level' => ++$level,
                'approver_type' => $step,
                'status' => LeaveApprovalStatus::Pending,
            ]);
        }

        if ($level === 0) {
            $expense->approvals()->create([
                'tenant_id' => $expense->tenant_id,
                'level' => 1,
                'approver_type' => 'finance',
                'status' => LeaveApprovalStatus::Pending,
            ]);
        }
    }

    private function canActOn(Expense $expense, ExpenseApproval $approval, User $approver): bool
    {
        if ($this->userCan($approver, $expense->tenant_id, Permission::ExpensesApprove, Permission::ExpensesManage)) {
            return true;
        }

        if ($approval->approver_type === 'manager') {
            $managerUserId = $expense->employee->manager?->user_id;

            return $managerUserId !== null && $managerUserId === $approver->getKey();
        }

        return false;
    }

    private function userCan(User $user, ?int $tenantId, Permission ...$permissions): bool
    {
        if ($tenantId === null) {
            return false;
        }

        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($tenantId);

        try {
            foreach ($permissions as $permission) {
                if ($user->hasPermissionTo($permission->value)) {
                    return true;
                }
            }

            return false;
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }

    /**
     * @return array<int, string>
     */
    private function approvalChain(): array
    {
        $configured = $this->setting('approval_chain', null);

        /** @var array<int, string> $chain */
        $chain = is_array($configured) && $configured !== [] ? $configured : config('expenses.approval_chain');

        return array_values(array_map('strval', $chain));
    }

    /**
     * @return array<int, string>
     */
    private function receiptRequiredCategories(): array
    {
        /** @var array<int, string> $categories */
        $categories = config('expenses.receipt_required_categories', []);

        return array_map('strval', $categories);
    }

    private function mileageRate(): int
    {
        $rate = $this->setting('mileage_rate_cents_per_km', config('expenses.mileage_rate_cents_per_km', 0));

        return is_numeric($rate) ? (int) $rate : 0;
    }

    private function setting(string $key, mixed $default): mixed
    {
        $tenant = $this->context->get();

        return $tenant?->settings['expenses'][$key] ?? $default;
    }
}
