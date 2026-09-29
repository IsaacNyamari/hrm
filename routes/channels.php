<?php

use App\Enums\Permission;
use App\Models\Call;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
| Private: a user's own notifications.
*/
Broadcast::channel('App.Models.User.{id}', function (User $user, int $id): bool {
    return (int) $user->id === $id;
});

/*
| Private: live recruitment board updates for staff of that organization who
| can see recruitment.
*/
Broadcast::channel('tenant.{tenantId}.recruitment', function (User $user, int $tenantId): bool {
    return $user->tenant_id === $tenantId && $user->can(Permission::RecruitmentView->value);
});

/*
| Private: live attendance (punches, breaks, corrections) for staff of that
| organization who can see attendance.
*/
Broadcast::channel('tenant.{tenantId}.attendance', function (User $user, int $tenantId): bool {
    return $user->tenant_id === $tenantId && $user->can(Permission::AttendanceView->value);
});

/*
| Presence: who's currently on the Calls page for this company, so a caller
| can see who's actually available before ringing them.
*/
Broadcast::channel('tenant.{tenantId}.calls', function (User $user, int $tenantId) {
    if ($user->tenant_id !== $tenantId) {
        return false;
    }

    return ['id' => $user->id, 'name' => $user->name, 'initials' => $user->initials()];
});

/*
| Presence: one call's room. Only its participants (invited, joined, or
| otherwise) may join — this is also the channel WebRTC signalling is
| whispered over between peers.
*/
Broadcast::channel('call.{uuid}', function (User $user, string $uuid) {
    $call = Call::query()->where('uuid', $uuid)->first();

    if ($call === null || $call->tenant_id !== $user->tenant_id) {
        return false;
    }

    if (! $call->participants()->where('user_id', $user->id)->exists()) {
        return false;
    }

    return ['id' => $user->id, 'name' => $user->name, 'initials' => $user->initials()];
});

/*
| Public: `application.{trackingCode}` needs no authorisation. Its events carry
| only the stage and status; the tracking page re-reads details itself using
| the code *and* the applicant's email.
*/
