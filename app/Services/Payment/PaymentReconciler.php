<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\PaymentStatus;
use App\Events\PaymentFailed;
use App\Events\PaymentSucceeded;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Scopes\TenantScope;
use App\Services\Billing\InvoiceService;
use App\Services\Subscription\SubscriptionManager;
use App\Support\Payment\WebhookEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Turns a verified provider callback into ledger changes. Idempotent: the
 * (provider, external_id) unique key on payment_transactions guarantees a given
 * provider event is applied at most once, even under retries or duplicates.
 */
class PaymentReconciler
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly SubscriptionManager $subscriptions,
    ) {}

    public function reconcile(WebhookEvent $event): PaymentTransaction
    {
        return DB::transaction(function () use ($event): PaymentTransaction {
            $transaction = PaymentTransaction::firstOrCreate(
                ['provider' => $event->provider, 'external_id' => $event->externalId],
                [
                    'type' => 'webhook',
                    'status' => $event->status->value,
                    'amount_cents' => $event->amountCents,
                    'currency' => $event->currency,
                    'payload' => $event->payload,
                    'processed_at' => CarbonImmutable::now(),
                ],
            );

            if (! $transaction->wasRecentlyCreated) {
                return $transaction;
            }

            $payment = $this->locatePayment($event);

            if ($payment === null) {
                return $transaction;
            }

            $transaction->forceFill([
                'payment_id' => $payment->getKey(),
                'tenant_id' => $payment->tenant_id,
            ])->save();

            $event->status === PaymentStatus::Succeeded
                ? $this->settle($payment, $event)
                : $this->fail($payment, $event);

            return $transaction;
        });
    }

    private function locatePayment(WebhookEvent $event): ?Payment
    {
        return Payment::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where(function ($query) use ($event): void {
                if ($event->reference !== null) {
                    $query->where('reference', $event->reference);
                }

                if ($event->externalId !== null) {
                    $query->orWhere('provider_reference', $event->externalId);
                }
            })
            ->first();
    }

    private function settle(Payment $payment, WebhookEvent $event): void
    {
        if ($payment->status === PaymentStatus::Succeeded) {
            return;
        }

        $payment->forceFill([
            'status' => PaymentStatus::Succeeded,
            'provider_reference' => $payment->provider_reference ?? $event->externalId,
            'paid_at' => CarbonImmutable::now(),
        ])->save();

        $invoice = $payment->invoice;

        if ($invoice !== null) {
            $this->invoices->applyPayment($invoice, $payment->amount_cents);

            if ($invoice->refresh()->isPaid()) {
                $this->subscriptions->activatePaidInvoice($invoice);
            }
        }

        PaymentSucceeded::dispatch($payment);
    }

    private function fail(Payment $payment, WebhookEvent $event): void
    {
        $payment->forceFill([
            'status' => PaymentStatus::Failed,
            'failure_reason' => $event->failureReason,
        ])->save();

        PaymentFailed::dispatch($payment);
    }
}
