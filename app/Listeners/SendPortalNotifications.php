<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\Permission;
use App\Events\AnnouncementPublished;
use App\Events\HrRequestSubmitted;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\AnnouncementPublishedNotification;
use App\Notifications\HrRequestSubmittedNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

class SendPortalNotifications
{
    public function handleHrRequest(HrRequestSubmitted $event): void
    {
        $recipients = $this->withinTeam($event->request->tenant_id, fn () => User::query()
            ->where('tenant_id', $event->request->tenant_id)
            ->permission(Permission::EmployeesView->value)
            ->get());

        Notification::send($recipients, new HrRequestSubmittedNotification($event->request));
    }

    public function handleAnnouncement(AnnouncementPublished $event): void
    {
        $announcement = $event->announcement;

        $userIds = Employee::query()
            ->whereNotNull('user_id')
            ->when($announcement->audience === 'department' && $announcement->department_id !== null,
                fn ($q) => $q->where('department_id', $announcement->department_id))
            ->when($announcement->audience === 'branch' && $announcement->branch_id !== null,
                fn ($q) => $q->where('branch_id', $announcement->branch_id))
            ->pluck('user_id');

        Notification::send(User::query()->whereIn('id', $userIds)->get(), new AnnouncementPublishedNotification($announcement));
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
            HrRequestSubmitted::class => 'handleHrRequest',
            AnnouncementPublished::class => 'handleAnnouncement',
        ];
    }
}
