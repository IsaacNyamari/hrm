<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Billing\ChargeInvoice;
use App\Enums\PaymentProvider;
use App\Enums\Permission;
use App\Facades\Tenancy;
use App\Models\Plan;
use App\Services\Subscription\SubscriptionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

class SubscriptionController extends Controller
{
    public function store(Request $request, SubscriptionManager $subscriptions, ChargeInvoice $charge): JsonResponse
    {
        abort_unless($request->user()?->can(Permission::SubscriptionManage->value), 403);

        $validated = $request->validate([
            'plan' => ['required', 'string', 'exists:plans,slug'],
            'provider' => ['required', new Enum(PaymentProvider::class)],
            'payer' => ['nullable', 'string', 'max:20'],
        ]);

        $plan = Plan::query()->where('slug', $validated['plan'])->where('is_active', true)->firstOrFail();
        $provider = PaymentProvider::from($validated['provider']);

        [$subscription, $invoice, $payment] = $subscriptions->subscribe(
            Tenancy::getOrFail(),
            $plan,
            $provider,
            $validated['payer'] ?? null,
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
