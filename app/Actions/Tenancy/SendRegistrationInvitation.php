<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\RegistrationInvitation;
use App\Models\User;
use App\Notifications\RegistrationAccessNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Grants a prospect access to register an organization: creates a single-use
 * link and emails it. Any earlier unopened link for the same address is
 * revoked so only the newest one works.
 */
class SendRegistrationInvitation
{
    public function handle(
        string $email,
        ?string $name = null,
        ?string $company = null,
        ?Lead $lead = null,
        ?User $invitedBy = null,
        int $expiresInDays = 7,
    ): RegistrationInvitation {
        $email = Str::lower(trim($email));
        $token = Str::random(64);

        $invitation = DB::transaction(function () use ($email, $name, $company, $lead, $invitedBy, $expiresInDays, $token): RegistrationInvitation {
            RegistrationInvitation::query()
                ->where('email', $email)
                ->whereNull('opened_at')
                ->whereNull('used_at')
                ->update(['expires_at' => now()]);

            $invitation = RegistrationInvitation::query()->create([
                'lead_id' => $lead?->getKey(),
                'email' => $email,
                'name' => $name,
                'company' => $company,
                'token_hash' => RegistrationInvitation::hashToken($token),
                'invited_by' => $invitedBy?->getKey(),
                'expires_at' => now()->addDays($expiresInDays),
            ]);

            $lead?->forceFill(['status' => LeadStatus::Invited, 'invited_at' => now()])->save();

            return $invitation;
        });

        Notification::route('mail', $email)->notify(new RegistrationAccessNotification($invitation, $token));

        return $invitation;
    }
}
