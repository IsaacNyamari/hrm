<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantInvitationNotification;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class SendInvitation
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * Create (or refresh) a pending invitation for an email + role and email it.
     */
    public function handle(
        Tenant $tenant,
        string $email,
        Role $role,
        ?User $invitedBy = null,
        int $expiresInDays = 7,
    ): Invitation {
        return $this->context->run($tenant, function () use ($tenant, $email, $role, $invitedBy, $expiresInDays): Invitation {
            $invitation = Invitation::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->getKey(),
                    'email' => Str::lower($email),
                    'accepted_at' => null,
                ],
                [
                    'role' => $role,
                    'token' => Str::random(64),
                    'invited_by' => $invitedBy?->getKey(),
                    'expires_at' => now()->addDays($expiresInDays),
                ],
            );

            Notification::route('mail', $email)->notify(new TenantInvitationNotification($invitation));

            return $invitation;
        });
    }
}
