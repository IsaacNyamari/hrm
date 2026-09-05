<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Events\InvoicePaid;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(private readonly TenantContext $context) {}

    public function generateForSubscription(Subscription $subscription, CarbonImmutable $periodStart, CarbonImmutable $periodEnd): Invoice
    {
        return $this->context->run($subscription->tenant, function () use ($subscription, $periodStart, $periodEnd): Invoice {
            $plan = $subscription->plan;
            $subtotal = $plan->price_cents;
            $tax = (int) round($subtotal * (float) config('billing.tax_rate') / 100);

            return DB::transaction(function () use ($subscription, $plan, $subtotal, $tax, $periodStart, $periodEnd): Invoice {
                $invoice = Invoice::create([
                    'tenant_id' => $subscription->tenant_id,
                    'subscription_id' => $subscription->id,
                    'number' => $this->nextNumber($subscription->tenant_id),
                    'status' => InvoiceStatus::Open,
                    'currency' => $plan->currency,
                    'subtotal_cents' => $subtotal,
                    'tax_cents' => $tax,
                    'total_cents' => $subtotal + $tax,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'due_at' => CarbonImmutable::now(),
                ]);

                $invoice->items()->create([
                    'description' => sprintf(
                        '%s (%s) — %s to %s',
                        $plan->name,
                        $plan->billing_interval->label(),
                        $periodStart->toDateString(),
                        $periodEnd->toDateString(),
                    ),
                    'quantity' => 1,
                    'unit_amount_cents' => $subtotal,
                    'amount_cents' => $subtotal,
                ]);

                return $invoice;
            });
        });
    }

    /**
     * Apply a settled payment amount to the invoice, flipping it to Paid when
     * fully covered.
     */
    public function applyPayment(Invoice $invoice, int $amountCents): Invoice
    {
        $invoice->forceFill(['amount_paid_cents' => $invoice->amount_paid_cents + $amountCents]);

        if ($invoice->amount_paid_cents >= $invoice->total_cents && $invoice->status !== InvoiceStatus::Paid) {
            $invoice->forceFill(['status' => InvoiceStatus::Paid, 'paid_at' => CarbonImmutable::now()]);
            $invoice->save();
            InvoicePaid::dispatch($invoice);

            return $invoice;
        }

        $invoice->save();

        return $invoice;
    }

    /**
     * Platform-admin / offline settlement: force the invoice paid.
     */
    public function markPaid(Invoice $invoice, ?Payment $payment = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $payment): Invoice {
            if ($payment !== null) {
                $payment->forceFill([
                    'status' => PaymentStatus::Succeeded,
                    'paid_at' => CarbonImmutable::now(),
                ])->save();
            }

            $invoice->forceFill([
                'status' => InvoiceStatus::Paid,
                'amount_paid_cents' => $invoice->total_cents,
                'paid_at' => CarbonImmutable::now(),
            ])->save();

            InvoicePaid::dispatch($invoice);

            return $invoice;
        });
    }

    private function nextNumber(int $tenantId): string
    {
        $prefix = (string) config('billing.invoice_number_prefix', 'INV');
        $sequence = Invoice::query()->count() + 1;

        return sprintf('%s-%d-%05d', $prefix, $tenantId, $sequence);
    }
}
