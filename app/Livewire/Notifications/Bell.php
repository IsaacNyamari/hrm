<?php

declare(strict_types=1);

namespace App\Livewire\Notifications;

use App\Enums\Permission;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The header bell (on every page): unread count that updates live, a toast
 * for each new notification, and — for staff who can see attendance — a toast
 * for every attendance punch in the organization.
 */
class Bell extends Component
{
    /**
     * @return array<string, string>
     */
    protected function getListeners(): array
    {
        $user = auth()->user();

        if ($user === null) {
            return [];
        }

        $listeners = [
            "echo-private:App.Models.User.{$user->id},.user.notified" => 'notified',
            "echo-private:App.Models.User.{$user->id},.attendance.logged" => 'ownAttendanceLogged',
        ];

        if ($user->tenant_id !== null && $user->can(Permission::AttendanceView->value)) {
            $listeners["echo-private:tenant.{$user->tenant_id}.attendance,.attendance.logged"] = 'attendanceLogged';
        }

        return $listeners;
    }

    /**
     * @param  array{title?: string, detail?: string}  $payload
     */
    public function notified(array $payload = []): void
    {
        Flux::toast(
            text: (string) ($payload['detail'] ?? ''),
            heading: (string) ($payload['title'] ?? __('New notification')),
        );
    }

    /**
     * HR / supervisors: someone in the organization punched.
     *
     * @param  array{message?: string, action?: string, status?: string}  $payload
     */
    public function attendanceLogged(array $payload = []): void
    {
        $late = in_array($payload['status'] ?? '', ['Late', 'Early Departure'], true);

        Flux::toast(
            text: (string) ($payload['message'] ?? ''),
            heading: __('Attendance'),
            variant: $late ? 'warning' : 'success',
        );
    }

    /**
     * The employee's own record changed from elsewhere (a biometric device or
     * an HR correction). Punches made from their own browser already toast.
     *
     * @param  array{message?: string, source?: string}  $payload
     */
    public function ownAttendanceLogged(array $payload = []): void
    {
        if (in_array($payload['source'] ?? 'web', ['web', 'mobile'], true)) {
            return;
        }

        if (auth()->user()?->can(Permission::AttendanceView->value)) {
            return; // already toasted via the organization channel
        }

        Flux::toast(text: (string) ($payload['message'] ?? ''), heading: __('Your attendance was recorded'));
    }

    public function render(): View
    {
        return view('livewire.notifications.bell', [
            'unread' => auth()->user()?->unreadNotifications()->count() ?? 0,
        ]);
    }
}
