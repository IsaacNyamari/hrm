<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ExpenseDecided;
use App\Events\LeaveApproved;
use App\Events\LeaveRejected;
use App\Support\Auditor;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;

class RecordAuditTrail
{
    public function __construct(private readonly Auditor $auditor) {}

    public function handleLogin(Login $event): void
    {
        $this->auditor->log('auth.login', $event->user instanceof Model ? $event->user : null);
    }

    public function handleLogout(Logout $event): void
    {
        $this->auditor->log('auth.logout', $event->user instanceof Model ? $event->user : null);
    }

    public function handleFailed(Failed $event): void
    {
        $this->auditor->log('auth.failed', null, ['email' => $event->credentials['email'] ?? null]);
    }

    public function handleLockout(Lockout $event): void
    {
        $this->auditor->log('auth.lockout', null, ['ip' => $event->request->ip()]);
    }

    public function handleLeaveApproved(LeaveApproved $event): void
    {
        $this->auditor->log('leave.approved', $event->leaveRequest, [
            'employee' => $event->leaveRequest->employee->fullName(),
            'days' => $event->leaveRequest->days,
        ]);
    }

    public function handleLeaveRejected(LeaveRejected $event): void
    {
        $this->auditor->log('leave.rejected', $event->leaveRequest, [
            'employee' => $event->leaveRequest->employee->fullName(),
        ]);
    }

    public function handleExpenseDecided(ExpenseDecided $event): void
    {
        $this->auditor->log(
            $event->approved ? 'expense.approved' : 'expense.rejected',
            $event->expense,
            ['reference' => $event->expense->reference, 'total_cents' => $event->expense->total_cents],
        );
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(): array
    {
        return [
            Login::class => 'handleLogin',
            Logout::class => 'handleLogout',
            Failed::class => 'handleFailed',
            Lockout::class => 'handleLockout',
            LeaveApproved::class => 'handleLeaveApproved',
            LeaveRejected::class => 'handleLeaveRejected',
            ExpenseDecided::class => 'handleExpenseDecided',
        ];
    }
}
