<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Enums\Role;
use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\RegistrationInvitation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Subscription\SubscriptionManager;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates an organization and its owner from an opened registration link:
 * the tenant, a trial on the entry plan (which provisions roles and reference
 * data), and the owner user with the Tenant Owner role. The link is marked
 * used in the same transaction.
 */
class RegisterOrganization
{
    public function __construct(
        private readonly SubscriptionManager $subscriptions,
        private readonly TenantContext $context,
    ) {}

    public function handle(RegistrationInvitation $invitation, string $organization, string $name, string $password): User
    {
        return DB::transaction(function () use ($invitation, $organization, $name, $password): User {
            $tenant = Tenant::query()->create([
                'name' => $organization,
                'slug' => $this->uniqueSlug($organization),
                'email' => $invitation->email,
                'country' => 'KE',
                'currency' => config('billing.currency', 'KES'),
                'timezone' => 'Africa/Nairobi',
                'status' => TenantStatus::Pending,
            ]);

            $owner = User::query()->create([
                'name' => $name,
                'email' => $invitation->email,
                'password' => Hash::make($password),
                'email_verified_at' => now(), // the emailed link proves the address
                'tenant_id' => $tenant->getKey(),
                'is_platform_admin' => false,
            ]);

            $this->subscriptions->startTrial($tenant, $this->entryPlan());

            $this->context->run($tenant, fn () => $owner->syncRoles([Role::TenantOwner->value]));

            $invitation->forceFill(['used_at' => now(), 'tenant_id' => $tenant->getKey()])->save();

            return $owner;
        });
    }

    private function entryPlan(): Plan
    {
        return Plan::query()->where('is_active', true)->orderBy('sort_order')->orderBy('price_cents')->firstOrFail();
    }

    private function uniqueSlug(string $organization): string
    {
        $base = Str::slug($organization) ?: 'organization';
        $slug = $base;
        $suffix = 2;

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
