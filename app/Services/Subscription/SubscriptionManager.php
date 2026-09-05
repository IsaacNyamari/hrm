<?php

declare(strict_types=1);

namespace App\Services\Subscription;

use App\Actions\Tenancy\ProvisionTenant;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Events\SubscriptionActivated;
use App\Events\SubscriptionCancelled;
use App\Events\SubscriptionExpired;
use App\Events\TrialStarted;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Scopes\TenantScope;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\InvoiceService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionManager
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly InvoiceService $invoices,
        private readonly ProvisionTenant $provisionTenant,
    ) {}

    public function startTrial(Tenant $tenant, Plan $plan): Subscription
    {
        return $this->context->run($tenant, fn (): Subscription => DB::transaction(function () use ($tenant, $plan): Subscription {
            $now = CarbonImmutable::now();
            $trialEnds = $now->addDays(max(0, $plan->trial_days));

            $subscription = Subscription::create([
                'tenant_id' => $tenant->getKey(),
                'plan_id' => $plan->getKey(),
                'status' => SubscriptionStatus::Trialing,
                'starts_at' => $now,
                'trial_ends_at' => $trialEnds,
                'current_period_start' => $now,
                'current_period_end' => $trialEnds,
            ]);

            $tenant->forceFill(['status' => TenantStatus::Trialing, 'trial_ends_at' => $trialEnds])->save();
            $this->provisionTenant->handle($tenant);

            TrialStarted::dispatch($subscription);

            return $subscription;
        }));
    }

    /**
     * Move the tenant onto $plan and, for a paid plan, raise an open invoice and
     * a pending payment for the first period.
     *
     * @return array{0: Subscription, 1: Invoice|null, 2: Payment|null}
     */
    public function subscribe(Tenant $tenant, Plan $plan, PaymentProvider $provider, ?string $payer = null): array
    {
        return $this->context->run($tenant, fn (): array => DB::transaction(function () use ($tenant, $plan, $provider, $payer): array {
            $now = CarbonImmutable::now();
            $periodEnd = $plan->billing_interval->advance($now);

            $subscription = $tenant->subscription()->first() ?? new Subscription(['tenant_id' => $tenant->getKey()]);
            $subscription->fill([
                'plan_id' => $plan->getKey(),
                'status' => $plan->price_cents === 0 ? SubscriptionStatus::Active : SubscriptionStatus::PastDue,
                'starts_at' => $subscription->starts_at ?? $now,
                'current_period_start' => $now,
                'current_period_end' => $periodEnd,
                'payment_provider' => $provider->value,
                'cancelled_at' => null,
                'ends_at' => null,
            ])->save();

            $this->provisionTenant->handle($tenant);

            if ($plan->price_cents === 0) {
                $tenant->forceFill(['status' => TenantStatus::Active])->save();
                SubscriptionActivated::dispatch($subscription);

                return [$subscription, null, null];
            }

            $invoice = $this->invoices->generateForSubscription($subscription, $now, $periodEnd);

            $payment = Payment::create([
                'tenant_id' => $tenant->getKey(),
                'invoice_id' => $invoice->getKey(),
                'provider' => $provider,
                'status' => PaymentStatus::Pending,
                'amount_cents' => $invoice->total_cents,
                'currency' => $invoice->currency,
                'reference' => 'PAY-'.Str::upper(Str::random(20)),
                'payer' => $payer,
            ]);

            return [$subscription, $invoice, $payment];
        }));
    }

    public function activatePaidInvoice(Invoice $invoice): void
    {
        $subscription = $invoice->subscription;

        if ($subscription === null) {
            return;
        }

        $this->context->run($subscription->tenant, function () use ($subscription, $invoice): void {
            $subscription->forceFill([
                'status' => SubscriptionStatus::Active,
                'current_period_start' => $invoice->period_start ?? $subscription->current_period_start,
                'current_period_end' => $invoice->period_end ?? $subscription->current_period_end,
            ])->save();

            $subscription->tenant->forceFill(['status' => TenantStatus::Active])->save();

            SubscriptionActivated::dispatch($subscription);
        });
    }

    public function changePlan(Subscription $subscription, Plan $plan): Subscription
    {
        return $this->context->run($subscription->tenant, function () use ($subscription, $plan): Subscription {
            $subscription->forceFill(['plan_id' => $plan->getKey()])->save();
            $this->provisionTenant->handle($subscription->tenant);

            return $subscription->refresh();
        });
    }

    public function cancel(Subscription $subscription, bool $immediately = false): Subscription
    {
        return $this->context->run($subscription->tenant, function () use ($subscription, $immediately): Subscription {
            $now = CarbonImmutable::now();

            $subscription->forceFill([
                'status' => SubscriptionStatus::Cancelled,
                'cancelled_at' => $now,
                'ends_at' => $immediately ? $now : ($subscription->current_period_end ?? $now),
            ])->save();

            if ($immediately) {
                $subscription->tenant->forceFill(['status' => TenantStatus::Cancelled])->save();
            }

            SubscriptionCancelled::dispatch($subscription);

            return $subscription;
        });
    }

    public function resume(Subscription $subscription): Subscription
    {
        return $this->context->run($subscription->tenant, function () use ($subscription): Subscription {
            $subscription->forceFill([
                'status' => SubscriptionStatus::Active,
                'cancelled_at' => null,
                'ends_at' => null,
            ])->save();

            $subscription->tenant->forceFill(['status' => TenantStatus::Active])->save();
            SubscriptionActivated::dispatch($subscription);

            return $subscription;
        });
    }

    /**
     * Sweep trials/past-due subscriptions whose deadline (plus grace) has
     * passed and expire them. Active subscriptions past their period end are
     * marked past-due. Returns the number expired.
     */
    public function expireOverdue(?CarbonImmutable $asOf = null): int
    {
        $asOf ??= CarbonImmutable::now();
        $grace = (int) config('billing.grace_days', 3);
        $expired = 0;

        Subscription::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereIn('status', [
                SubscriptionStatus::Trialing->value,
                SubscriptionStatus::PastDue->value,
                SubscriptionStatus::Active->value,
            ])
            ->with('tenant')
            ->chunkById(200, function ($subscriptions) use (&$expired, $asOf, $grace): void {
                foreach ($subscriptions as $subscription) {
                    if ($subscription->status === SubscriptionStatus::Active) {
                        if ($subscription->current_period_end !== null && $subscription->current_period_end->lessThanOrEqualTo($asOf)) {
                            $subscription->forceFill(['status' => SubscriptionStatus::PastDue])->save();
                        }

                        continue;
                    }

                    $deadline = $subscription->status === SubscriptionStatus::Trialing
                        ? $subscription->trial_ends_at
                        : $subscription->current_period_end?->addDays($grace);

                    if ($deadline === null || $deadline->greaterThan($asOf)) {
                        continue;
                    }

                    $subscription->forceFill([
                        'status' => SubscriptionStatus::Expired,
                        'ends_at' => $asOf,
                    ])->save();

                    $subscription->tenant->forceFill(['status' => TenantStatus::Expired])->save();
                    SubscriptionExpired::dispatch($subscription);
                    $expired++;
                }
            });

        return $expired;
    }
}
