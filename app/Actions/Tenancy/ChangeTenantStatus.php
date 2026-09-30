<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Facades\Tenancy;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Support\Auditor;
use App\Support\TenantCache;
use Illuminate\Support\Facades\DB;

/**
 * Suspends or reactivates an organization and keeps its subscription in step,
 * so a suspended tenant can never show an "active" subscription (or count
 * toward revenue) and a reactivated one regains access.
 */
class ChangeTenantStatus
{
    public function __construct(private readonly Auditor $auditor) {}

    public function handle(Tenant $tenant, TenantStatus $status, string $event): Tenant
    {
        $from = $tenant->status;

        DB::transaction(function () use ($tenant, $status): void {
            $tenant->update(['status' => $status]);

            $subscription = Tenancy::run($tenant, fn (): ?Subscription => Subscription::query()->where('tenant_id', $tenant->getKey())->latest('id')->first());

            if ($subscription === null) {
                return;
            }

            if ($status === TenantStatus::Suspended && in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::Trialing, SubscriptionStatus::PastDue], true)) {
                $subscription->forceFill(['status' => SubscriptionStatus::Suspended])->save();
            }

            if ($status === TenantStatus::Active && $subscription->status === SubscriptionStatus::Suspended) {
                $subscription->forceFill(['status' => SubscriptionStatus::Active])->save();
            }
        });

        // The platform dashboard's org counts are cached (see PlatformMetrics).
        TenantCache::forget('platform.metrics');

        // Attribute the entry to the target organization even though the operator has no tenant of their own.
        Tenancy::run($tenant, fn () => $this->auditor->log($event, $tenant, [
            'tenant_id' => $tenant->getKey(),
            'from' => $from->value,
            'to' => $status->value,
        ]));

        return $tenant->refresh();
    }
}
