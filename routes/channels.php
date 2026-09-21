<?php

use App\Enums\Permission;
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
| Public: `application.{trackingCode}` needs no authorisation. Its events carry
| only the stage and status; the tracking page re-reads details itself using
| the code *and* the applicant's email.
*/
