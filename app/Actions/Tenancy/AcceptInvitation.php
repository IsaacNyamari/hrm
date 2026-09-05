<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Models\Invitation;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AcceptInvitation
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Invitation $invitation, string $name, string $password): User
    {
        if (! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'token' => 'This invitation is no longer valid.',
            ]);
        }

        $tenant = $invitation->tenant;

        return DB::transaction(fn (): User => $this->context->run($tenant, function () use ($invitation, $tenant, $name, $password): User {
            $existing = User::query()->where('email', $invitation->email)->first();

            if ($existing !== null) {
                throw ValidationException::withMessages([
                    'email' => 'An account with this email already exists. Please sign in instead.',
                ]);
            }

            $user = User::create([
                'name' => $name,
                'email' => $invitation->email,
                'password' => Hash::make($password),
            ]);

            $user->forceFill([
                'tenant_id' => $tenant->getKey(),
                'email_verified_at' => now(),
            ])->save();

            $user->assignRole($invitation->role->value);

            $invitation->forceFill([
                'accepted_at' => now(),
                'accepted_user_id' => $user->getKey(),
            ])->save();

            return $user;
        }));
    }
}
