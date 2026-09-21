<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Billing\ChargeInvoice;
use App\Enums\PaymentProvider;
use App\Enums\Permission;
use App\Facades\Tenancy;
use App\Models\Plan;
use App\Services\Payment\PaymentManager;
use App\Services\Subscription\SubscriptionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * The payment gateway is the one the platform admin made active; tenants
     * cannot pick it.
     */
    public function store(Request $request, SubscriptionManager $subscriptions, ChargeInvoice $charge, PaymentManager $payments): JsonResponse
    {
        abort_unless($request->user()?->can(Permission::SubscriptionManage->value), 403);

        $validated = $request->validate([
            'plan' => ['required', 'string', 'exists:plans,slug'],
            'payer' => ['nullable', 'string', 'max:255'],
        ]);

        $plan = Plan::query()->where('slug', $validated['plan'])->where('is_active', true)->firstOrFail();
        $provider = $payments->activeProvider();

        // Paystack needs the payer's email; fall back to the signed-in user's.
        $payer = $validated['payer'] ?? ($provider === PaymentProvider::Paystack ? $request->user()?->email : null);

        [$subscription, $invoice, $payment] = $subscriptions->subscribe(
            Tenancy::getOrFail(),
            $plan,
            $provider,
            $payer,
        );

        $result = $payment !== null ? $charge->handle($payment) : null;

        return response()->json([
            'data' => [
                'subscription' => ['uuid' => $subscription->uuid, 'status' => $subscription->status->value],
                'invoice' => $invoice?->only(['uuid', 'number', 'total_cents', 'currency', 'status']),
                'payment' => $payment === null ? null : [
                    'uuid' => $payment->uuid,
                    'reference' => $payment->reference,
                    'status' => $result?->status->value ?? $payment->status->value,
                    'provider' => $provider->value,
                    'checkout_url' => $result?->redirectUrl,
                ],
            ],
        ], 201);
    }

    public function destroy(Request $request, SubscriptionManager $subscriptions): JsonResponse
    {
        abort_unless($request->user()?->can(Permission::SubscriptionManage->value), 403);

        $tenant = Tenancy::getOrFail();
        $subscription = $tenant->subscription;

        abort_if($subscription === null, 404);

        $subscriptions->cancel($subscription, immediately: $request->boolean('immediately'));

        return response()->json(['data' => ['status' => $subscription->fresh()?->status->value]]);
    }
}
