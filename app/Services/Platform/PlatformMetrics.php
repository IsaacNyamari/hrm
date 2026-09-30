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
use Illuminate\Database\Eloquent\Collection;

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
        // Only scalar figures are cached: with `cache.serializable_classes`
        // disabled, cached Eloquent models come back as incomplete objects.
        return [
            ...TenantCache::remember('platform.metrics', 60, fn (): array => $this->compute()),
            'recent_tenants' => $this->recentTenants(),
        ];
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function recentTenants(): Collection
    {
        return Tenant::query()
            ->with('subscription.plan:id,name')
            ->latest('id')
            ->limit(8)
            ->get();
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
            ->whereNotIn('tenant_id', Tenant::query()->where('status', TenantStatus::Suspended)->select('id'))
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

        $revenueThisMonthCents = $this->collectedBetween($now->startOfMonth(), $now);
        $revenueLastMonthCents = $this->collectedBetween($now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow());
        $revenueTrend = $this->trend($revenueThisMonthCents, $revenueLastMonthCents);

        $signups30d = Tenant::query()->where('created_at', '>=', $now->subDays(30))->count();
        $signupsPrior30d = Tenant::query()->whereBetween('created_at', [$now->subDays(60), $now->subDays(30)])->count();

        $openInvoices = Invoice::query()
            ->where('status', InvoiceStatus::Open)
            ->toBase()
            ->selectRaw('count(*) as aggregate, coalesce(sum(total_cents - amount_paid_cents), 0) as outstanding')
            ->first();

        $statusCount = fn (TenantStatus $status): int => (int) ($tenantsByStatus[$status->value] ?? 0);
        $tenantsIndex = route('platform.tenants.index');

        $cards = [
            [
                'key' => 'mrr',
                'label' => 'Monthly recurring revenue',
                'value' => $this->money($mrrCents),
                'trend' => null,
                'hint' => trans_choice(':count paying subscription|:count paying subscriptions', $activeSubscriptions->count()),
                'href' => route('platform.billing', ['tab' => 'subscriptions']),
            ],
            [
                'key' => 'revenue_month',
                'label' => 'Collected this month',
                'value' => $this->money($revenueThisMonthCents),
                'trend' => $revenueTrend,
                'hint' => $revenueTrend === null ? 'This month so far' : 'vs last month',
                'href' => route('platform.billing', ['tab' => 'payments', 'status' => PaymentStatus::Succeeded->value]),
            ],
            [
                'key' => 'tenants',
                'label' => 'Organizations',
                'value' => (int) $tenantsByStatus->sum(),
                'trend' => $this->trend($signups30d, $signupsPrior30d),
                'hint' => '+'.$signups30d.' new in 30 days',
                'href' => $tenantsIndex,
            ],
            [
                'key' => 'invoices_open',
                'label' => 'Open invoices',
                'value' => (int) ($openInvoices->aggregate ?? 0),
                'trend' => null,
                'hint' => $this->money((int) ($openInvoices->outstanding ?? 0)).' outstanding',
                'href' => route('platform.billing', ['tab' => 'invoices', 'status' => InvoiceStatus::Open->value]),
            ],
        ];

        $minorCards = [
            ['key' => 'active', 'label' => 'Active', 'value' => $statusCount(TenantStatus::Active), 'href' => route('platform.tenants.index', ['status' => TenantStatus::Active->value])],
            ['key' => 'trialing', 'label' => 'On trial', 'value' => $statusCount(TenantStatus::Trialing), 'href' => route('platform.tenants.index', ['status' => TenantStatus::Trialing->value])],
            ['key' => 'past_due', 'label' => 'Past due', 'value' => $statusCount(TenantStatus::PastDue), 'href' => route('platform.tenants.index', ['status' => TenantStatus::PastDue->value])],
            ['key' => 'suspended', 'label' => 'Suspended', 'value' => $statusCount(TenantStatus::Suspended), 'href' => route('platform.tenants.index', ['status' => TenantStatus::Suspended->value])],
            ['key' => 'employees', 'label' => 'Employees', 'value' => (int) Employee::query()->count(), 'href' => $tenantsIndex],
            ['key' => 'users', 'label' => 'User accounts', 'value' => (int) User::query()->whereNotNull('tenant_id')->count(), 'href' => $tenantsIndex],
        ];

        return [
            'cards' => $cards,
            'minor_cards' => $minorCards,
            'plan_breakdown' => $planBreakdown,
            'status_breakdown' => $this->statusBreakdown($tenantsByStatus->all()),
            'revenue_series' => $this->revenueSeries($now),
            'signup_series' => $this->signupSeries($now),
            'top_tenants' => $this->topTenantsByHeadcount(),
            'mrr_cents' => $mrrCents,
            'currency' => 'KES',
        ];
    }

    private function collectedBetween(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) Payment::query()
            ->where('status', PaymentStatus::Succeeded)
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount_cents');
    }

    /**
     * Percentage change from the previous period; null when there is nothing to compare against.
     */
    private function trend(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * @param  array<string, int|string>  $counts
     * @return array<int, array{status: string, label: string, count: int}>
     */
    private function statusBreakdown(array $counts): array
    {
        return collect(TenantStatus::cases())
            ->map(fn (TenantStatus $status): array => [
                'status' => $status->value,
                'label' => str($status->value)->headline()->toString(),
                'count' => (int) ($counts[$status->value] ?? 0),
            ])
            ->filter(fn (array $row): bool => $row['count'] > 0)
            ->values()
            ->all();
    }

    /**
     * Twelve calendar-month buckets ending with the current month, keyed `Y-m`.
     *
     * @return array<string, int>
     */
    private function emptyMonths(CarbonImmutable $now): array
    {
        $months = [];

        for ($i = 11; $i >= 0; $i--) {
            $months[$now->startOfMonth()->subMonthsNoOverflow($i)->format('Y-m')] = 0;
        }

        return $months;
    }

    /**
     * @return array<int, array{month: string, label: string, value: int}>
     */
    private function revenueSeries(CarbonImmutable $now): array
    {
        $months = $this->emptyMonths($now);

        Payment::query()
            ->where('status', PaymentStatus::Succeeded)
            ->where('paid_at', '>=', $now->startOfMonth()->subMonthsNoOverflow(11))
            ->toBase()
            ->get(['paid_at', 'amount_cents'])
            ->each(function (object $row) use (&$months): void {
                $key = CarbonImmutable::parse($row->paid_at)->format('Y-m');

                if (array_key_exists($key, $months)) {
                    $months[$key] += (int) $row->amount_cents;
                }
            });

        return $this->series($months);
    }

    /**
     * @return array<int, array{month: string, label: string, value: int}>
     */
    private function signupSeries(CarbonImmutable $now): array
    {
        $months = $this->emptyMonths($now);

        Tenant::query()
            ->where('created_at', '>=', $now->startOfMonth()->subMonthsNoOverflow(11))
            ->toBase()
            ->pluck('created_at')
            ->each(function (mixed $createdAt) use (&$months): void {
                $key = CarbonImmutable::parse($createdAt)->format('Y-m');

                if (array_key_exists($key, $months)) {
                    $months[$key]++;
                }
            });

        return $this->series($months);
    }

    /**
     * @param  array<string, int>  $months
     * @return array<int, array{month: string, label: string, value: int}>
     */
    private function series(array $months): array
    {
        return collect($months)
            ->map(fn (int $value, string $month): array => [
                'month' => $month,
                'label' => CarbonImmutable::createFromFormat('!Y-m', $month)->format('M'),
                'value' => $value,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, slug: string|null, employees: int}>
     */
    private function topTenantsByHeadcount(): array
    {
        $counts = Employee::query()
            ->toBase()
            ->selectRaw('tenant_id, count(*) as aggregate')
            ->groupBy('tenant_id')
            ->orderByDesc('aggregate')
            ->limit(5)
            ->pluck('aggregate', 'tenant_id');

        $tenants = Tenant::query()->whereIn('id', $counts->keys())->get(['id', 'name', 'slug'])->keyBy('id');

        return $counts
            ->map(fn (int|string $count, int|string $tenantId): array => [
                'id' => (int) $tenantId,
                'name' => (string) ($tenants->get($tenantId)?->name ?? '—'),
                'slug' => $tenants->get($tenantId)?->slug,
                'employees' => (int) $count,
            ])
            ->values()
            ->all();
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
