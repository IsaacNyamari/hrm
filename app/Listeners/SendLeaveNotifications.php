<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\Permission;
use App\Events\LeaveApproved;
use App\Events\LeaveRejected;
use App\Events\LeaveRequested;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\LeaveDecisionNotification;
use App\Notifications\LeaveRequestedNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

class SendLeaveNotifications
{
    public function handleRequested(LeaveRequested $event): void
    {
        $request = $event->leaveRequest;
        $approval = $request->approvals()->where('level', $request->current_level)->first();

        if ($approval === null) {
            return;
        }

        $recipients = $approval->approver_type === 'manager'
            ? User::query()->whereKey($request->employee->manager?->user_id)->get()
            : $this->hrApprovers($request);

        Notification::send($recipients, new LeaveRequestedNotification($request));
    }

    public function handleApproved(LeaveApproved $event): void
    {
        $this->notifyEmployee($event->leaveRequest);
    }

    public function handleRejected(LeaveRejected $event): void
    {
        $this->notifyEmployee($event->leaveRequest);
    }

    private function notifyEmployee(LeaveRequest $request): void
    {
        $userId = $request->employee->user_id;

        if ($userId !== null) {
            Notification::send(User::query()->whereKey($userId)->get(), new LeaveDecisionNotification($request));
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function hrApprovers(LeaveRequest $request): Collection
    {
        $registrar = app(PermissionRegistrar::class);
        $previous = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($request->tenant_id);

        try {
            return User::query()
                ->where('tenant_id', $request->tenant_id)
                ->permission(Permission::LeaveApprove->value)
                ->get();
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
            LeaveRequested::class => 'handleRequested',
            LeaveApproved::class => 'handleApproved',
            LeaveRejected::class => 'handleRejected',
        ];
    }
}
