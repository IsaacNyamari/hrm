<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Enums\BillingInterval;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantCache;
use Carbon\CarbonImmutable;

/**
 * Cross-tenant headline figures for the platform (SaaS operator) dashboard.
 * Every query runs with no tenant bound, so the global TenantScope is a no-op
 * and counts span all organizations.
 */
class PlatformMetrics
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return TenantCache::remember('platform.metrics', 60, fn (): array => $this->compute());
    }

    /**
     * @return array<string, mixed>
     */
    private function compute(): array
    {
        $now = CarbonImmutable::now();

        $tenantsByStatus = Tenant::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $activeSubscriptions = Subscription::query()
            ->with('plan:id,name,price_cents,billing_interval')
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])
            ->get();

        $mrrCents = $activeSubscriptions->sum(fn (Subscription $s): int => $this->monthlyCents($s));

        $planBreakdown = $activeSubscriptions
            ->groupBy(fn (Subscription $s): string => $s->plan->name)
            ->map(fn ($group, string $name): array => [
                'plan' => $name,
                'subscriptions' => $group->count(),
                'mrr_cents' => (int) $group->sum(fn (Subscription $s): int => $this->monthlyCents($s)),
            ])
            ->sortByDesc('subscriptions')
            ->values()
            ->all();

        $revenueThisMonthCents = (int) Payment::query()
            ->where('status', PaymentStatus::Succeeded)
            ->where('paid_at', '>=', $now->startOfMonth())
            ->sum('amount_cents');

        $cards = [
            ['key' => 'tenants', 'label' => 'Organizations', 'value' => (int) $tenantsByStatus->sum()],
            ['key' => 'active', 'label' => 'Active', 'value' => (int) ($tenantsByStatus[TenantStatus::Active->value] ?? 0)],
            ['key' => 'trialing', 'label' => 'On trial', 'value' => (int) ($tenantsByStatus[TenantStatus::Trialing->value] ?? 0)],
            ['key' => 'suspended', 'label' => 'Suspended', 'value' => (int) ($tenantsByStatus[TenantStatus::Suspended->value] ?? 0)],
            ['key' => 'mrr', 'label' => 'MRR', 'value' => $this->money($mrrCents)],
            ['key' => 'revenue_month', 'label' => 'Collected this month', 'value' => $this->money($revenueThisMonthCents)],
            ['key' => 'employees', 'label' => 'Employees (all orgs)', 'value' => (int) Employee::query()->count()],
            ['key' => 'users', 'label' => 'User accounts', 'value' => (int) User::query()->whereNotNull('tenant_id')->count()],
            ['key' => 'signups_30d', 'label' => 'New orgs (30 days)', 'value' => (int) Tenant::query()->where('created_at', '>=', $now->subDays(30))->count()],
            ['key' => 'invoices_open', 'label' => 'Open invoices', 'value' => (int) Invoice::query()->where('status', InvoiceStatus::Open)->count()],
        ];

        $recentTenants = Tenant::query()
            ->with('subscription.plan:id,name')
            ->latest('id')
            ->limit(8)
            ->get();

        return [
            'cards' => $cards,
            'plan_breakdown' => $planBreakdown,
            'recent_tenants' => $recentTenants,
            'mrr_cents' => $mrrCents,
            'currency' => 'KES',
        ];
    }

    /**
     * A subscription's contribution to monthly recurring revenue in minor units.
     * Yearly plans are amortised across twelve months.
     */
    private function monthlyCents(Subscription $subscription): int
    {
        $price = $subscription->plan->price_cents;

        return $subscription->plan->billing_interval === BillingInterval::Yearly
            ? intdiv($price, 12)
            : $price;
    }

    private function money(int $cents): string
    {
        return 'KES '.number_format($cents / 100, 0);
    }
}
