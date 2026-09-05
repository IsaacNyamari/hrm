<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\Permission;
use App\Events\ExpenseDecided;
use App\Events\ExpenseSubmitted;
use App\Models\User;
use App\Notifications\ExpenseDecidedNotification;
use App\Notifications\ExpenseSubmittedNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

class SendExpenseNotifications
{
    public function handleSubmitted(ExpenseSubmitted $event): void
    {
        $expense = $event->expense->loadMissing(['employee.manager', 'approvals']);

        if ($expense->status->value === 'approved') {
            return; // auto-approved, nothing to review
        }

        $approval = $expense->approvals->firstWhere('level', $expense->current_level);
        $recipients = collect();

        if ($approval?->approver_type === 'manager') {
            $managerUserId = $expense->employee->manager?->user_id;
            if ($managerUserId !== null) {
                $recipients = $recipients->push(User::query()->find($managerUserId));
            }
        } else {
            $recipients = $this->withinTeam($expense->tenant_id, fn () => User::query()
                ->where('tenant_id', $expense->tenant_id)
                ->permission(Permission::ExpensesApprove->value)
                ->get());
        }

        Notification::send($recipients->filter()->unique('id'), new ExpenseSubmittedNotification($expense));
    }

    public function handleDecided(ExpenseDecided $event): void
    {
        $user = $event->expense->employee->user;

        if ($user !== null) {
            Notification::send($user, new ExpenseDecidedNotification($event->expense, $event->approved));
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withinTeam(int $tenantId, callable $callback): mixed
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($tenantId);

        try {
            return $callback();
        } finally {
            $registrar->setPermissionsTeamId($previous);
        }
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(): array
    {
        return [
            ExpenseSubmitted::class => 'handleSubmitted',
            ExpenseDecided::class => 'handleDecided',
        ];
    }
}
